<?php

use App\Enums\Permission;
use App\Models\Attachment;
use App\Models\AttachmentType;
use App\Models\AuditLog;
use App\Models\CashClosing;
use App\Models\CashMovement;
use App\Models\Client;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Profile;
use App\Models\User;
use App\Models\Zone;
use App\Support\Exports\ListExports;
use Illuminate\Testing\TestResponse;

function export(User $user, string $view, string $format, array $query = []): TestResponse
{
    return test()->actingAs($user)->get(route('list-exports.show', ['view' => $view, 'format' => $format, ...$query]));
}

/**
 * The text of the first sheet of an xlsx, to read what was written.
 */
function xlsxSheet(string $content): string
{
    $path = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
    file_put_contents($path, $content);
    $zip = new ZipArchive;
    $zip->open($path);
    $xml = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    unlink($path);

    return $xml;
}

test('every view can be exported in the three formats by the owner', function (string $view, string $format) {
    $owner = User::factory()->owner()->create();
    $response = export($owner, $view, $format)->assertOk();

    $content = $response->getContent();

    match ($format) {
        'csv' => expect($content)->toStartWith("\xEF\xBB\xBF"),
        'xlsx' => expect(substr($content, 0, 2))->toBe('PK')->and(xlsxSheet($content))->toContain('<sheetData>'),
        default => expect($content)->toStartWith('%PDF'),
    };
})->with(fn () => collect(ListExports::VIEWS)->crossJoin(['csv', 'xlsx', 'pdf'])->all());

test('the csv has the translated header, semicolons and filters', function () {
    $zone = Zone::factory()->create();
    Client::factory()->for($zone)->create(['name' => 'Alpha Travaux']);
    Client::factory()->for($zone)->create(['name' => 'Beta Services']);

    $content = export(User::factory()->owner()->create(), 'clients', 'csv', ['search' => 'Alpha'])->getContent();

    expect($content)->toContain('Référence;')
        ->and($content)->toContain('Alpha Travaux')
        ->and($content)->not->toContain('Beta Services');
});

test('amounts stay numbers in the spreadsheet and text is never a formula', function () {
    CashMovement::factory()->create(['amount' => 12345, 'label' => '=1+1']);
    $owner = User::factory()->owner()->create();

    $sheet = xlsxSheet(export($owner, 'cash', 'xlsx')->getContent());
    $csv = export($owner, 'cash', 'csv')->getContent();

    expect($sheet)->toContain('<v>12345</v>')
        ->and($sheet)->not->toContain('<f>')
        ->and($csv)->toContain("'=1+1");
});

test('a user who cannot open the page cannot export it', function () {
    $agent = User::factory()->agent()->create();

    export($agent, 'payments', 'csv')->assertNotFound();
    export($agent, 'cash', 'xlsx')->assertNotFound();
    export($agent, 'audit', 'pdf')->assertNotFound();
    export(User::factory()->owner()->create(), 'clients', 'doc')->assertNotFound();
    export(User::factory()->owner()->create(), 'secrets', 'csv')->assertNotFound();
});

test('a field agent only exports the clients of their zones', function () {
    $mine = Zone::factory()->create();
    $agent = User::factory()->agent()->create();
    $agent->zones()->attach($mine);
    Client::factory()->for($mine)->create(['name' => 'Client de ma zone']);
    Client::factory()->create(['name' => 'Client ailleurs']);

    $content = export($agent, 'clients', 'csv')->getContent();

    expect($content)->toContain('Client de ma zone')->and($content)->not->toContain('Client ailleurs');
});

test('purchase prices are only exported with the profit right', function () {
    $product = Product::factory()->create(['name' => 'Tuyau']);
    ProductPrice::factory()->create(['product_id' => $product->id, 'purchase_price' => 777, 'sale_price' => 999]);

    $catalogOnly = User::factory()->create(['profile_id' => Profile::factory()->granting([Permission::ManageCatalog])->create()->id]);
    $withProfit = User::factory()->create(['profile_id' => Profile::factory()->granting([Permission::ManageCatalog, Permission::ViewProfit])->create()->id]);

    $without = export($catalogOnly, 'products', 'csv')->getContent();
    $with = export($withProfit, 'products', 'csv')->getContent();

    expect($without)->toContain('Tuyau')->and($without)->not->toContain('777')->and($without)->not->toContain('Prix d\'achat')
        ->and($with)->toContain('777');
});

