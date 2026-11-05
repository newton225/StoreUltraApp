<?php

use App\Actions\Expenses\CancelExpense;
use App\Actions\Expenses\RecordExpense;
use App\Exceptions\InvalidExpense;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Delivery;
use App\Models\Expense;
use App\Models\ExpenseAllocation;
use App\Models\ExpenseType;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Models\Zone;
use App\Support\BusinessSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->type = ExpenseType::factory()->create(['code' => 'transport']);
    $this->employee = User::factory()->create();
    $this->week = '2026-07-13';
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function expensePayload(ExpenseType $type, array $overrides = []): array
{
    return [
        'expense_type_id' => $type->id,
        'amount' => 10000,
        'incurred_on' => today()->toDateString(),
        'scope' => 'week',
        'week' => '2026-07-13',
        ...$overrides,
    ];
}

function weekOrder(int $kits, string $week = '2026-07-13', string $status = 'received', ?Zone $zone = null): Order
{
    return Order::factory()
        ->for($zone ? Client::factory()->for($zone) : Client::factory())
        ->create(['kits_count' => $kits, 'delivery_week' => $week, 'status' => $status]);
}

/**
 * @return array<int, int>
 */
function sharesOf(Expense $expense): array
{
    return $expense->allocations()->orderBy('order_id')->pluck('amount', 'order_id')->all();
}

test('an expense for a delivery goes entirely to the order of that delivery', function () {
    $order = weekOrder(3);
    $delivery = Delivery::factory()->for($order)->create();

    $this->actingAs($this->employee)
        ->post(route('expenses.store'), expensePayload($this->type, ['scope' => 'delivery', 'delivery_id' => $delivery->id, 'week' => null, 'amount' => 30000]))
        ->assertSessionHasNoErrors();

    $expense = Expense::sole();

    expect($expense->scope)->toBe('delivery');
    expect($expense->created_by)->toBe($this->employee->id);
    expect(sharesOf($expense))->toBe([$order->id => 30000]);
    expect(AuditLog::where('action', 'expense.recorded')->count())->toBe(1);
});

test('an expense for one order goes to that order', function () {
    $order = weekOrder(3);

    $this->actingAs($this->employee)
        ->post(route('expenses.store'), expensePayload($this->type, ['scope' => 'order', 'order_id' => $order->id, 'week' => null]))
        ->assertSessionHasNoErrors();

    expect(sharesOf(Expense::sole()))->toBe([$order->id => 10000]);
});

test('an expense for a week is shared between the orders in proportion to their kits', function () {
    $big = weekOrder(3);
    $small = weekOrder(1);
    $cancelled = weekOrder(5, $this->week, 'cancelled');
    weekOrder(2, '2026-07-20');

    $this->actingAs($this->employee)->post(route('expenses.store'), expensePayload($this->type));

    expect(sharesOf(Expense::sole()))->toBe([$big->id => 7500, $small->id => 2500]);
    expect(ExpenseAllocation::where('order_id', $cancelled->id)->count())->toBe(0);
});

test('the shares always add up to the amount, the leftover francs going to the first orders', function () {
    $orders = [weekOrder(1), weekOrder(1), weekOrder(1)];

    $this->actingAs($this->employee)->post(route('expenses.store'), expensePayload($this->type, ['amount' => 100]));

    expect(array_values(sharesOf(Expense::sole())))->toBe([34, 33, 33]);
    expect(array_sum(sharesOf(Expense::sole())))->toBe(100);
    expect(array_keys(sharesOf(Expense::sole())))->toBe(array_map(fn ($order) => $order->id, $orders));
});

test('the setting can share an expense equally between the orders', function () {
    app(BusinessSettings::class)->set('expense_allocation', 'equal');
    $a = weekOrder(3);
    $b = weekOrder(1);

    $this->actingAs($this->employee)->post(route('expenses.store'), expensePayload($this->type));

    expect(sharesOf(Expense::sole()))->toBe([$a->id => 5000, $b->id => 5000]);
});

test('an expense for a zone only reaches the orders of that zone', function () {
    $zone = Zone::factory()->create();
    $inside = weekOrder(2, $this->week, 'received', $zone);
    weekOrder(2);

    $this->actingAs($this->employee)
        ->post(route('expenses.store'), expensePayload($this->type, ['scope' => 'zone', 'zone_id' => $zone->id]))
        ->assertSessionHasNoErrors();

    expect(sharesOf(Expense::sole()))->toBe([$inside->id => 10000]);
});

test('an expense for a shop only reaches the orders that buy from it', function () {
    $shop = Shop::factory()->create();
    $product = Product::factory()->for($shop)->create();
    $buying = readyOrder($this->week, [[$product, 2, 1000]], 'received');
    weekOrder(2);

    $this->actingAs($this->employee)
        ->post(route('expenses.store'), expensePayload($this->type, ['scope' => 'shop', 'shop_id' => $shop->id]))
        ->assertSessionHasNoErrors();

    expect(sharesOf(Expense::sole()))->toBe([$buying->id => 10000]);
});

