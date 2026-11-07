<?php

use App\Enums\Permission;
use App\Enums\PermissionAction;
use App\Models\Client;
use App\Models\Profile;
use App\Models\User;
use App\Models\Zone;

function clientsUser(array $permissions): User
{
    $profile = Profile::factory()->create(['permissions' => $permissions]);

    return User::factory()->create(['profile_id' => $profile->id])->refresh();
}

it('opens the clients only to a profile that holds the menu', function () {
    $client = Client::factory()->create();

    $this->actingAs(clientsUser([]))->get(route('clients.index'))->assertForbidden();
    $this->actingAs(clientsUser([]))->get(route('clients.show', $client))->assertForbidden();
    $this->actingAs(clientsUser([Permission::ManageClients->value]))->get(route('clients.index'))->assertOk();
});

it('needs the action to register, edit, change the status and export', function () {
    $client = Client::factory()->create();
    $reader = clientsUser([Permission::ManageClients->value]);

    $this->actingAs($reader)->get(route('clients.create'))->assertForbidden();
    $this->actingAs($reader)->get(route('clients.edit', $client))->assertForbidden();
    $this->actingAs($reader)->patch(route('clients.status', $client), ['status' => 'suspended'])->assertForbidden();
    $this->actingAs($reader)->get(route('list-exports.show', ['view' => 'clients', 'format' => 'csv']))->assertNotFound();

    $writer = clientsUser([Permission::ManageClients->value, PermissionAction::ClientsCreate->value, PermissionAction::ClientsUpdate->value]);

    $this->actingAs($writer)->get(route('clients.create'))->assertOk();
    $this->actingAs($writer)->get(route('clients.edit', $client))->assertOk();
});

it('keeps the orders of a zone visible without the clients menu', function () {
    $zone = Zone::factory()->create();
    $client = Client::factory()->create(['zone_id' => $zone->id]);
    $user = clientsUser([]);

    expect($user->reachesClient($client))->toBeTrue();
});

it('gives the clients menu and its actions to every profile that existed before', function () {
    $migration = require database_path('migrations/2026_11_07_120000_give_clients_menu_to_existing_profiles.php');
    $profile = Profile::factory()->create(['permissions' => [Permission::ManageOrders->value]]);

    $migration->up();

    $permissions = $profile->refresh()->permissions;

    expect($permissions)->toContain('manage_orders', 'manage_clients', 'clients.create', 'clients.update', 'clients.change_status', 'clients.export');
});

it('opens deliveries, payments and documents only with their own menu', function () {
    $none = clientsUser([]);

    foreach (['deliveries.index', 'payments.index', 'payments.arrears', 'documents.index'] as $route) {
        $this->actingAs($none)->get(route($route))->assertForbidden();
    }

    $this->actingAs(clientsUser([Permission::ManageDeliveries->value]))->get(route('deliveries.index'))->assertOk();
    $this->actingAs(clientsUser([Permission::ManagePayments->value]))->get(route('payments.index'))->assertOk();
    $this->actingAs(clientsUser([Permission::ManageDocuments->value]))->get(route('documents.index'))->assertOk();
    $this->actingAs(clientsUser([Permission::ManageOrders->value]))->get(route('payments.index'))->assertForbidden();
});

it('gives the new menus to the profiles that could already open the screens', function () {
    $migration = require database_path('migrations/2026_11_07_130000_give_deliveries_payments_documents_menus.php');
    $withOrders = Profile::factory()->create(['permissions' => [Permission::ManageOrders->value]]);
    $without = Profile::factory()->create(['permissions' => []]);

    $migration->up();

    expect($withOrders->refresh()->permissions)->toContain('manage_deliveries', 'manage_payments', 'manage_documents', 'payments.export', 'documents.export')
        ->and($without->refresh()->permissions)->toContain('manage_deliveries')->not->toContain('manage_payments');
});
