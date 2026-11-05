<?php

use App\Documents\DocumentIssuer;
use App\Enums\DocumentType;
use App\Exceptions\InvalidDocument;
use App\Models\AuditLog;
use App\Models\Delivery;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Support\BusinessSettings;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create();
});

function issuer(): DocumentIssuer
{
    return app(DocumentIssuer::class);
}

test('a proforma is numbered, rendered as a pdf, stored and attached to its order', function () {
    $order = realOrder('draft');

    $document = issuer()->issue(DocumentType::Proforma, $order, 'fr', $this->user);

    expect($document->number)->toBe(sprintf('PRO-%d-0001', now()->year));
    expect($document->type)->toBe(DocumentType::Proforma);
    expect($document->subject_id)->toBe($order->id);
    expect($document->locale)->toBe('fr');
    expect($document->issued_by)->toBe($this->user->id);
    expect($document->cancelled_at)->toBeNull();
    expect(Storage::disk('local')->get($document->file_path))->toStartWith('%PDF');
    expect(AuditLog::where('action', 'document.issued')->count())->toBe(1);
});

test('each kind of document has its own sequence', function () {
    $order = realOrder('delivered');
    $delivery = Delivery::factory()->for($order)->create();

    $proforma = issuer()->issue(DocumentType::Proforma, $order, 'fr', $this->user);
    $note = issuer()->issue(DocumentType::DeliveryNote, $delivery, 'fr', $this->user);
    $invoice = issuer()->issue(DocumentType::Invoice, $order, 'fr', $this->user);

    $year = now()->year;

    expect($proforma->number)->toBe("PRO-{$year}-0001");
    expect($note->number)->toBe("BL-{$year}-0001");
    expect($invoice->number)->toBe("FAC-{$year}-0001");
});

test('a receipt keeps the number of its payment and a purchase order its own reference', function () {
    $order = realOrder('proforma_issued');
    $payment = Payment::factory()->for($order)->create([
        'receipt_number' => 'REC-2026-0042',
        'payment_method_id' => PaymentMethod::factory()->create()->id,
    ]);
    $purchase = PurchaseOrder::factory()->create(['reference' => 'BC-2026-0007', 'status' => 'sent']);

    expect(issuer()->issue(DocumentType::Receipt, $payment, 'fr', $this->user)->number)->toBe('REC-2026-0042');
    expect(issuer()->issue(DocumentType::PurchaseOrder, $purchase, 'fr', $this->user)->number)->toBe('BC-2026-0007');
});

test('a document cannot be issued twice while the first one is valid', function () {
    $order = realOrder('draft');
    issuer()->issue(DocumentType::Proforma, $order, 'fr', $this->user);

    issuer()->issue(DocumentType::Proforma, $order, 'en', $this->user);
})->throws(InvalidDocument::class);

test('a cancelled document is replaced by a new one that points back to it', function () {
    $order = realOrder('draft');
    $old = issuer()->issue(DocumentType::Proforma, $order, 'fr', $this->user);

    $new = issuer()->reissue($old, 'Wrong quantity', 'en', $this->user);

    $old->refresh();

    expect($old->cancelled_at)->not->toBeNull();
    expect($old->cancel_reason)->toBe('Wrong quantity');
    expect($old->cancelled_by)->toBe($this->user->id);
    expect($old->replaced_by_id)->toBe($new->id);
    expect($new->number)->not->toBe($old->number);
    expect($new->locale)->toBe('en');
    expect($new->cancelled_at)->toBeNull();
    Storage::disk('local')->assertExists($old->file_path);
});

test('an issued document cannot be edited, only cancelled', function () {
    $document = issuer()->issue(DocumentType::Proforma, realOrder('draft'), 'fr', $this->user);

    expect($document->update(['number' => 'FAKE']))->toBeFalse();
    expect($document->update(['file_path' => 'x']))->toBeFalse();
    expect($document->delete())->toBeFalse();
    expect($document->refresh()->number)->not->toBe('FAKE');
});

test('an invoice is only issued for an order that was delivered', function (string $status) {
    issuer()->issue(DocumentType::Invoice, realOrder($status), 'fr', $this->user);
})->with(['draft', 'proforma_issued', 'advance_received', 'ordered', 'received', 'cancelled'])->throws(InvalidDocument::class);

test('an invoice freezes the amounts and keeps the fne state', function () {
    $order = realOrder('settled');
    Payment::factory()->for($order)->create(['kind' => 'advance', 'amount' => 6000, 'payment_method_id' => PaymentMethod::factory()->create()->id]);

    $document = issuer()->issue(DocumentType::Invoice, $order, 'fr', $this->user);
    $invoice = Invoice::sole();

    expect($document->subject_type)->toBe(Invoice::class);
    expect($document->subject_id)->toBe($invoice->id);
    expect($invoice->order_id)->toBe($order->id);
    expect($invoice->client_id)->toBe($order->client_id);
    expect($invoice->total)->toBe(16500);
    expect($invoice->paid_amount)->toBe(6000);
    expect($invoice->fne_status)->toBe('not_required');
    expect($invoice->number)->toBe($document->number);
});

test('when the fne is required the invoice waits for its number', function () {
    app(BusinessSettings::class)->set('fne_mode', 'required');

    issuer()->issue(DocumentType::Invoice, realOrder('delivered'), 'fr', $this->user);

    expect(Invoice::sole()->fne_status)->toBe('to_issue');
});

test('the fne number can be given when the invoice is issued', function () {
    app(BusinessSettings::class)->set('fne_mode', 'required');

    issuer()->issue(DocumentType::Invoice, realOrder('delivered'), 'fr', $this->user, ['fne_number' => 'FNE-998877']);

    $invoice = Invoice::sole();

    expect($invoice->fne_status)->toBe('issued');
    expect($invoice->fne_number)->toBe('FNE-998877');
});

test('cancelling an invoice cancels the record and lets a new one be issued', function () {
    $order = realOrder('delivered');
    $document = issuer()->issue(DocumentType::Invoice, $order, 'fr', $this->user);

    $replacement = issuer()->reissue($document, 'Error', 'fr', $this->user);

    expect(Invoice::count())->toBe(2);
    expect(Invoice::whereNotNull('cancelled_at')->sole()->number)->toBe($document->number);
    expect(Invoice::whereNull('cancelled_at')->sole()->number)->toBe($replacement->number);
});

test('a statement lists the invoices of a period and has no subject', function () {
    $inside = realOrder('delivered');
    $outside = realOrder('delivered');
    issuer()->issue(DocumentType::Invoice, $inside, 'fr', $this->user);
    issuer()->issue(DocumentType::Invoice, $outside, 'fr', $this->user);
    Invoice::query()->where('order_id', $outside->id)->update(['issued_on' => today()->subMonths(2)]);

    $period = ['period_start' => today()->startOfMonth()->toDateString(), 'period_end' => today()->endOfMonth()->toDateString()];
    $statement = issuer()->issue(DocumentType::Statement, null, 'fr', $this->user, $period);

    expect($statement->number)->toBe(sprintf('BOR-%d-0001', now()->year));
    expect($statement->subject_type)->toBeNull();
    expect($statement->period_start->toDateString())->toBe($period['period_start']);
    expect(issuer()->statementInvoices($period['period_start'], $period['period_end']))->toHaveCount(1);

    issuer()->issue(DocumentType::Statement, null, 'fr', $this->user, $period);
})->throws(InvalidDocument::class);