test('a date inside the week is enough to pick the week', function () {
    $order = weekOrder(2);

    $this->actingAs($this->employee)->post(route('expenses.store'), expensePayload($this->type, ['week' => '2026-07-16']));

    expect(sharesOf(Expense::sole()))->toBe([$order->id => 10000]);
    expect(Expense::sole()->week->toDateString())->toBe($this->week);
});

test('an expense that reaches no order is refused', function () {
    $this->actingAs($this->employee)
        ->post(route('expenses.store'), expensePayload($this->type))
        ->assertSessionHasErrors('scope');

    expect(Expense::count())->toBe(0);
});

test('an expense is rejected for invalid data', function (Closure $mutate, string $field) {
    weekOrder(2);
    $inactive = ExpenseType::factory()->create(['active' => false]);

    $this->actingAs($this->employee)
        ->post(route('expenses.store'), $mutate(expensePayload($this->type), $inactive))
        ->assertSessionHasErrors($field);

    expect(Expense::count())->toBe(0);
})->with([
    'zero amount' => [fn (array $p) => [...$p, 'amount' => 0], 'amount'],
    'fractional amount' => [fn (array $p) => [...$p, 'amount' => 10.5], 'amount'],
    'unknown type' => [fn (array $p) => [...$p, 'expense_type_id' => 9999], 'expense_type_id'],
    'inactive type' => [fn (array $p, ExpenseType $inactive) => [...$p, 'expense_type_id' => $inactive->id], 'expense_type_id'],
    'future date' => [fn (array $p) => [...$p, 'incurred_on' => today()->addDay()->toDateString()], 'incurred_on'],
    'unknown scope' => [fn (array $p) => [...$p, 'scope' => 'planet'], 'scope'],
    'week scope without week' => [fn (array $p) => [...$p, 'week' => null], 'week'],
    'delivery scope without delivery' => [fn (array $p) => [...$p, 'scope' => 'delivery', 'week' => null], 'delivery_id'],
    'order scope without order' => [fn (array $p) => [...$p, 'scope' => 'order', 'week' => null], 'order_id'],
    'zone scope without zone' => [fn (array $p) => [...$p, 'scope' => 'zone'], 'zone_id'],
    'shop scope without shop' => [fn (array $p) => [...$p, 'scope' => 'shop'], 'shop_id'],
]);

test('a proof can be attached and opened by the office', function () {
    weekOrder(2);

    $this->actingAs($this->employee)
        ->post(route('expenses.store'), expensePayload($this->type, ['proof' => UploadedFile::fake()->create('ticket.pdf', 200, 'application/pdf')]))
        ->assertSessionHasNoErrors();

    $expense = Expense::sole();

    Storage::disk('local')->assertExists($expense->proof_path);
    $this->get(route('expenses.proof', $expense))->assertOk();
});