test('each export is written to the audit journal with the filters', function () {
    $owner = User::factory()->owner()->create();

    export($owner, 'clients', 'csv', ['search' => 'Alpha'])->assertOk();

    $entry = AuditLog::query()->where('action', 'export.generated')->firstOrFail();

    expect($entry->user_id)->toBe($owner->id)
        ->and($entry->after['view'])->toBe('clients')
        ->and($entry->after['format'])->toBe('csv')
        ->and($entry->after['filters'])->toBe(['search' => 'Alpha']);
});

test('an export is never cached', function () {
    export(User::factory()->owner()->create(), 'clients', 'csv')->assertHeader('Referrer-Policy', 'no-referrer');
});

test('the late advances are exported with what is paid and what is left, and follow the zone filter', function () {
    $order = Order::factory()->for(Client::factory()->create(['name' => 'Retard SARL']))->create(['status' => 'proforma_issued', 'delivery_week' => today()->startOfWeek()->subWeek(), 'advance_due' => 40000]);
    Payment::factory()->create(['order_id' => $order->id, 'kind' => 'advance', 'amount' => 15000]);
    $other = Order::factory()->for(Client::factory()->create(['name' => 'Autre Zone']))->create(['status' => 'draft', 'delivery_week' => today()->startOfWeek()->subWeek(), 'advance_due' => 10000]);

    $csv = export(User::factory()->owner()->create(), 'arrears', 'csv', ['zone' => $order->client->zone_id])->getContent();

    expect($csv)->toContain('Retard SARL')->toContain(';40000;15000;25000')->not->toContain('Autre Zone')
        ->and($other->client->zone_id)->not->toBe($order->client->zone_id);
});

test('the cash closings are exported with their difference', function () {
    CashClosing::factory()->create(['closed_on' => today()->subDay(), 'expected_amount' => 100000, 'counted_amount' => 98000, 'difference' => -2000, 'notes' => 'Billet déchiré']);

    $csv = export(User::factory()->owner()->create(), 'closings', 'csv')->getContent();

    expect($csv)->toContain(';100000;98000;-2000;')->toContain('Billet déchiré');
});

test('the administrative documents are exported without their file, for the kind on the page', function () {
    $type = AttachmentType::factory()->create(['applies_to' => 'company']);
    Attachment::factory()->create(['attachment_type_id' => $type->id, 'number' => 'RCCM-0042', 'expires_on' => '2027-01-31', 'file_path' => 'attachments/secret-file-name.pdf']);
    $shopType = AttachmentType::factory()->create(['applies_to' => 'shop']);
    Attachment::factory()->create(['attachment_type_id' => $shopType->id, 'number' => 'BOUTIQUE-9']);

    $csv = export(User::factory()->owner()->create(), 'attachments', 'csv', ['kind' => 'company'])->getContent();

    expect($csv)->toContain('RCCM-0042')->toContain('2027-01-31')->not->toContain('secret-file-name')->not->toContain('BOUTIQUE-9');
});

test('the users export lists the profile and the state, never a secret', function () {
    $owner = User::factory()->owner()->create();
    User::factory()->create(['name' => 'Awa Bureau', 'email' => 'awa@example.com', 'is_active' => false]);

    $csv = export($owner, 'users', 'csv')->getContent();

    expect($csv)->toContain('Awa Bureau')->toContain('awa@example.com')->toContain('Inactif')
        ->and($csv)->not->toContain('$2y$')
        ->and(strtolower($csv))->not->toContain('password');
});

test('the new exports follow the right of their page', function () {
    $costsOnly = User::factory()->create(['profile_id' => Profile::factory()->granting([Permission::ManageExpenses])->create()->id]);
    $cashOnly = User::factory()->create(['profile_id' => Profile::factory()->granting([Permission::ManageCash])->create()->id]);
    $orders = User::factory()->create(['profile_id' => Profile::factory()->granting([Permission::ManageOrders])->create()->id]);

    export($costsOnly, 'closings', 'csv')->assertNotFound();
    export($cashOnly, 'closings', 'csv')->assertOk();
    export($orders, 'arrears', 'csv')->assertOk();
    export($cashOnly, 'arrears', 'csv')->assertNotFound();
    export($orders, 'users', 'csv')->assertNotFound();
    export($orders, 'attachments', 'csv')->assertNotFound();
});
