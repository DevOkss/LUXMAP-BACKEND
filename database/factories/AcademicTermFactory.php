<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class AcademicTermFactory extends Factory
{
    public function definition(): array
    {
        return [
            'academic_year' => '2026-2027',
            'semester' => '1st',
            'start_date' => '2026-08-01',
            'end_date' => '2026-12-20',
            'is_active' => true,
        ];
    }

    public function active(bool $active = true): static
    {
        return $this->state(fn () => ['is_active' => $active]);
    }
}