<?php

use App\Actions\Purchases\GeneratePurchaseOrders;
use App\Documents\DocumentIssuer;
use App\Enums\DocumentType;
use App\Models\Delivery;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Shop;
use App\Models\User;
use App\Support\BusinessSettings;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create();
    app(BusinessSettings::class)->set('company_name', 'Zebe Kits SARL');
});

function htmlOf(DocumentType $type, $subject, string $locale = 'fr', array $options = []): string
{
    return app(DocumentIssuer::class)->render($type, $subject, $locale, 'NUM-TEST-0001', $options);
}

test('the proforma shows the company, the client, the lines, the totals and the advance', function () {
    $order = realOrder('draft', 3, 5000);

    $html = htmlOf(DocumentType::Proforma, $order);

    expect($html)
        ->toContain('Zebe Kits SARL')
        ->toContain('NUM-TEST-0001')
        ->toContain('Aya Kone')
        ->toContain(__('documents.proforma.title', [], 'fr'))
        ->toContain('16 500 FCFA')
        ->toContain('5 000 FCFA')
        ->toContain('11 500 FCFA');
});

test('the same document is written in the language chosen', function () {
    $order = realOrder('draft', 3, 5000);

    $english = htmlOf(DocumentType::Proforma, $order, 'en');

    expect($english)
        ->toContain(__('documents.proforma.title', [], 'en'))
        ->toContain('16,500 FCFA')
        ->not->toContain(__('documents.proforma.title', [], 'fr'));
    expect(__('documents.proforma.title', [], 'en'))->not->toBe(__('documents.proforma.title', [], 'fr'));
});

test('the language of the interface is restored after rendering', function () {
    app()->setLocale('fr');

    htmlOf(DocumentType::Proforma, realOrder('draft'), 'en');

    expect(app()->getLocale())->toBe('fr');
});

test('a receipt shows the amount, the method and what is left to pay', function () {
    $order = realOrder('proforma_issued', 3, 5000);
    $payment = Payment::factory()->for($order)->create([
        'receipt_number' => 'REC-2026-0042',
        'kind' => 'advance',
        'amount' => 5000,
        'reference' => 'MP123',
        'payment_method_id' => PaymentMethod::factory()->create(['label_fr' => 'Mobile money'])->id,
    ]);

    $html = htmlOf(DocumentType::Receipt, $payment);

    expect($html)->toContain('Aya Kone')->toContain('5 000 FCFA')->toContain('Mobile money')->toContain('MP123')->toContain('11 500 FCFA');
});

test('a delivery note shows who received the kits and the signature', function () {
    Storage::disk('local')->put('signatures/s.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
    $order = realOrder('delivered');
    $delivery = Delivery::factory()->for($order)->create(['kits_delivered' => 3, 'received_by' => 'Moussa Traore', 'signature_path' => 'signatures/s.png']);

    $html = htmlOf(DocumentType::DeliveryNote, $delivery);

    expect($html)->toContain('Moussa Traore')->toContain('Aya Kone')->toContain('data:image/png;base64,');
});

test('a delivery note without a signature has no image', function () {
    $delivery = Delivery::factory()->for(realOrder('delivered'))->create(['signature_path' => null]);

    expect(htmlOf(DocumentType::DeliveryNote, $delivery))->not->toContain('data:image/png;base64,');
});

test('a purchase order lists what is asked from the shop', function () {
    $product = Product::factory()->for(Shop::factory()->create(['name' => 'Boutique 1']))->create(['name' => 'Barre de fer']);
    readyOrder(today()->startOfWeek()->toDateString(), [[$product, 4, 1775]]);
    app(GeneratePurchaseOrders::class)->handle(today());
    $purchase = PurchaseOrder::firstOrFail();

    $html = htmlOf(DocumentType::PurchaseOrder, $purchase);

    expect($html)->toContain($purchase->shop->name)->toContain($purchase->items->first()->product->name)->toContain('7 100 FCFA');
});

test('an invoice shows the fne number when there is one', function () {
    $document = app(DocumentIssuer::class)->issue(DocumentType::Invoice, realOrder('delivered'), 'fr', $this->user, ['fne_number' => 'FNE-998877']);

    expect(htmlOf(DocumentType::Invoice, Invoice::sole()))->toContain('FNE-998877')->toContain('16 500 FCFA');
    expect($document->number)->not->toBeNull();
});

test('a statement lists each invoice of the period with the totals', function () {
    $issuer = app(DocumentIssuer::class);
    $issuer->issue(DocumentType::Invoice, realOrder('delivered'), 'fr', $this->user);
    $issuer->issue(DocumentType::Invoice, realOrder('delivered', 2), 'fr', $this->user);
    $period = ['period_start' => today()->startOfMonth()->toDateString(), 'period_end' => today()->endOfMonth()->toDateString()];

    $html = htmlOf(DocumentType::Statement, null, 'fr', $period);

    foreach (Invoice::pluck('number') as $number) {
        expect($html)->toContain($number);
    }

    expect($html)->toContain('27 500 FCFA');
});
