<?php

use App\Models\Client;
use App\Models\ClientMember;
use App\Models\Zone;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\InitialClientsSeeder;

test('the project workbook loads the sub-prefectures, the TPE and their managers', function () {
    $this->seed(InitialClientsSeeder::class);

    expect(Zone::count())->toBe(5)
        ->and(Client::count())->toBe(57)
        ->and(ClientMember::count())->toBe(57)
        ->and(Zone::pluck('name')->sort()->values()->all())->toBe(['Arikokaha', 'Fronan', 'Katiola', 'Niakaramadougou', 'Timbe'])
        ->and(Client::where('name', 'GMORY SERVICES')->firstOrFail()->members()->count())->toBe(1)
        ->and(Client::query()->pluck('phone')->unique()->count())->toBe(57)
        ->and(Client::query()->where('phone', '')->count())->toBe(0);
});

test('loading again does not duplicate anything', function () {
    $this->seed(InitialClientsSeeder::class);
    $this->seed(InitialClientsSeeder::class);

    expect(Client::count())->toBe(57)->and(ClientMember::count())->toBe(57)->and(Zone::count())->toBe(5);
});

test('a missing workbook is skipped without stopping the deployment', function () {
    config(['app.seed_tpe_file' => sys_get_temp_dir().'/absent-workbook.xlsx']);

    $this->seed(InitialClientsSeeder::class);

    expect(Client::count())->toBe(0);
});

test('the whole base data loads in one go, and again', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(Client::count())->toBe(57)->and(Zone::count())->toBe(5);
});
