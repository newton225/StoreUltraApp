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

test('the delivery note uses the same sheet, with the quantities ordered and delivered and no price', function () {
    app(BusinessSettings::class)->set('document_project_title', 'Projet de latrines familiales');
    $order = realOrder('delivered', 3, 5000);
    $delivery = Delivery::factory()->for($order)->create(['kits_delivered' => 2, 'received_by' => 'Moussa Traore', 'signature_path' => null]);
    $item = $order->items()->with('product')->first();

    $html = htmlOf(DocumentType::DeliveryNote, $delivery);

    expect($html)
        ->toContain('Projet de latrines familiales')
        ->toContain($order->reference)
        ->toContain($order->client->zone->name)
        ->toContain($item->product->name)
        ->toContain('QTE commandée')
        ->toContain('QTE livrée')
        ->toContain('KITS LIVRÉS')
        ->toContain((string) intdiv($item->quantity, 3) * 2)
        ->toContain('Moussa Traore')
        ->not->toContain('NET À PAYER')
        ->not->toContain('Rapprochement avant nouvelle commande');
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

test('the invoice follows the model: header, goods line by line, totals, amount in words and signatures', function () {
    $settings = app(BusinessSettings::class);
    $settings->set('company_phone', '01 71 91 15 04');
    $settings->set('document_project_title', 'Projet de latrines familiales');
    $settings->set('document_subtitle', 'Matériaux de construction');

    $order = realOrder('delivered');
    Payment::factory()->for($order)->create(['kind' => 'advance', 'amount' => 5000]);
    $order->client->forceFill(['contact_person' => 'Ouattara Sikahoua', 'delivery_address' => 'Kationon 2'])->save();

    app(DocumentIssuer::class)->issue(DocumentType::Invoice, $order, 'fr', $this->user);
    $invoice = Invoice::sole();
    $item = $order->items()->with('product')->first();

    expect(htmlOf(DocumentType::Invoice, $invoice))
        ->toContain('Projet de latrines familiales')
        ->toContain('Matériaux de construction')
        ->toContain($order->client->zone->name)
        ->toContain('01 71 91 15 04')
        ->toContain('Kationon 2')
        ->toContain($item->product->name)
        ->toContain('NET À PAYER')
        ->toContain('16 500 FCFA')
        ->toContain('AVANCE')
        ->toContain('5 000 FCFA')
        ->toContain('RESTE À PAYER')
        ->toContain('11 500 FCFA')
        ->toContain('Seize mille cinq cents francs')
        ->toContain('Ouattara Sikahoua')
        ->toContain(e($this->user->name))
        ->toContain('Contrôle avant sortie');
});

test('the invoice reconciles the previous debt of the client with the new order', function () {
    $old = realOrder('delivered');
    $order = realOrder('delivered');
    $order->forceFill(['client_id' => $old->client_id])->save();
    Payment::factory()->for($old)->create(['kind' => 'advance', 'amount' => 4000]);
    Payment::factory()->for($order)->create(['kind' => 'advance', 'amount' => 5000]);

    app(DocumentIssuer::class)->issue(DocumentType::Invoice, $order->refresh(), 'fr', $this->user);
    $html = htmlOf(DocumentType::Invoice, Invoice::sole());

    // The old order cost 16 500 and 4 000 were paid: 12 500 are still owed. The new one leaves 11 500 to pay.
    expect($html)
        ->toContain('Ancienne dette')
        ->toContain($old->reference)
        ->toContain('12 500')
        ->toContain('Payé en partie')
        ->toContain('24 000');
});

test('the proforma uses the same sheet as the invoice, with the advance still to be paid', function () {
    $settings = app(BusinessSettings::class);
    $settings->set('document_project_title', 'Projet de latrines familiales');
    $settings->set('document_subtitle', 'Matériaux de construction');

    $order = realOrder('proforma_issued', 3, 5000);
    $item = $order->items()->with('product')->first();

    $html = htmlOf(DocumentType::Proforma, $order, 'fr', ['issuer' => 'Awa Traoré']);

    expect($html)
        ->toContain('Projet de latrines familiales')
        ->toContain('Matériaux de construction')
        ->toContain($order->client->zone->name)
        ->toContain($item->product->name)
        ->toContain('NET À PAYER')
        ->toContain('16 500 FCFA')
        ->toContain('AVANCE À VERSER')
        ->toContain('5 000 FCFA')
        ->toContain('RESTE À PAYER À LA LIVRAISON')
        ->toContain('11 500 FCFA')
        ->toContain('Seize mille cinq cents francs')
        ->toContain('Valable')
        ->toContain('Rapprochement avant nouvelle commande')
        ->toContain('À verser')
        ->toContain('Awa Traoré')
        ->not->toContain('Contrôle avant sortie');
});

test('an invoice without any previous debt says so', function () {
    app(DocumentIssuer::class)->issue(DocumentType::Invoice, realOrder('delivered'), 'fr', $this->user);

    expect(htmlOf(DocumentType::Invoice, Invoice::sole()))->toContain('Ancienne dette')->toContain('Aucune');
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
