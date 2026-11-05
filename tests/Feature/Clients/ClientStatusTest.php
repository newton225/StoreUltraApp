<?php

use App\Enums\ClientStatus;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\User;

test('a client without a commercial number can still be deactivated from its page', function () {
    $client = Client::factory()->create(['commercial_number' => null, 'status' => ClientStatus::Active]);
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)->patch(route('clients.status', $client), ['status' => 'suspended'])->assertRedirect();

    expect($client->refresh()->status)->toBe(ClientStatus::Suspended)
        ->and($client->commercial_number)->toBeNull();
});

test('a status change is written to the audit journal with who did it', function () {
    config(['security.audit_reference_data' => true]);
    $client = Client::factory()->create(['status' => ClientStatus::Active]);
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)->patch(route('clients.status', $client), ['status' => 'ended']);

    $entry = AuditLog::query()->where('action', 'client.updated')->latest('id')->firstOrFail();

    expect($entry->user_id)->toBe($owner->id)
        ->and($entry->before)->toBe(['status' => 'active'])
        ->and($entry->after)->toBe(['status' => 'ended']);
});

test('an unknown status is refused and a field agent cannot change a status', function () {
    $client = Client::factory()->create(['status' => ClientStatus::Active]);

    $this->actingAs(User::factory()->owner()->create())
        ->patch(route('clients.status', $client), ['status' => 'banned'])
        ->assertSessionHasErrors('status');

    $this->actingAs(User::factory()->agent()->create())
        ->patch(route('clients.status', $client), ['status' => 'suspended'])
        ->assertForbidden();

    expect($client->refresh()->status)->toBe(ClientStatus::Active);
});

test('a suspended client no longer gets generated orders', function () {
    $client = Client::factory()->create(['status' => ClientStatus::Active]);

    $this->actingAs(User::factory()->owner()->create())->patch(route('clients.status', $client), ['status' => 'suspended']);

    expect(Client::query()->where('status', ClientStatus::Active->value)->whereKey($client->id)->exists())->toBeFalse();
});

test('the client page offers the status actions to the office only', function () {
    $client = Client::factory()->create();

    $this->actingAs(User::factory()->owner()->create())->get(route('clients.show', $client))
        ->assertInertia(fn ($page) => $page->where('canChangeStatus', true)->has('statuses', 4));
});
