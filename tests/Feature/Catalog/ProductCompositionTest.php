<?php

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\ProductComponent;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->panel = Product::factory()->create(['name' => 'Panneau']);
    $this->bar = Product::factory()->create(['name' => 'Barre']);
    $this->cement = Product::factory()->create(['name' => 'Ciment']);
});

function composition(array $lines): array
{
    return ['components' => array_map(fn (array $line): array => ['component_product_id' => $line[0]->id, 'quantity' => $line[1]], $lines)];
}

test('a product can be made of other products, with a quantity that may have decimals', function () {
    $this->put(route('products.components.update', $this->panel), composition([[$this->bar, '2,5'], [$this->cement, 1]]))
        ->assertSessionHasNoErrors();

    expect($this->panel->components()->count())->toBe(2)
        ->and($this->panel->components()->where('component_product_id', $this->bar->id)->value('quantity'))->toBe(2.5)
        ->and($this->panel->fresh()->isManufactured())->toBeTrue();
});

test('sending nothing makes it a plain product again, and every change is traced', function () {
    ProductComponent::factory()->create(['product_id' => $this->panel->id, 'component_product_id' => $this->bar->id]);

    $this->put(route('products.components.update', $this->panel), [])->assertSessionHasNoErrors();

    expect($this->panel->components()->count())->toBe(0)
        ->and(AuditLog::where('action', 'product_component.deleted')->count())->toBe(1);
});

test('a composition is refused when it breaks the one-level rule or is malformed', function (Closure $payload) {
    $manufactured = Product::factory()->create();
    ProductComponent::factory()->create(['product_id' => $manufactured->id, 'component_product_id' => $this->bar->id]);

    $this->put(route('products.components.update', $this->panel), $payload($this, $manufactured))->assertSessionHasErrors();

    expect($this->panel->components()->count())->toBe(0);
})->with([
    'itself' => [fn ($t) => composition([[$t->panel, 1]])],
    'a manufactured product' => [fn ($t, $manufactured) => composition([[$manufactured, 1]])],
    'an archived product' => [function ($t) {
        $t->cement->forceFill(['archived_at' => now()])->save();

        return composition([[$t->cement, 1]]);
    }],
    'a zero quantity' => [fn ($t) => composition([[$t->bar, 0]])],
    'a negative quantity' => [fn ($t) => composition([[$t->bar, -1]])],
    'twice the same component' => [fn ($t) => composition([[$t->bar, 1], [$t->bar, 2]])],
]);

test('a product that is already a component cannot become manufactured', function () {
    ProductComponent::factory()->create(['product_id' => $this->panel->id, 'component_product_id' => $this->bar->id]);

    $this->put(route('products.components.update', $this->bar), composition([[$this->cement, 1]]))
        ->assertSessionHasErrors('components');

    expect($this->bar->components()->count())->toBe(0);
});