test('a proof of the wrong kind or too heavy is refused', function (UploadedFile $file) {
    weekOrder(2);

    $this->actingAs($this->employee)
        ->post(route('expenses.store'), expensePayload($this->type, ['proof' => $file]))
        ->assertSessionHasErrors('proof');

    expect(Expense::count())->toBe(0);
})->with([
    'an executable' => [fn () => UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream')],
    'a huge file' => [fn () => UploadedFile::fake()->create('big.pdf', 5000, 'application/pdf')],
]);

test('an expense without proof has nothing to open', function () {
    weekOrder(2);
    $this->actingAs($this->employee)->post(route('expenses.store'), expensePayload($this->type));

    $this->get(route('expenses.proof', Expense::sole()))->assertNotFound();
});

test('an expense is cancelled with a reason and stays in the books', function () {
    weekOrder(2);
    $this->actingAs($this->employee)->post(route('expenses.store'), expensePayload($this->type));
    $expense = Expense::sole();

    $this->post(route('expenses.cancel', $expense), [])->assertSessionHasErrors('reason');
    $this->post(route('expenses.cancel', $expense), ['reason' => 'Entered twice'])->assertSessionHasNoErrors();

    $expense->refresh();

    expect($expense->cancelled_at)->not->toBeNull();
    expect($expense->cancel_reason)->toBe('Entered twice');
    expect($expense->cancelled_by)->toBe($this->employee->id);
    expect($expense->allocations()->count())->toBe(1);

    $this->post(route('expenses.cancel', $expense), ['reason' => 'Again'])->assertSessionHasErrors('reason');
});

test('an expense can neither be edited nor deleted', function () {
    weekOrder(2);
    $this->actingAs($this->employee)->post(route('expenses.store'), expensePayload($this->type));
    $expense = Expense::sole();

    expect($expense->update(['amount' => 1]))->toBeFalse();
    expect($expense->delete())->toBeFalse();
    expect($expense->refresh()->amount)->toBe(10000);
});

test('a field agent cannot handle the expenses', function () {
    $expense = Expense::factory()->create();
    $this->actingAs(User::factory()->agent()->create());

    $this->get(route('expenses.index'))->assertForbidden();
    $this->get(route('expenses.create'))->assertForbidden();
    $this->post(route('expenses.store'), expensePayload($this->type))->assertForbidden();
    $this->post(route('expenses.cancel', $expense), ['reason' => 'x'])->assertForbidden();
    $this->get(route('expenses.proof', $expense))->assertForbidden();
});

test('guests are sent to the login page', function () {
    $this->get(route('expenses.index'))->assertRedirect(route('login'));
});

test('a cost cancelled by two requests that both saw it standing is cancelled once', function () {
    $expense = Expense::factory()->create();
    $first = Expense::findOrFail($expense->id);
    $second = Expense::findOrFail($expense->id);
    $cancel = app(CancelExpense::class);

    $cancel->handle($first, 'Doublon', $this->employee);

    expect(fn () => $cancel->handle($second, 'Doublon', $this->employee))->toThrow(InvalidExpense::class);
    expect(AuditLog::where('action', 'expense.cancelled')->count())->toBe(1);
    expect($first->isCancelled())->toBeTrue();
});

test('a cancellation whose trace cannot be written is not kept', function () {
    $expense = Expense::factory()->create();
    AuditLog::creating(function (): void {
        throw new RuntimeException('the journal is unavailable');
    });

    expect(fn () => app(CancelExpense::class)->handle($expense, 'Erreur', $this->employee))->toThrow(RuntimeException::class);

    expect($expense->refresh()->isCancelled())->toBeFalse();
});

test('a proof stored for a cost that could not be recorded is removed', function () {
    weekOrder(2, $this->week);
    AuditLog::creating(function (): void {
        throw new RuntimeException('the journal is unavailable');
    });

    expect(fn () => app(RecordExpense::class)->handle(
        expensePayload($this->type),
        UploadedFile::fake()->image('proof.png'),
        $this->employee,
    ))->toThrow(RuntimeException::class);

    expect(Expense::count())->toBe(0);
    expect(ExpenseAllocation::count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

test('the same submission sent twice records the cost once', function () {
    weekOrder(2, $this->week);
    $payload = expensePayload($this->type, ['idempotency_key' => 'form-1']);
    $this->actingAs($this->employee);

    $this->post(route('expenses.store'), $payload)->assertSessionHasNoErrors();
    $this->post(route('expenses.store'), $payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('expenses.index'));

    expect(Expense::count())->toBe(1);
    expect(ExpenseAllocation::count())->toBe(1);
    expect(AuditLog::where('action', 'expense.recorded')->count())->toBe(1);
});

test('a replayed submission with a proof does not keep a second file', function () {
    weekOrder(2, $this->week);
    $payload = expensePayload($this->type, ['idempotency_key' => 'form-1']);
    $this->actingAs($this->employee);

    $this->post(route('expenses.store'), [...$payload, 'proof' => UploadedFile::fake()->image('proof.png')]);
    $this->post(route('expenses.store'), [...$payload, 'proof' => UploadedFile::fake()->image('proof.png')]);

    expect(Expense::count())->toBe(1);
    expect(Storage::disk('local')->allFiles())->toHaveCount(1);
});

test('a replay is answered even when the cost would no longer find an order', function () {
    $order = weekOrder(2, $this->week);
    $payload = expensePayload($this->type, ['idempotency_key' => 'form-1']);
    $this->actingAs($this->employee);

    $this->post(route('expenses.store'), $payload)->assertSessionHasNoErrors();
    $order->forceFill(['status' => 'cancelled'])->save();
    $this->post(route('expenses.store'), $payload)->assertSessionHasNoErrors();

    expect(Expense::count())->toBe(1);
});

test('two different submissions of the same amount are two costs', function () {
    weekOrder(2, $this->week);
    $this->actingAs($this->employee);

    $this->post(route('expenses.store'), expensePayload($this->type, ['idempotency_key' => 'form-1']));
    $this->post(route('expenses.store'), expensePayload($this->type, ['idempotency_key' => 'form-2']));

    expect(Expense::count())->toBe(2);
});

test('a cost without a key is still recorded, without protection against a replay', function () {
    weekOrder(2, $this->week);
    $this->actingAs($this->employee);

    $this->post(route('expenses.store'), expensePayload($this->type))->assertSessionHasNoErrors();
    $this->post(route('expenses.store'), expensePayload($this->type))->assertSessionHasNoErrors();

    expect(Expense::count())->toBe(2);
});

test('an expense key that is too long is rejected', function () {
    weekOrder(2, $this->week);

    $this->actingAs($this->employee)
        ->post(route('expenses.store'), expensePayload($this->type, ['idempotency_key' => str_repeat('a', 65)]))
        ->assertSessionHasErrors('idempotency_key');

    expect(Expense::count())->toBe(0);
});
