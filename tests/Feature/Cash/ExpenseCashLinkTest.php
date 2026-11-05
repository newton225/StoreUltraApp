<?php

use App\Models\AuditLog;
use App\Models\CashMovement;
use App\Models\Client;
use App\Models\Expense;
use App\Models\ExpenseType;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->owner = User::factory()->owner()->create();
    $this->type = ExpenseType::factory()->create(['code' => 'transport', 'label_fr' => 'Transport', 'label_en' => 'Transport']);
    Order::factory()->for(Client::factory())->create(['kits_count' => 3, 'delivery_week' => '2026-07-13', 'status' => 'received']);
});

function paidExpensePayload(ExpenseType $type, array $overrides = []): array
{
    return [
        'expense_type_id' => $type->id,
        'amount' => 12000,
        'incurred_on' => today()->toDateString(),
        'scope' => 'week',
        'week' => '2026-07-13',
        ...$overrides,
    ];
}

test('a cost paid from the cash box records the matching cash movement out', function () {
    $this->actingAs($this->owner)->post(route('expenses.store'), paidExpensePayload($this->type, ['paid_from_cash' => '1']))->assertRedirect();

    $expense = Expense::query()->firstOrFail();
    $movement = CashMovement::query()->firstOrFail();

    expect($expense->paid_from_cash)->toBeTrue()
        ->and($movement->expense_id)->toBe($expense->id)
        ->and($movement->direction)->toBe('out')
        ->and($movement->amount)->toBe(12000)
        ->and($movement->recorded_by)->toBe($this->owner->id)
        ->and($movement->occurred_on->toDateString())->toBe(today()->toDateString())
        ->and($movement->label)->toContain('Transport');
});

test('a cost not paid from the cash box leaves the cash box alone', function () {
    $this->actingAs($this->owner)->post(route('expenses.store'), paidExpensePayload($this->type))->assertRedirect();

    expect(Expense::count())->toBe(1)->and(CashMovement::count())->toBe(0);
});

test('cancelling the cost cancels its cash movement and both are traced', function () {
    $this->actingAs($this->owner)->post(route('expenses.store'), paidExpensePayload($this->type, ['paid_from_cash' => '1']));
    $expense = Expense::query()->firstOrFail();

    $this->actingAs($this->owner)->post(route('expenses.cancel', $expense), ['reason' => 'Erreur de saisie'])->assertRedirect();

    $movement = CashMovement::query()->firstOrFail();

    expect($expense->refresh()->isCancelled())->toBeTrue()
        ->and($movement->isCancelled())->toBeTrue()
        ->and($movement->cancel_reason)->toBe('Erreur de saisie')
        ->and(AuditLog::where('action', 'cash_movement.cancelled')->count())->toBe(1)
        ->and(AuditLog::where('action', 'expense.cancelled')->count())->toBe(1);
});

test('a movement that pays a cost cannot be cancelled on its own', function () {
    $this->actingAs($this->owner)->post(route('expenses.store'), paidExpensePayload($this->type, ['paid_from_cash' => '1']));
    $movement = CashMovement::query()->firstOrFail();

    $this->actingAs($this->owner)->post(route('cash.cancel', $movement), ['reason' => 'Non'])->assertSessionHasErrors('reason');

    expect($movement->refresh()->isCancelled())->toBeFalse();
});
