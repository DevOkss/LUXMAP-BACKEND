<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            InstituteSeeder::class,
            OrganizationSeeder::class,
            InstitutionAccountSeeder::class,
            HeadUserSeeder::class,
            AcademicTermSeeder::class,
        ]);

        $ssc = Organization::where('code', 'SSC')->first();

        $admin = User::firstOrCreate(
            ['email' => 'admin@soms.edu'],
            [
                'name' => 'Super Admin',
                'password' => bcrypt('admin123'),
                'is_enrolled' => true,
            ]
        );

        if (! $admin->organizations()->where('organization_id', $ssc->id)->exists()) {
            $admin->organizations()->attach($ssc->id, [
                'role' => UserRole::SUPER_ADMIN->value,
                'position' => 'System Administrator',
                'assigned_at' => now(),
            ]);
        }
    }
}

