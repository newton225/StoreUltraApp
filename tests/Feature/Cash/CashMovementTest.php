<?php

use App\Models\AuditLog;
use App\Models\CashMovement;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function cashPayload(array $overrides = []): array
{
    return [
        'direction' => 'in',
        'amount' => 50000,
        'occurred_on' => today()->toDateString(),
        'label' => 'Apport du responsable',
        ...$overrides,
    ];
}

test('a movement is recorded with its reference, its author and a trace', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)->post(route('cash.store'), cashPayload(['counterparty' => 'M. Kone', 'document_reference' => 'BORD-12']))
        ->assertRedirect();

    $movement = CashMovement::query()->firstOrFail();

    expect($movement->reference)->toBe(sprintf('CAI-%04d', $movement->id))
        ->and($movement->recorded_by)->toBe($owner->id)
        ->and($movement->counterparty)->toBe('M. Kone');

    $entry = AuditLog::query()->where('action', 'cash_movement.recorded')->firstOrFail();

    expect($entry->user_id)->toBe($owner->id)->and($entry->after['amount'])->toBe(50000);
});

test('sending the same idempotency key twice records one movement', function () {
    $owner = User::factory()->owner()->create();
    $payload = cashPayload(['idempotency_key' => 'abc-123']);

    $this->actingAs($owner)->post(route('cash.store'), $payload);
    $this->actingAs($owner)->post(route('cash.store'), $payload);

    expect(CashMovement::count())->toBe(1);
});

test('the balance counts money in minus money out and ignores cancelled movements', function () {
    CashMovement::factory()->create(['amount' => 100000]);
    CashMovement::factory()->out()->create(['amount' => 30000]);
    CashMovement::factory()->create(['amount' => 999, 'cancelled_at' => now()]);

    $this->actingAs(User::factory()->owner()->create())
        ->get(route('cash.index'))
        ->assertInertia(fn (Assert $page) => $page->where('summary.in', 100000)
            ->where('summary.out', 30000)
            ->where('summary.closing', 70000));
});

test('the opening balance is what the cash box held before the period', function () {
    CashMovement::factory()->create(['amount' => 80000, 'occurred_on' => '2026-01-10']);
    CashMovement::factory()->out()->create(['amount' => 20000, 'occurred_on' => '2026-01-20']);
    CashMovement::factory()->create(['amount' => 5000, 'occurred_on' => '2026-02-05']);

    $this->actingAs(User::factory()->owner()->create())
        ->get(route('cash.index', ['from' => '2026-02-01', 'to' => '2026-02-28']))
        ->assertInertia(fn (Assert $page) => $page->where('summary.opening', 60000)
            ->where('summary.in', 5000)
            ->where('summary.closing', 65000));
});

test('a movement is cancelled with a reason and cannot be edited or deleted', function () {
    $owner = User::factory()->owner()->create();
    $movement = CashMovement::factory()->create();

    $this->actingAs($owner)->post(route('cash.cancel', $movement), ['reason' => 'Saisie en double'])->assertRedirect();

    expect($movement->refresh()->isCancelled())->toBeTrue()
        ->and($movement->cancel_reason)->toBe('Saisie en double')
        ->and(AuditLog::where('action', 'cash_movement.cancelled')->count())->toBe(1);

    $movement->update(['amount' => 1]);
    expect($movement->refresh()->amount)->toBe(10000);

    expect($movement->delete())->toBeFalse();
    expect(CashMovement::count())->toBe(1);

    $this->actingAs($owner)->post(route('cash.cancel', $movement), ['reason' => 'Encore'])->assertSessionHasErrors('reason');
});

test('the amount must be positive and the date not in the future', function () {
    $this->actingAs(User::factory()->owner()->create())
        ->post(route('cash.store'), cashPayload(['amount' => 0, 'occurred_on' => today()->addDay()->toDateString()]))
        ->assertSessionHasErrors(['amount', 'occurred_on']);
});

test('only someone who manages expenses reaches the cash box', function () {
    $this->actingAs(User::factory()->agent()->create())->get(route('cash.index'))->assertForbidden();
    $this->actingAs(User::factory()->agent()->create())->post(route('cash.store'), cashPayload())->assertForbidden();
});
