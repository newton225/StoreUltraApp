<?php

use App\Enums\Permission;
use App\Models\AuditLog;
use App\Models\CashClosing;
use App\Models\CashMovement;
use App\Models\Client;
use App\Models\Expense;
use App\Models\ExpenseType;
use App\Models\Order;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->owner = User::factory()->owner()->create();
    $this->type = ExpenseType::factory()->create(['code' => 'transport', 'label_fr' => 'Transport', 'label_en' => 'Transport']);
});

function userWith(array $permissions): User
{
    return User::factory()->create(['profile_id' => Profile::factory()->granting($permissions)->create()->id]);
}

function closingPayload(array $overrides = []): array
{
    return ['closed_on' => today()->toDateString(), 'counted_amount' => 100000, ...$overrides];
}

// --- The right of its own ---------------------------------------------------------------------------

test('the cash box needs its own right, the costs need theirs', function () {
    $costsOnly = userWith([Permission::ManageExpenses]);
    $cashOnly = userWith([Permission::ManageCash]);

    $this->actingAs($costsOnly)->get(route('cash.index'))->assertForbidden();
    $this->actingAs($costsOnly)->get(route('expenses.index'))->assertOk();
    $this->actingAs($cashOnly)->get(route('cash.index'))->assertOk();
    $this->actingAs($cashOnly)->get(route('expenses.index'))->assertForbidden();
});

test('profiles that held the costs right keep the cash box', function () {
    expect(Profile::query()->where('code', 'employee')->firstOrFail()->permissions)->toContain('manage_cash')
        ->and(Profile::query()->where('code', 'owner')->firstOrFail()->permissions)->toContain('manage_cash');
});

test('marking a cost as paid from the cash box needs the cash right', function () {
    Order::factory()->for(Client::factory())->create(['kits_count' => 1, 'delivery_week' => '2026-07-13', 'status' => 'received']);
    $payload = ['expense_type_id' => $this->type->id, 'amount' => 5000, 'incurred_on' => today()->toDateString(), 'scope' => 'week', 'week' => '2026-07-13', 'paid_from_cash' => '1'];

    $this->actingAs(userWith([Permission::ManageExpenses]))->post(route('expenses.store'), $payload)->assertSessionHasErrors('paid_from_cash');
    expect(Expense::count())->toBe(0);

    $this->actingAs(userWith([Permission::ManageExpenses, Permission::ManageCash]))->post(route('expenses.store'), $payload)->assertSessionHasNoErrors();
    expect(CashMovement::count())->toBe(1);
});

// --- Closing ---------------------------------------------------------------------------------------

test('a count that matches the books closes the cash box without any adjustment', function () {
    CashMovement::factory()->create(['amount' => 100000, 'occurred_on' => today()->subDays(2)]);

    $this->actingAs($this->owner)->post(route('cash.closings.store'), closingPayload())->assertRedirect();

    $closing = CashClosing::query()->firstOrFail();

    expect($closing->expected_amount)->toBe(100000)
        ->and($closing->counted_amount)->toBe(100000)
        ->and($closing->difference)->toBe(0)
        ->and($closing->reference)->toBe(sprintf('CLO-%04d', $closing->id))
        ->and($closing->closed_by)->toBe($this->owner->id)
        ->and(CashMovement::count())->toBe(1)
        ->and(AuditLog::where('action', 'cash_closing.recorded')->firstOrFail()->after['difference'])->toBe(0);
});

