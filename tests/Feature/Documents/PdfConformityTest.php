<?php

use App\Actions\Orders\CreateOrder;
use App\Documents\DocumentIssuer;
use App\Enums\DocumentType;
use App\Models\Client;
use App\Models\Delivery;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use App\Support\BusinessSettings;
use Illuminate\Support\Facades\Storage;

/**
 * What a reader of the printed document sees (the checks the owner did by eye on the real PDF): the company and
 * never the application, no label without its value, the right number label, and a document of a normal size on
 * ONE page, not a page holding only the signatures.
 */
beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create();

    $settings = app(BusinessSettings::class);
    $settings->set('company_name', 'Zebe Kits SARL');
    $settings->set('company_address', 'Cocody Riviera, Abidjan');
    $settings->set('company_email', 'contact@zebe.ci');
});

/** An order of a kit of twelve products: as long as the real ones. */
function longOrder(string $status = 'delivered')
{
    $lines = [];

    foreach (range(1, 12) as $n) {
        $product = Product::factory()->create(['name' => "Produit numéro {$n} ".fake()->unique()->numerify('###')]);
        ProductPrice::factory()->for($product)->create(['purchase_price' => 1000 + $n]);
        $lines[] = [$product, 3, 2500 + $n];
    }

    $order = app(CreateOrder::class)->handle(
        client: Client::factory()->create(['name' => 'Aya Kone', 'phone' => '07 09 46 16 68']),
        version: makeKitVersion($lines),
        kitsCount: 3,
        deliveryWeek: today(),
        advanceAmount: 20000,
    );
    $order->forceFill(['status' => $status])->save();

    return $order;
}

/** How many pages a PDF has. */
function pagesOf(string $pdf): int
{
    return preg_match_all('/\/Type\s*\/Page\b/', $pdf);
}

test('a proforma, an invoice and a delivery note of twelve lines each fit on one page', function () {
    $order = longOrder();
    $delivery = Delivery::factory()->create(['order_id' => $order->id, 'kits_delivered' => 3]);
    $issuer = app(DocumentIssuer::class);

    foreach ([
        $issuer->issue(DocumentType::Proforma, $order, 'fr', $this->user),
        $issuer->issue(DocumentType::Invoice, $order, 'fr', $this->user),
        $issuer->issue(DocumentType::DeliveryNote, $delivery, 'fr', $this->user),
        $issuer->issue(DocumentType::Proforma, longOrder('draft'), 'en', $this->user),
    ] as $document) {
        $pdf = Storage::disk('local')->get($document->file_path);

        expect(str_starts_with($pdf, '%PDF'))->toBeTrue()
            ->and(pagesOf($pdf))->toBe(1, "{$document->number} must be on one page");
    }
});

test('the page shows the company as supplier, one client phone, and no label without its value', function () {
    $html = app(DocumentIssuer::class)->render(DocumentType::Proforma, longOrder('draft'), 'fr', 'PRO-TEST-0001');

    expect($html)
        ->toContain('Fournisseur :</span> Zebe Kits SARL')
        ->toContain('Téléphone du client')
        ->toContain('07 09 46 16 68')
        ->not->toContain(config('app.name'));

    // The company phone is in the letterhead only: the information block does not repeat it (an empty one showed as a bare « Téléphone : »).
    expect(substr_count($html, 'Téléphone'))->toBe(substr_count($html, 'Téléphone du client'));

    // Not one bold label followed by nothing, whatever is missing.
    expect(preg_match('/<span class="strong">[^<]+ :<\/span>\s*(?:&nbsp;)?\s*<\/td>/', $html))->toBe(0);
});

test('each kind of sheet names its own number', function () {
    $order = longOrder();
    $delivery = Delivery::factory()->create(['order_id' => $order->id, 'kits_delivered' => 3]);
    $issuer = app(DocumentIssuer::class);

    // An invoice is its own record, created when the document is issued.
    $invoice = $issuer->issue(DocumentType::Invoice, $order, 'fr', $this->user)->subject;

    expect($issuer->render(DocumentType::Proforma, $order, 'fr', 'N-1'))->toContain('N° de la proforma')->not->toContain('N° du bon :')
        ->and($issuer->render(DocumentType::Invoice, $invoice, 'fr', 'N-1'))->toContain('N° de facture')
        ->and($issuer->render(DocumentType::DeliveryNote, $delivery, 'fr', 'N-1'))->toContain('N° du bon de livraison')
        ->and($issuer->render(DocumentType::Invoice, $invoice, 'en', 'N-1'))->toContain('Invoice no.');
});

test('a sentence of the English documents says « issued for the sum of », not a literal translation', function () {
    expect(__('documents.invoice.in_words', [], 'en'))->toContain('issued for the sum of')->not->toContain('closed at')
        ->and(__('documents.proforma.in_words', [], 'en'))->toContain('issued for the sum of');
});
