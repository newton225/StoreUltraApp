<?php

use App\Actions\Stock\RecordStockMovement;
use App\Enums\Permission;
use App\Enums\PermissionAction;
use App\Enums\StockMovementType;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\ProductComponent;
use App\Models\StockMovement;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->owner = User::factory()->owner()->create(['password' => 'secret-password-1']);
    $this->bar = Product::factory()->create(['name' => 'Barre', 'pack_size' => 10]);
    app(RecordStockMovement::class)->handle($this->bar, StockMovementType::Receipt, 10, 1000);
    app(RecordStockMovement::class)->handle($this->bar, StockMovementType::Receipt, 10, 1200);
});

function stockAdjustment(array $overrides = []): array
{
    return ['quantity' => 5, 'unit_cost' => 1500, 'reason' => 'Inventaire', 'password' => 'secret-password-1', ...$overrides];
}

test('the stock page lists the products bought as they are with their quantity, cost and value', function () {
    $panel = Product::factory()->create(['name' => 'Panneau']);
    ProductComponent::factory()->create(['product_id' => $panel->id, 'component_product_id' => $this->bar->id]);

    $this->actingAs($this->owner)->get(route('stock.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('stock/index')
            ->where('totalValue', 22000)
            ->has('items', 1)
            ->where('items.0.name', 'Barre')
            ->where('items.0.on_hand', 20)
            ->where('items.0.average_cost', 1100)
            ->where('items.0.pack_size', 10));
});

test('a user without the purchases menu cannot open the stock', function () {
    $employee = withProfile(User::factory()->create(), [Permission::ManageClients]);

    $this->actingAs($employee)->get(route('stock.index'))->assertForbidden();
    $this->actingAs($employee)->get(route('stock.show', $this->bar))->assertForbidden();
});

test('a product page shows its movements, newest first', function () {
    $this->actingAs($this->owner)->get(route('stock.show', $this->bar))
        ->assertInertia(fn (Assert $page) => $page
            ->component('stock/show')
            ->where('levels.on_hand', 20)
            ->has('movements', 2)
            ->where('movements.0.unit_cost', 1200)
            ->where('canAdjust', true));
});

test('a stock correction needs the right, a valid password and a reason, and is traced', function () {
    $this->actingAs($this->owner)
        ->post(route('stock.adjust', $this->bar), stockAdjustment())
        ->assertSessionHasNoErrors();

    expect(StockMovement::where('type', 'adjustment')->count())->toBe(1)
        ->and(AuditLog::where('action', 'stock.adjusted')->count())->toBe(1);

    $this->post(route('stock.adjust', $this->bar), stockAdjustment(['password' => 'wrong']))->assertSessionHasErrors('password');
    $this->post(route('stock.adjust', $this->bar), stockAdjustment(['reason' => '']))->assertSessionHasErrors('reason');
    $this->post(route('stock.adjust', $this->bar), stockAdjustment(['quantity' => 0]))->assertSessionHasErrors('quantity');

    expect(StockMovement::where('type', 'adjustment')->count())->toBe(1);
});

test('a correction cannot make the stock negative and adding units needs a cost', function () {
    $this->actingAs($this->owner)
        ->post(route('stock.adjust', $this->bar), stockAdjustment(['quantity' => -21, 'unit_cost' => null]))
        ->assertSessionHasErrors('quantity');

    $this->post(route('stock.adjust', $this->bar), stockAdjustment(['quantity' => 3, 'unit_cost' => null]))
        ->assertSessionHasErrors('unit_cost');

    expect(StockMovement::where('type', 'adjustment')->count())->toBe(0);
});

test('someone with the purchases menu but without the correction right cannot correct the stock', function () {
    $employee = withProfile(User::factory()->create(['password' => 'secret-password-1']), [Permission::ManagePurchases]);

    $this->actingAs($employee)->get(route('stock.show', $this->bar))
        ->assertInertia(fn (Assert $page) => $page->where('canAdjust', false));

    $this->post(route('stock.adjust', $this->bar), stockAdjustment())->assertForbidden();

    expect(StockMovement::where('type', 'adjustment')->count())->toBe(0);
});

test('the correction right belongs to the purchases menu and is given to profiles that hold it', function () {
    expect(PermissionAction::StockAdjust->menu())->toBe(Permission::ManagePurchases)
        ->and(PermissionAction::values())->toContain('stock.adjust');
});
