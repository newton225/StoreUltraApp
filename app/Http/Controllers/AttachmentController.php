<?php

namespace App\Http\Controllers;

use App\Actions\Attachments\DeleteAttachment;
use App\Actions\Attachments\RecordAttachment;
use App\Http\Requests\StoreAttachmentRequest;
use App\Models\Attachment;
use App\Models\AttachmentType;
use App\Models\AuditLog;
use App\Support\AttachmentAlerts;
use App\Support\Search;
use App\Support\UploadedFiles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    private const PER_PAGE = 30;

    /**
     * List the documents of one kind of owner (company, shops or clients) with the alerts of all.
     */
    public function index(Request $request, AttachmentAlerts $alerts): Response
    {
        $kind = in_array($request->query('kind'), AttachmentType::KINDS, true) ? (string) $request->query('kind') : 'company';
        $owners = $alerts->owners();

        $search = trim($request->string('search')->toString());

        $attachments = Attachment::query()
            ->with('type')
            ->whereHas('type', fn ($query) => $query->where('applies_to', $kind))
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->tap(fn ($q) => Search::contains($q, 'number', $search))
                ->tap(fn ($q) => Search::contains($q, 'notes', $search, or: true))))
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Attachment $attachment): array => [
                'id' => $attachment->id,
                'type' => $attachment->type->name,
                'owner' => $owners[$kind][$attachment->owner_id ?? 0] ?? '—',
                'number' => $attachment->number,
                'issued_on' => $attachment->issued_on?->toDateString(),
                'expires_on' => $attachment->expires_on?->toDateString(),
                'notes' => $attachment->notes,
                'has_file' => $attachment->file_path !== null,
                'file_type' => $attachment->file_path !== null && str_ends_with($attachment->file_path, '.pdf') ? 'pdf' : 'image',
            ]);

        $ownerOptions = [];

        foreach ($owners[$kind] as $id => $label) {
            $ownerOptions[] = ['id' => $id, 'name' => $label];
        }

        return Inertia::render('attachments/index', [
            'kind' => $kind,
            'kinds' => AttachmentType::KINDS,
            'attachments' => $attachments,
            'search' => $search,
            'owners' => $ownerOptions,
            'types' => AttachmentType::query()->where('active', true)->where('applies_to', $kind)->orderBy('label_fr')->get()
                ->map(fn (AttachmentType $type): array => ['id' => $type->id, 'name' => $type->name, 'required' => $type->required, 'has_expiry' => $type->has_expiry])->values(),
            'attachmentAlerts' => $alerts->all(),
            'preset' => [
                'type' => $request->integer('type') ?: null,
                'owner' => $request->has('owner') ? $request->integer('owner') : null,
            ],
        ]);
    }

    /**
     * Keep a document.
     */
    public function store(StoreAttachmentRequest $request, RecordAttachment $record): RedirectResponse
    {
        $attachment = $record->handle($request->safe()->except(['file', 'confirm_duplicate']), $request->file('file'), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('attachments.recorded')]);

        return to_route('attachments.index', ['kind' => $attachment->type->applies_to]);
    }

    /**
     * Remove a document.
     */
    public function destroy(Request $request, Attachment $attachment, DeleteAttachment $delete): RedirectResponse
    {
        $delete->handle($attachment, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('attachments.deleted')]);

        return back();
    }

    /**
     * Open the file of a document, or download it with ?download=1. It is private. An image is shown as it
     * is (the page previews it); a PDF is always a download.
     */
    public function file(Request $request, Attachment $attachment, UploadedFiles $files): StreamedResponse
    {
        abort_if($attachment->file_path === null, 404);

        AuditLog::record('attachment.opened', $attachment, [], ['number' => $attachment->number]);

        return $request->boolean('download') ? $files->download($attachment->file_path) : $files->response($attachment->file_path);
    }
}