test('a shortage is written as one cash-out adjustment, a surplus as one cash-in', function () {
    CashMovement::factory()->create(['amount' => 100000, 'occurred_on' => today()->subDays(5)]);

    $this->actingAs($this->owner)->post(route('cash.closings.store'), closingPayload(['closed_on' => today()->subDays(3)->toDateString(), 'counted_amount' => 95000]))->assertRedirect();

    $shortage = CashMovement::query()->whereNotNull('closing_id')->firstOrFail();
    expect($shortage->direction)->toBe('out')->and($shortage->amount)->toBe(5000)->and($shortage->occurred_on->toDateString())->toBe(today()->subDays(3)->toDateString());

    $this->actingAs($this->owner)->post(route('cash.closings.store'), closingPayload(['closed_on' => today()->toDateString(), 'counted_amount' => 97000]))->assertRedirect();

    $surplus = CashMovement::query()->whereNotNull('closing_id')->orderByDesc('id')->firstOrFail();
    expect($surplus->direction)->toBe('in')->and($surplus->amount)->toBe(2000)
        ->and(CashClosing::query()->orderBy('id')->pluck('difference')->all())->toBe([-5000, 2000]);
});

test('the cash box cannot be closed in the future or not after the last closing', function () {
    $this->actingAs($this->owner)->post(route('cash.closings.store'), closingPayload(['closed_on' => today()->addDay()->toDateString()]))->assertSessionHasErrors('closed_on');

    $this->actingAs($this->owner)->post(route('cash.closings.store'), closingPayload())->assertSessionHasNoErrors();
    $this->actingAs($this->owner)->post(route('cash.closings.store'), closingPayload(['closed_on' => today()->subDay()->toDateString()]))->assertSessionHasErrors('closed_on');
    $this->actingAs($this->owner)->post(route('cash.closings.store'), closingPayload())->assertSessionHasErrors('closed_on');

    expect(CashClosing::count())->toBe(1);
});

test('a closing is never edited or deleted', function () {
    $closing = CashClosing::factory()->create();

    expect($closing->update(['counted_amount' => 5]))->toBeFalse()->and($closing->delete())->toBeFalse()
        ->and(CashClosing::count())->toBe(1);
});

test('once closed, nothing dated on or before the closing can be recorded or cancelled', function () {
    $movement = CashMovement::factory()->create(['amount' => 1000, 'occurred_on' => today()->subDays(4)]);
    $this->actingAs($this->owner)->post(route('cash.closings.store'), closingPayload(['closed_on' => today()->subDays(2)->toDateString(), 'counted_amount' => 3000]))->assertRedirect();
    $adjustment = CashMovement::query()->whereNotNull('closing_id')->firstOrFail();

    $this->actingAs($this->owner)->post(route('cash.store'), ['direction' => 'in', 'amount' => 10, 'occurred_on' => today()->subDays(2)->toDateString(), 'label' => 'Tardif'])->assertSessionHasErrors('occurred_on');
    $this->actingAs($this->owner)->post(route('cash.store'), ['direction' => 'in', 'amount' => 10, 'occurred_on' => today()->subDay()->toDateString(), 'label' => 'Après'])->assertSessionHasNoErrors();
    $this->actingAs($this->owner)->post(route('cash.cancel', $movement), ['reason' => 'Trop tard'])->assertSessionHasErrors('reason');
    $this->actingAs($this->owner)->post(route('cash.cancel', $adjustment), ['reason' => 'Trop tard'])->assertSessionHasErrors('reason');

    expect($movement->refresh()->isCancelled())->toBeFalse()->and($adjustment->refresh()->isCancelled())->toBeFalse();
});

test('a cost paid from the cash box cannot be dated in a closed period, nor cancelled once closed', function () {
    Order::factory()->for(Client::factory())->create(['kits_count' => 1, 'delivery_week' => '2026-07-13', 'status' => 'received']);
    $payload = fn (string $date) => ['expense_type_id' => $this->type->id, 'amount' => 5000, 'incurred_on' => $date, 'scope' => 'week', 'week' => '2026-07-13', 'paid_from_cash' => '1'];

    $this->actingAs($this->owner)->post(route('expenses.store'), $payload(today()->subDays(3)->toDateString()))->assertRedirect();
    $expense = Expense::query()->firstOrFail();
    $this->actingAs($this->owner)->post(route('cash.closings.store'), closingPayload(['closed_on' => today()->subDay()->toDateString(), 'counted_amount' => 0]))->assertRedirect();

    $this->actingAs($this->owner)->post(route('expenses.store'), $payload(today()->subDays(2)->toDateString()))->assertSessionHasErrors('incurred_on');
    $this->actingAs($this->owner)->post(route('expenses.cancel', $expense), ['reason' => 'Erreur'])->assertSessionHasErrors('reason');

    expect(Expense::count())->toBe(1)->and($expense->refresh()->isCancelled())->toBeFalse();
});

