<?php

namespace App\Documents;

use App\Enums\DocumentType;
use App\Enums\OrderStatus;
use App\Enums\PurchaseOrderStatus;
use App\Exceptions\InvalidDocument;
use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\Delivery;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Support\AmountInWords;
use App\Support\BusinessSettings;
use App\Support\CommercialSheet;
use App\Support\DocumentNumbers;
use App\Support\MoneyFormatter;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DocumentIssuer
{
    public function __construct(private DocumentNumbers $numbers, private BusinessSettings $settings) {}

    /**
     * Issue a document: number it, write it as a PDF in the chosen language, archive it and attach it
     * to its record.
     *
     * @param  array<string, mixed>  $options  period_start and period_end for a statement, fne_number for an invoice.
     *
     * @throws InvalidDocument when the document cannot be issued now
     */
    public function issue(DocumentType $type, ?Model $subject, string $locale, ?User $user = null, array $options = []): Document
    {
        return DB::transaction(function () use ($type, $subject, $locale, $user, $options): Document {
            $this->guard($type, $subject, $options);

            $record = $subject;

            if ($type === DocumentType::Invoice) {
                if (! $subject instanceof Order) {
                    throw new InvalidDocument('type', 'documents.errors.invoice_status');
                }

                $record = $this->createInvoice($subject, $user, $options['fne_number'] ?? null);
            }

            $number = $this->numberFor($type, $record);
            $pdf = Pdf::loadHTML($this->render($type, $record, $locale, $number, [...$options, 'issuer' => $user?->name]))->setPaper('a4')->output();
            $path = "documents/{$type->value}/{$number}.pdf";

            Storage::disk('local')->put($path, $pdf);

            $document = Document::create([
                'type' => $type,
                'number' => $number,
                'subject_type' => $record?->getMorphClass(),
                'subject_id' => $record?->getKey(),
                'locale' => $locale,
                'file_path' => $path,
                'period_start' => $options['period_start'] ?? null,
                'period_end' => $options['period_end'] ?? null,
                'issued_by' => $user?->id,
                'issued_at' => now(),
            ]);

            AuditLog::record('document.issued', $document, [], [
                'type' => $type->value,
                'number' => $number,
                'locale' => $locale,
            ], $user);

            return $document;
        });
    }

    /**
     * Cancel a document. The file is kept; the number is never reused.
     */
    public function cancel(Document $document, string $reason, ?User $user = null): void
    {
        DB::transaction(function () use ($document, $reason, $user): void {
            $document->forceFill([
                'cancelled_at' => now(),
                'cancelled_by' => $user?->id,
                'cancel_reason' => $reason,
            ])->save();

            if ($document->subject instanceof Invoice) {
                $document->subject->forceFill(['cancelled_at' => now()])->save();
            }

            AuditLog::record('document.cancelled', $document, [], ['reason' => $reason], $user);
        });
    }

    /**
     * Cancel a document and issue a new one in its place, pointing back to it.
     */
    public function reissue(Document $old, string $reason, string $locale, ?User $user = null): Document
    {
        return DB::transaction(function () use ($old, $reason, $locale, $user): Document {
            $this->cancel($old, $reason, $user);

            $subject = $old->subject instanceof Invoice ? $old->subject->order : $old->subject;
            $options = $old->type === DocumentType::Statement
                ? ['period_start' => $old->period_start?->toDateString(), 'period_end' => $old->period_end?->toDateString()]
                : [];

            $new = $this->issue($old->type, $subject, $locale, $user, $options);

            $old->forceFill(['replaced_by_id' => $new->id])->save();

            return $new;
        });
    }

    /**
     * The HTML of a document in the chosen language. The language of the interface is restored afterwards.
     *
     * @param  array<string, mixed>  $options
     */
    public function render(DocumentType $type, ?Model $subject, string $locale, string $number, array $options = []): string
    {
        $previous = App::getLocale();
        App::setLocale($locale);

        try {
            return view("documents.{$type->value}", [
                ...$this->base($locale, $number),
                ...$this->data($type, $subject, $locale, $options),
            ])->render();
        } finally {
            App::setLocale($previous);
        }
    }

    /**
     * The active invoices issued in a period, oldest first.
     *
     * @return Collection<int, Invoice>
     */
    public function statementInvoices(string $start, string $end): Collection
    {
        return Invoice::query()
            ->with('client:id,name')
            ->whereNull('cancelled_at')
            ->whereDate('issued_on', '>=', $start)
            ->whereDate('issued_on', '<=', $end)
            ->orderBy('issued_on')
            ->orderBy('id')
            ->get();
    }

    /**
     * Refuse a document that makes no sense now or that already exists.
     *
     * @param  array<string, mixed>  $options
     */
    private function guard(DocumentType $type, ?Model $subject, array $options): void
    {
        match ($type) {
            DocumentType::Proforma => $this->guardOrder($subject, fn (Order $order): bool => $order->status !== OrderStatus::Cancelled, 'documents.errors.order_cancelled'),
            DocumentType::Invoice => $this->guardOrder($subject, fn (Order $order): bool => in_array($order->status, [OrderStatus::Delivered, OrderStatus::Settled, OrderStatus::Closed], true), 'documents.errors.invoice_status'),
            DocumentType::PurchaseOrder => $this->guardPurchase($subject),
            DocumentType::Receipt, DocumentType::DeliveryNote, DocumentType::Statement => null,
        };

        $exists = match ($type) {
            DocumentType::Invoice => Invoice::query()->where('order_id', $subject?->getKey())->whereNull('cancelled_at')->exists(),
            DocumentType::Statement => Document::query()
                ->where('type', $type->value)
                ->whereNull('cancelled_at')
                ->whereDate('period_start', $options['period_start'] ?? '')
                ->whereDate('period_end', $options['period_end'] ?? '')
                ->exists(),
            default => Document::query()
                ->where('type', $type->value)
                ->where('subject_type', $subject?->getMorphClass())
                ->where('subject_id', $subject?->getKey())
                ->whereNull('cancelled_at')
                ->exists(),
        };

        if ($exists) {
            throw new InvalidDocument('type', 'documents.errors.exists');
        }
    }

    private function guardOrder(?Model $subject, callable $allowed, string $messageKey): void
    {
        if (! $subject instanceof Order || ! $allowed($subject)) {
            throw new InvalidDocument('type', $messageKey);
        }
    }

    private function guardPurchase(?Model $subject): void
    {
        if (! $subject instanceof PurchaseOrder || $subject->status === PurchaseOrderStatus::Cancelled) {
            throw new InvalidDocument('type', 'documents.errors.purchase_cancelled');
        }
    }

    private function createInvoice(Order $order, ?User $user, ?string $fneNumber): Invoice
    {
        $mode = $this->settings->get('fne_mode');

        return Invoice::create([
            'number' => $this->numbers->next('invoice'),
            'order_id' => $order->id,
            'client_id' => $order->client_id,
            'issued_on' => today()->toDateString(),
            'total' => $order->total_sale,
            'paid_amount' => $order->netPaid(),
            'fne_status' => filled($fneNumber) ? 'issued' : ($mode === 'required' ? 'to_issue' : 'not_required'),
            'fne_number' => filled($fneNumber) ? $fneNumber : null,
            'created_by' => $user?->id,
        ]);
    }

    private function numberFor(DocumentType $type, ?Model $subject): string
    {
        return match (true) {
            $subject instanceof Invoice => $subject->number,
            $subject instanceof Payment => (string) $subject->receipt_number,
            $subject instanceof PurchaseOrder => (string) $subject->reference,
            default => $this->numbers->next($type->value),
        };
    }

    /**
     * The numbers of the company documents meant to be printed (trade register, tax account...), as "label : number".
     *
     * @return list<string>
     */
    private function companyIdentifiers(string $locale): array
    {
        $lines = [];

        $attachments = Attachment::query()
            ->with('type')
            ->whereHas('type', fn ($query) => $query->where('applies_to', 'company')->where('on_documents', true)->where('active', true))
            ->orderBy('attachment_type_id')
            ->orderByDesc('id')
            ->get()
            ->unique('attachment_type_id');

        foreach ($attachments as $attachment) {
            $label = $locale === 'en' ? $attachment->type->label_en : $attachment->type->label_fr;
            $lines[] = $label.' : '.$attachment->number;
        }

        return $lines;
    }

    /**
     * @return array<string, mixed>
     */
    private function base(string $locale, string $number): array
    {
        return [
            'locale' => $locale,
            'number' => $number,
            'issuedOn' => now()->format('d/m/Y'),
            'company' => [
                'name' => $this->settings->get('company_name'),
                'address' => $this->settings->get('company_address'),
                'phone' => $this->settings->get('company_phone'),
                'identifiers' => $this->companyIdentifiers($locale),
            ],
            'money' => fn (int $amount): string => MoneyFormatter::format($amount, $locale),
            'plain' => fn (int $amount): string => $locale === 'en' ? number_format($amount, 0, '.', ',') : number_format($amount, 0, ',', ' '),
            'inWords' => fn (int $amount): string => AmountInWords::format($amount, $locale),
            'date' => fn (CarbonInterface|string $date): string => Carbon::parse($date)->format('d/m/Y'),
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function data(DocumentType $type, ?Model $subject, string $locale, array $options): array
    {
        return match ($type) {
            DocumentType::Proforma => $this->proformaData($subject, $options),
            DocumentType::Receipt => $this->receiptData($subject),
            DocumentType::PurchaseOrder => $this->purchaseData($subject),
            DocumentType::DeliveryNote => $this->deliveryData($subject),
            DocumentType::Invoice => $this->invoiceData($subject, $options),
            DocumentType::Statement => $this->statementData($options),
        };
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function proformaData(?Model $subject, array $options): array
    {
        /** @var Order $order */
        $order = $subject;
        $order->load('client.zone', 'kitVersion.kit', 'items.product');

        return [
            'order' => $order,
            'validityDays' => $this->settings->get('proforma_validity_days'),
            'sheet' => CommercialSheet::forProforma($order, $options['issuer'] ?? null),
            'projectTitle' => $this->settings->get('document_project_title'),
            'subtitle' => $this->settings->get('document_subtitle'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function receiptData(?Model $subject): array
    {
        /** @var Payment $payment */
        $payment = $subject;
        $payment->load('order.client', 'method', 'collector', 'recorder');

        return ['payment' => $payment, 'paidTotal' => $payment->order->netPaid()];
    }

    /**
     * @return array<string, mixed>
     */
    private function purchaseData(?Model $subject): array
    {
        /** @var PurchaseOrder $purchase */
        $purchase = $subject;
        $purchase->load('shop', 'items.product');

        return ['purchase' => $purchase];
    }

    /**
     * @return array<string, mixed>
     */
    private function deliveryData(?Model $subject): array
    {
        /** @var Delivery $delivery */
        $delivery = $subject;
        $delivery->load('order.client.zone', 'order.kitVersion.kit', 'order.items.product', 'agent');

        $signature = null;

        if ($delivery->signature_path !== null && Storage::disk('local')->exists($delivery->signature_path)) {
            $signature = 'data:image/png;base64,'.base64_encode(Storage::disk('local')->get($delivery->signature_path));
        }

        return [
            'delivery' => $delivery,
            'signature' => $signature,
            'sheet' => CommercialSheet::forDelivery($delivery),
            'projectTitle' => $this->settings->get('document_project_title'),
            'subtitle' => $this->settings->get('document_subtitle'),
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function invoiceData(?Model $subject, array $options): array
    {
        /** @var Invoice $invoice */
        $invoice = $subject;
        $invoice->load('client.zone', 'order.kitVersion.kit');

        return [
            'invoice' => $invoice,
            'sheet' => CommercialSheet::forInvoice($invoice, $options['issuer'] ?? null),
            'projectTitle' => $this->settings->get('document_project_title'),
            'subtitle' => $this->settings->get('document_subtitle'),
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function statementData(array $options): array
    {
        $invoices = $this->statementInvoices((string) $options['period_start'], (string) $options['period_end']);

        $rows = $invoices->map(function (Invoice $invoice): array {
            $paid = $invoice->order->netPaid();

            return [
                'number' => $invoice->number,
                'issued_on' => $invoice->issued_on,
                'client' => $invoice->client->name,
                'total' => $invoice->total,
                'paid' => $paid,
                'remaining' => max(0, $invoice->total - $paid),
            ];
        });

        return [
            'periodStart' => $options['period_start'],
            'periodEnd' => $options['period_end'],
            'rows' => $rows,
            'totals' => [
                'total' => (int) $rows->sum('total'),
                'paid' => (int) $rows->sum('paid'),
                'remaining' => (int) $rows->sum('remaining'),
            ],
        ];
    }
}
