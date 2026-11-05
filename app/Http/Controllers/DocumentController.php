<?php

namespace App\Http\Controllers;

use App\Documents\DocumentIssuer;
use App\Enums\DocumentType;
use App\Exceptions\InvalidDocument;
use App\Http\Requests\CancelDocumentRequest;
use App\Http\Requests\StoreDocumentRequest;
use App\Models\Delivery;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    private const PER_PAGE = 30;

    /**
     * List the documents issued, with their state, and offer to prepare a statement.
     */
    public function index(Request $request): Response
    {
        $type = DocumentType::tryFrom($request->string('type')->toString());
        $search = trim($request->string('search')->toString());
        $state = $request->string('state')->toString();

        $documents = Document::query()
            ->with('subject')
            ->when($type !== null, fn (BuilderContract $query) => $query->where('type', $type->value))
            ->when($search !== '', fn (BuilderContract $query) => $query->whereLike('number', '%'.addcslashes($search, '%_\\').'%'))
            ->when($state === 'valid', fn (BuilderContract $query) => $query->whereNull('cancelled_at'))
            ->when($state === 'cancelled', fn (BuilderContract $query) => $query->whereNotNull('cancelled_at'))
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Document $document): array => [
                'id' => $document->id,
                'type' => $document->type->value,
                'number' => $document->number,
                'locale' => $document->locale,
                'about' => $this->describe($document),
                'issued_at' => $document->issued_at->toIso8601String(),
                'cancelled' => $document->isCancelled(),
                'cancel_reason' => $document->cancel_reason,
            ]);

        return Inertia::render('documents/index', [
            'documents' => $documents,
            'filters' => ['type' => $type?->value, 'search' => $search, 'state' => $state ?: null],
            'types' => array_column(DocumentType::cases(), 'value'),
            'period' => ['start' => today()->startOfMonth()->toDateString(), 'end' => today()->endOfMonth()->toDateString()],
        ]);
    }

    /**
     * Issue a document for a record.
     */
    public function store(StoreDocumentRequest $request, DocumentIssuer $issuer): RedirectResponse
    {
        $type = DocumentType::from($request->validated('type'));
        $modelClass = $type->subjectModel();
        $subject = $modelClass === null ? null : $modelClass::query()->findOrFail($request->integer('subject_id'));

        Gate::authorize('issue', [Document::class, $type, $subject]);

        try {
            $issuer->issue($type, $subject, $request->validated('locale'), $request->user(), [
                'period_start' => $request->validated('period_start'),
                'period_end' => $request->validated('period_end'),
                'fne_number' => $request->validated('fne_number'),
            ]);
        } catch (InvalidDocument $exception) {
            return back()->withErrors([$exception->field => __($exception->messageKey)]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('documents.ui.issued')]);

        return back();
    }

    /**
     * Open the PDF. It is never public: it is served to whoever can see the record behind it.
     */
    public function download(Document $document): StreamedResponse
    {
        Gate::authorize('view', $document);

        return Storage::disk('local')->response(
            $document->file_path,
            "{$document->number}.pdf",
            ['Content-Type' => 'application/pdf'],
            'inline',
        );
    }

    /**
     * Cancel a document, and replace it by a new one in the same step if asked.
     */
    public function cancel(CancelDocumentRequest $request, Document $document, DocumentIssuer $issuer): RedirectResponse
    {
        Gate::authorize('cancel', $document);

        try {
            $request->boolean('replace')
                ? $issuer->reissue($document, $request->validated('reason'), $request->validated('locale') ?? $document->locale, $request->user())
                : $issuer->cancel($document, $request->validated('reason'), $request->user());
        } catch (InvalidDocument $exception) {
            return back()->withErrors([$exception->field => __($exception->messageKey)]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('documents.ui.cancelled')]);

        return back();
    }

    /**
     * A short description of what the document is about.
     */
    private function describe(Document $document): string
    {
        $subject = $document->subject;

        return match (true) {
            $subject instanceof Order => (string) $subject->reference,
            $subject instanceof Payment => (string) $subject->receipt_number,
            $subject instanceof Delivery => (string) $subject->reference,
            $subject instanceof Invoice => (string) $subject->number,
            $subject instanceof PurchaseOrder => (string) $subject->reference,
            default => $document->period_start !== null
                ? $document->period_start->toDateString().' → '.$document->period_end?->toDateString()
                : '',
        };
    }
}
