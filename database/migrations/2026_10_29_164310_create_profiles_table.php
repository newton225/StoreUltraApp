<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Profiles: what each kind of user may do, as data the owner can change. The three profiles that
 * existed as fixed roles become the three system profiles, which cannot be changed or removed (a
 * different set of permissions is a new profile), every user is given the one of their role, and the
 * setting "employees see the profit" becomes a permission of the employee profile.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->nullable()->unique();
            $table->string('label_fr');
            $table->string('label_en');
            $table->boolean('restricted_to_zones')->default(false);
            $table->boolean('is_system')->default(false);
            $table->json('permissions');
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('profile_id')->nullable()->after('role')->constrained('profiles')->restrictOnDelete();
        });

        $employeeSeesProfit = DB::table('settings')->where('key', 'employees_see_profit')->value('value') === '1';
        $all = ['manage_orders', 'manage_purchases', 'manage_expenses', 'manage_catalog', 'view_profit'];
        $now = now();

        $profiles = [
            ['code' => 'owner', 'label_fr' => 'Responsable', 'label_en' => 'Owner', 'restricted_to_zones' => false, 'is_system' => true, 'permissions' => $all],
            ['code' => 'employee', 'label_fr' => 'Employé', 'label_en' => 'Employee', 'restricted_to_zones' => false, 'is_system' => true, 'permissions' => $employeeSeesProfit ? $all : array_slice($all, 0, 4)],
            ['code' => 'agent', 'label_fr' => 'Agent de terrain', 'label_en' => 'Field agent', 'restricted_to_zones' => true, 'is_system' => true, 'permissions' => []],
        ];

        foreach ($profiles as $profile) {
            $id = DB::table('profiles')->insertGetId([
                ...$profile,
                'permissions' => json_encode($profile['permissions']),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('users')->where('role', $profile['code'])->update(['profile_id' => $id]);
        }

        DB::table('settings')->where('key', 'employees_see_profit')->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('profile_id');
        });

        Schema::dropIfExists('profiles');
    }
};
