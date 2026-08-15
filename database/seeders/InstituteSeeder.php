<?php

namespace Database\Seeders;

use App\Models\Institute;
use App\Models\Program;
use Illuminate\Database\Seeder;

class InstituteSeeder extends Seeder
{
    public function run(): void
    {
        $institutes = [
            [
                'code' => 'ICS',
                'name' => 'Institute of Computer Studies',
                'programs' => [
                    ['code' => 'BSCS', 'name' => 'Bachelor of Science in Computer Science'],
                ],
            ],
            [
                'code' => 'IAS',
                'name' => 'Institute of Arts and Sciences',
                'programs' => [
                    ['code' => 'AB English', 'name' => 'Bachelor of Arts in English Language'],
                    ['code' => 'AB PolSci', 'name' => 'Bachelor of Arts in Political Science'],
                    ['code' => 'AB Communication', 'name' => 'Bachelor of Arts in Communication'],
                ],
            ],
            [
                'code' => 'IHS',
                'name' => 'Institute of Health Sciences',
                'programs' => [
                    ['code' => 'BS Midwifery', 'name' => 'Bachelor of Science in Midwifery'],
                    ['code' => 'Dip. Midwifery', 'name' => 'Diploma in Midwifery'],
                ],
            ],
            [
                'code' => 'IBFS',
                'name' => 'Institute of Business and Financial Services',
                'programs' => [
                    ['code' => 'BSBA MM', 'name' => 'Bachelor of Science in Business Administration Major in Marketing Management'],
                    ['code' => 'BSOA', 'name' => 'Bachelor of Science in Office Administration'],
                    ['code' => 'BSBA HRM', 'name' => 'Bachelor of Science in Business Administration Major in Human Resource Management'],
                ],
            ],
            [
                'code' => 'ICJE',
                'name' => 'Institute of Criminal Justice Education',
                'programs' => [
                    ['code' => 'BSISM', 'name' => 'Bachelor of Science in Industrial Security Management'],
                    ['code' => 'BS Criminology', 'name' => 'Bachelor of Science in Criminology'],
                ],
            ],
        ];

        foreach ($institutes as $data) {
            $institute = Institute::firstOrCreate(
                ['code' => $data['code']],
                ['name' => $data['name']]
            );

            foreach ($data['programs'] as $program) {
                Program::firstOrCreate(
                    ['institute_id' => $institute->id, 'code' => $program['code']],
                    ['name' => $program['name']]
                );
            }
        }
    }
}