// --- Linking older costs ------------------------------------------------------------------------------

test('older costs ticked by the office get their cash-out movement, once, with a trace each', function () {
    $first = Expense::factory()->create(['expense_type_id' => $this->type->id, 'amount' => 7000, 'incurred_on' => today()->subDays(10)]);
    $second = Expense::factory()->create(['expense_type_id' => $this->type->id, 'amount' => 3000, 'incurred_on' => today()->subDays(8)]);

    $this->actingAs($this->owner)->get(route('cash.index'))
        ->assertInertia(fn ($page) => $page->has('unlinked_expenses', 2));

    $this->actingAs($this->owner)->post(route('cash.link-expenses'), ['expense_ids' => [$first->id]])->assertRedirect();
    $this->actingAs($this->owner)->post(route('cash.link-expenses'), ['expense_ids' => [$first->id]])->assertRedirect();

    $movement = CashMovement::query()->firstOrFail();

    expect(CashMovement::count())->toBe(1)
        ->and($movement->expense_id)->toBe($first->id)
        ->and($movement->direction)->toBe('out')
        ->and($movement->amount)->toBe(7000)
        ->and($movement->occurred_on->toDateString())->toBe(today()->subDays(10)->toDateString())
        ->and($first->refresh()->paid_from_cash)->toBeTrue()
        ->and($second->refresh()->paid_from_cash)->toBeFalse()
        ->and(AuditLog::where('action', 'expense.linked_to_cash')->count())->toBe(1);

    $this->actingAs($this->owner)->get(route('cash.index'))
        ->assertInertia(fn ($page) => $page->has('unlinked_expenses', 1)->where('unlinked_expenses.0.id', $second->id));
});

test('cancelled costs and costs of a closed period are skipped, never forced', function () {
    $old = Expense::factory()->create(['expense_type_id' => $this->type->id, 'incurred_on' => today()->subDays(10)]);
    $cancelled = Expense::factory()->create(['expense_type_id' => $this->type->id, 'incurred_on' => today()->subDays(2)]);
    $cancelled->forceFill(['cancelled_at' => now(), 'cancel_reason' => 'Erreur'])->save();
    $this->actingAs($this->owner)->post(route('cash.closings.store'), closingPayload(['closed_on' => today()->subDays(5)->toDateString(), 'counted_amount' => 0]))->assertRedirect();

    $this->actingAs($this->owner)->post(route('cash.link-expenses'), ['expense_ids' => [$old->id, $cancelled->id]])->assertRedirect();

    expect(CashMovement::whereNotNull('expense_id')->count())->toBe(0)->and($old->refresh()->paid_from_cash)->toBeFalse();
});

test('linking needs both rights, and the mark can never be taken off', function () {
    $expense = Expense::factory()->create(['expense_type_id' => $this->type->id, 'incurred_on' => today()->subDays(3)]);

    $this->actingAs(userWith([Permission::ManageCash]))->post(route('cash.link-expenses'), ['expense_ids' => [$expense->id]])->assertForbidden();
    $this->actingAs(userWith([Permission::ManageExpenses]))->post(route('cash.link-expenses'), ['expense_ids' => [$expense->id]])->assertForbidden();

    $this->actingAs($this->owner)->post(route('cash.link-expenses'), ['expense_ids' => [$expense->id]])->assertRedirect();

    expect($expense->refresh()->update(['paid_from_cash' => false]))->toBeFalse()
        ->and($expense->update(['amount' => 1]))->toBeFalse()
        ->and($expense->refresh()->paid_from_cash)->toBeTrue();
});
