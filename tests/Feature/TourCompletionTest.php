<?php

use App\Models\TourCompletion;
use App\Models\User;

test('finishing or skipping a tour is kept for the member, once', function () {
    $user = User::factory()->owner()->create();

    $this->actingAs($user)->post(route('tours.store', 'orders'))->assertRedirect();
    $this->actingAs($user)->post(route('tours.store', 'orders'))->assertRedirect();

    expect(TourCompletion::where('user_id', $user->id)->where('tour', 'orders')->count())->toBe(1);
});

test('a screen that has no tour is refused', function () {
    $user = User::factory()->owner()->create();

    $this->actingAs($user)->post(route('tours.store', 'unknown'))->assertNotFound();

    expect(TourCompletion::count())->toBe(0);
});

test('a visitor cannot note a tour', function () {
    $this->post(route('tours.store', 'orders'))->assertRedirect(route('login'));
});

test('the tours already seen are sent with every page, for this member only', function () {
    $user = User::factory()->owner()->create();
    $other = User::factory()->owner()->create();
    TourCompletion::factory()->create(['user_id' => $user->id, 'tour' => 'dashboard']);
    TourCompletion::factory()->create(['user_id' => $other->id, 'tour' => 'cash']);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('toursDone', ['dashboard']));
});

test('every screen with a tour has its texts in both languages', function () {
    foreach (['fr', 'en'] as $locale) {
        $texts = trans('tours', [], $locale);

        foreach (['dashboard', 'orders', 'order_create', 'order_show', 'cash', 'profits', 'users'] as $screen) {
            expect($texts[$screen])->toBeArray()->not->toBeEmpty();

            foreach ($texts[$screen] as $step) {
                expect($step['title'])->toBeString()->not->toBeEmpty()
                    ->and($step['body'])->toBeString()->not->toBeEmpty();
            }
        }
    }
});
