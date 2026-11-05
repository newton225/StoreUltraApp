<?php

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('an entry keeps who did what to which record, before and after', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();

    $this->actingAs($user);
    AuditLog::record('client.renamed', $client, ['name' => 'Old'], ['name' => 'New']);

    $entry = AuditLog::sole();

    expect($entry->user_id)->toBe($user->id);
    expect($entry->action)->toBe('client.renamed');
    expect($entry->subject_type)->toBe(Client::class);
    expect($entry->subject_id)->toBe($client->id);
    expect($entry->before)->toBe(['name' => 'Old']);
    expect($entry->after)->toBe(['name' => 'New']);
    expect($entry->created_at)->not->toBeNull();
});

test('an entry can be recorded without a logged-in user', function () {
    AuditLog::record('orders.generated', Client::factory()->create());

    expect(AuditLog::sole()->user_id)->toBeNull();
});

test('an entry can neither be changed nor deleted', function () {
    AuditLog::record('client.created', Client::factory()->create(), [], ['name' => 'A']);
    $entry = AuditLog::sole();

    expect($entry->update(['action' => 'tampered']))->toBeFalse();
    expect($entry->delete())->toBeFalse();
    expect(AuditLog::sole()->action)->toBe('client.created');
});

test('only the owner reads the journal', function () {
    $this->get(route('audit.index'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())->get(route('audit.index'))->assertForbidden();
    $this->actingAs(User::factory()->agent()->create())->get(route('audit.index'))->assertForbidden();
});

test('the owner reads the journal, newest first', function () {
    $client = Client::factory()->create();
    AuditLog::record('first', $client);
    $this->travel(1)->minute();
    AuditLog::record('second', $client);

    $this->actingAs(User::factory()->owner()->create())
        ->get(route('audit.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('audit/index')
            ->where('entries.total', 2)
            ->where('entries.data.0.action', 'second'));
});
