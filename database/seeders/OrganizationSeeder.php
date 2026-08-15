<?php

namespace Database\Seeders;

use App\Enums\OrganizationType;
use App\Models\Institute;
use App\Models\Organization;
use App\Models\Program;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $ssc = Organization::firstOrCreate(
            ['code' => 'SSC'],
            [
                'name' => 'Supreme Student Council',
                'type' => OrganizationType::SSC,
                'description' => 'The highest student governing body of Tangub City Global College.',
                'config' => json_encode(['penalty_amount' => 50]),
                'is_active' => true,
            ]
        );

        $this->createInstitute($ssc, 'ICS', 'Institute of Computer Studies', [
            ['BSCS', 'BSCS - Student Organization'],
        ]);

        $this->createInstitute($ssc, 'IAS', 'Institute of Arts and Sciences', [
            ['AB English', 'AB English - Student Organization'],
            ['AB PolSci', 'AB PolSci - Student Organization'],
            ['AB Communication', 'AB Communication - Student Organization'],
        ]);

        $this->createInstitute($ssc, 'IHS', 'Institute of Health Sciences', [
            ['BS Midwifery', 'BS Midwifery - Student Organization'],
            ['Dip. Midwifery', 'Dip. Midwifery - Student Organization'],
        ]);

        $this->createInstitute($ssc, 'IBFS', 'Institute of Business and Financial Services', [
            ['BSBA MM', 'BSBA MM - Student Organization'],
            ['BSOA', 'BSOA - Student Organization'],
            ['BSBA HRM', 'BSBA HRM - Student Organization'],
        ]);

        $this->createInstitute($ssc, 'ICJE', 'Institute of Criminal Justice Education', [
            ['BSISM', 'BSISM - Student Organization'],
            ['BS Criminology', 'BS Criminology - Student Organization'],
        ]);
    }

    private function createInstitute(
        Organization $ssc,
        string $instituteCode,
        string $instituteName,
        array $programs
    ): void {
        $institute = Institute::where('code', $instituteCode)->first();

        $isc = Organization::firstOrCreate(
            ['code' => "{$instituteCode}-ISC"],
            [
                'parent_id' => $ssc->id,
                'institute_id' => $institute?->id,
                'name' => $instituteName,
                'type' => OrganizationType::ISC,
                'description' => "Institute Student Council for the {$instituteName}.",
                'config' => json_encode(['penalty_amount' => 50]),
                'is_active' => true,
            ]
        );

        if ($institute && !$isc->institute_id) {
            $isc->update(['institute_id' => $institute->id]);
        }

        foreach ($programs as [$code, $name]) {
            $program = Program::where('code', $code)->where('institute_id', $institute?->id)->first();

            $sro = Organization::firstOrCreate(
                ['code' => "{$code}-SRO"],
                [
                    'parent_id' => $isc->id,
                    'program_id' => $program?->id,
                    'name' => $name,
                    'type' => OrganizationType::SRO,
                    'description' => "Student organization for {$name}.",
                    'config' => json_encode(['penalty_amount' => 50]),
                    'is_active' => true,
                ]
            );

            if ($program && !$sro->program_id) {
                $sro->update(['program_id' => $program->id]);
            }
        }
    }
}
