<?php

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\User;
use App\Models\Zone;

beforeEach(fn () => config(['security.audit_reference_data' => true]));

test('creating, changing and deleting reference data leaves a trace with who did it', function () {
    $owner = User::factory()->owner()->create();
    $this->actingAs($owner);

    $zone = Zone::factory()->create(['name' => 'Timbe']);
    $zone->update(['name' => 'Timbé']);
    $zone->delete();

    $entries = AuditLog::query()->where('subject_type', Zone::class)->orderBy('id')->get();

    expect($entries->pluck('action')->all())->toBe(['zone.created', 'zone.updated', 'zone.deleted'])
        ->and($entries->pluck('user_id')->unique()->all())->toBe([$owner->id])
        ->and($entries[1]->before)->toBe(['name' => 'Timbe'])
        ->and($entries[1]->after)->toBe(['name' => 'Timbé']);
});

test('a change that modifies nothing leaves no trace', function () {
    $client = Client::factory()->create();
    $before = AuditLog::count();

    $client->update(['name' => $client->name]);

    expect(AuditLog::count())->toBe($before);
});

test('every audited action has a readable phrase in both languages', function () {
    $actions = AuditLog::query()->distinct()->pluck('action');
    Client::factory()->create();
    Zone::factory()->create();

    foreach (['fr', 'en'] as $locale) {
        $phrases = trans('orders.audit.actions', [], $locale);

        foreach (AuditLog::query()->distinct()->pluck('action')->merge($actions) as $action) {
            [$model, $verb] = explode('.', $action);

            expect($phrases[$model][$verb] ?? null)->not->toBeNull("{$action} has no phrase in {$locale}");
        }
    }
});
