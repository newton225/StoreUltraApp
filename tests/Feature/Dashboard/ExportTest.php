<?php

use App\Enums\Permission;
use App\Models\Client;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\Zone;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 7, 15)->startOfDay());
    $this->owner = User::factory()->owner()->create();
    $this->period = ['from' => '2026-07-01', 'to' => '2026-07-31'];
});

/**
 * @return list<list<string>>
 */
function csvRows(string $content): array
{
    $lines = array_filter(explode("\n", str_replace("\r", '', ltrim($content, "\xEF\xBB\xBF"))), fn (string $line): bool => $line !== '');

    return array_map(fn (string $line): array => str_getcsv($line, ';', '"', ''), array_values($lines));
}

test('a csv opens in Excel: utf-8 with a marker, semicolons and a dated name', function () {
    Client::factory()->create(['name' => 'Aya Koné']);

    $response = $this->actingAs($this->owner)->get(route('exports.show', ['report' => 'clients', ...$this->period]));

    $response->assertOk()->assertDownload('storeultra-clients-2026-07-01-2026-07-31.csv');
    expect($response->streamedContent())->toStartWith("\xEF\xBB\xBF");
    expect(csvRows($response->streamedContent())[1])->toContain('Aya Koné');
    expect($response->headers->get('Content-Type'))->toContain('text/csv');
});

test('the orders export lists the period with what each client paid', function () {
    $order = realOrder('delivered', 3, 0);
    $order->forceFill(['delivery_week' => '2026-07-13'])->save();
    Payment::factory()->for($order)->create(['kind' => 'advance', 'amount' => 4000, 'payment_method_id' => PaymentMethod::factory()->create()->id]);
    $before = realOrder('delivered', 3, 0);
    $before->forceFill(['delivery_week' => '2026-05-04'])->save();

    $rows = csvRows($this->actingAs($this->owner)->get(route('exports.show', ['report' => 'orders', ...$this->period]))->streamedContent());

    expect($rows)->toHaveCount(2);
    expect($rows[1][1])->toBe('2026-07-13');
    expect($rows[1][5])->toBe('3');
    expect($rows[1][7])->toBe((string) $order->total_sale);
    expect($rows[1][9])->toBe('4000');
});

test('the payments export gives refunds as negative amounts', function () {
    $order = realOrder('delivered', 3, 0);
    $method = PaymentMethod::factory()->create();
    Payment::factory()->for($order)->create(['kind' => 'advance', 'amount' => 4000, 'paid_on' => '2026-07-10', 'payment_method_id' => $method->id]);
    Payment::factory()->for($order)->create(['kind' => 'refund', 'amount' => 500, 'paid_on' => '2026-07-11', 'payment_method_id' => $method->id]);
    Payment::factory()->for($order)->create(['kind' => 'advance', 'amount' => 999, 'paid_on' => '2026-05-11', 'payment_method_id' => $method->id]);

    $rows = csvRows($this->actingAs($this->owner)->get(route('exports.show', ['report' => 'payments', ...$this->period]))->streamedContent());

    expect($rows)->toHaveCount(3);
    expect(array_column($rows, 3))->toBe([__('dashboard.csv.amount'), '4000', '-500']);
});

test('the late advances, expenses, shops and profit exports have their headers', function (string $report, string $firstHeader) {
    $response = $this->actingAs($this->owner)->get(route('exports.show', ['report' => $report, ...$this->period]));

    $response->assertOk();
    expect(csvRows($response->streamedContent())[0][0])->toBe($firstHeader);
})->with([
    'arrears' => ['arrears', fn () => __('dashboard.csv.order')],
    'expenses' => ['expenses', fn () => __('dashboard.csv.incurred_on')],
    'shops' => ['shops', fn () => __('dashboard.csv.shop')],
    'profits' => ['profits', fn () => __('dashboard.csv.week')],
]);

test('the expenses export keeps the cancelled ones with their reason', function () {
    Expense::factory()->create(['incurred_on' => '2026-07-10', 'amount' => 7000, 'cancelled_at' => now(), 'cancel_reason' => 'Doublon']);

    $rows = csvRows($this->actingAs($this->owner)->get(route('exports.show', ['report' => 'expenses', ...$this->period]))->streamedContent());

    expect($rows[1][2])->toBe('7000');
    expect($rows[1][5])->toBe('Doublon');
});

test('a text that looks like a formula is made harmless', function () {
    Client::factory()->create(['name' => '=HYPERLINK("http://evil")']);

    $rows = csvRows($this->actingAs($this->owner)->get(route('exports.show', ['report' => 'clients', ...$this->period]))->streamedContent());

    expect($rows[1][1])->toStartWith("'=");
});

test('each profile downloads only the reports it may see', function () {
    $employee = User::factory()->create();
    $agent = User::factory()->agent()->create();
    $cases = [
        [$this->owner, ['orders', 'clients', 'payments', 'arrears', 'expenses', 'shops', 'profits']],
        [$employee, ['orders', 'clients', 'payments', 'arrears', 'expenses', 'shops']],
        [$agent, ['orders', 'clients']],
    ];

    foreach ($cases as [$user, $allowed]) {
        $this->actingAs($user);

        foreach (['orders', 'clients', 'payments', 'arrears', 'expenses', 'shops', 'profits'] as $report) {
            $status = $this->get(route('exports.show', ['report' => $report, ...$this->period]))->getStatusCode();

            expect($status)->toBe(in_array($report, $allowed, true) ? 200 : 404, $user->role->value.' '.$report);
        }
    }

    withProfile($employee, [Permission::ViewProfit]);

    $this->actingAs($employee)->get(route('exports.show', ['report' => 'profits', ...$this->period]))->assertOk();
});

test('an unknown report does not exist', function () {
    $this->actingAs($this->owner)->get(route('exports.show', ['report' => 'secrets']))->assertNotFound();
});

test('an agent only exports the orders and clients of their sub-prefectures', function () {
    $mine = Zone::factory()->create();
    $agent = User::factory()->agent()->create();
    $agent->zones()->attach($mine);
    Client::factory()->create(['name' => 'Chez moi', 'zone_id' => $mine->id]);
    Client::factory()->create(['name' => 'Ailleurs']);
    $order = Order::factory()->create(['delivery_week' => '2026-07-13']);
    $order->client->update(['zone_id' => $mine->id]);
    Order::factory()->create(['delivery_week' => '2026-07-13']);

    $clients = csvRows($this->actingAs($agent)->get(route('exports.show', ['report' => 'clients', ...$this->period]))->streamedContent());
    $orders = csvRows($this->get(route('exports.show', ['report' => 'orders', ...$this->period]))->streamedContent());

    expect($clients)->toHaveCount(3);
    expect(array_column($clients, 1))->not->toContain('Ailleurs');
    expect($orders)->toHaveCount(2);
});

test('the synthesis is a pdf in the language of the user', function () {
    realOrder('delivered');

    $response = $this->actingAs($this->owner)->get(route('dashboard.pdf', $this->period));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('application/pdf');
    expect(substr((string) $response->getContent(), 0, 4))->toBe('%PDF');
});
