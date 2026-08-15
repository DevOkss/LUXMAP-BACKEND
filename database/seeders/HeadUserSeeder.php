<?php

namespace Database\Seeders;

use App\Enums\OrganizationType;
use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds one SSC Head and one Institute Head per institute.
 *
 * Rerunnable: users are keyed by email and pivots are attached idempotently.
 */
class HeadUserSeeder extends Seeder
{
    public function run(): void
    {
        $heads = [
            [
                'email' => 'ssc.head@soms.edu',
                'name' => 'Andres D. Villanueva',
                'role' => UserRole::SSC_HEAD,
                'position' => 'SSC Head',
                'organization_code' => 'SSC',
                'institute' => 'SSC',
                'program' => 'Supreme Student Council',
            ],
            [
                'email' => 'ics.head@soms.edu',
                'name' => 'Ramon G. Torres',
                'role' => UserRole::INSTITUTE_HEAD,
                'position' => 'ICS Head',
                'organization_code' => 'ICS-ISC',
                'institute' => 'ICS',
                'program' => 'Institute of Computer Studies',
            ],
            [
                'email' => 'ias.head@soms.edu',
                'name' => 'Lourdes M. Reyes',
                'role' => UserRole::INSTITUTE_HEAD,
                'position' => 'IAS Head',
                'organization_code' => 'IAS-ISC',
                'institute' => 'IAS',
                'program' => 'Institute of Arts and Sciences',
            ],
            [
                'email' => 'ihs.head@soms.edu',
                'name' => 'Cecilia A. Ramos',
                'role' => UserRole::INSTITUTE_HEAD,
                'position' => 'IHS Head',
                'organization_code' => 'IHS-ISC',
                'institute' => 'IHS',
                'program' => 'Institute of Health Sciences',
            ],
            [
                'email' => 'ibfs.head@soms.edu',
                'name' => 'Eduardo C. Bautista',
                'role' => UserRole::INSTITUTE_HEAD,
                'position' => 'IBFS Head',
                'organization_code' => 'IBFS-ISC',
                'institute' => 'IBFS',
                'program' => 'Institute of Business and Financial Services',
            ],
            [
                'email' => 'icje.head@soms.edu',
                'name' => 'Norman P. Aquino',
                'role' => UserRole::INSTITUTE_HEAD,
                'position' => 'ICJE Head',
                'organization_code' => 'ICJE-ISC',
                'institute' => 'ICJE',
                'program' => 'Institute of Criminal Justice Education',
            ],
        ];

        foreach ($heads as $data) {
            $organization = Organization::where('code', $data['organization_code'])->first();

            if (! $organization) {
                continue;
            }

            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => bcrypt('admin123'),
                    'is_enrolled' => true,
                ]
            );

            $user->organizations()->syncWithoutDetaching([$organization->id => [
                'role' => $data['role']->value,
                'position' => $data['position'],
                'assigned_at' => now(),
            ]]);
        }

        $this->seedProgramHeads();
    }

    /**
     * Seeds one Program Head (SRO Head) per SRO organization.
     */
    private function seedProgramHeads(): void
    {
        $sros = Organization::where('type', OrganizationType::SRO)->get();

        foreach ($sros as $sro) {
            $code = str_replace('-SRO', '', $sro->code);
            $email = strtolower($code).'.head@soms.edu';

            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => "Program Head - {$sro->name}",
                    'password' => bcrypt('admin123'),
                    'is_enrolled' => true,
                ]
            );

            $user->organizations()->syncWithoutDetaching([$sro->id => [
                'role' => UserRole::SRO_HEAD->value,
                'position' => 'Program Head',
                'assigned_at' => now(),
            ]]);
        }
    }
}
