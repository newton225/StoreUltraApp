<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with the base data of the business: shops, products and prices, the
     * kit, payment methods, expense and attachment types, then the sub-prefectures and TPE clients. Every
     * seeder can be run again without duplicating anything, and none disables the model events, so each
     * record loaded is written to the audit journal like any other change (playbook SEC-161). The owner is
     * created with `php artisan app:create-owner`.
     */
    public function run(): void
    {
        $this->call([
            InitialCatalogSeeder::class,
            InitialKitSeeder::class,
            InitialPaymentMethodsSeeder::class,
            InitialExpenseTypesSeeder::class,
            InitialAttachmentTypesSeeder::class,
            InitialClientsSeeder::class,
        ]);
    }
}
