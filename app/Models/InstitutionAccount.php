<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InstitutionAccount extends Model
{
    /** @use HasFactory<\Database\Factories\InstitutionAccountFactory> */
    use HasFactory;

    protected $fillable = [
        'stud_id',
        'password',
        'stud_cnum',
        'stud_fname',
        'stud_lname',
        'stud_mname',
        'stud_sex',
        'stud_year',
        'academic_year',
        'semester',
        'is_graduated',
        'is_enrolled',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'stud_year' => 'integer',
            'is_graduated' => 'boolean',
            'is_enrolled' => 'boolean',
        ];
    }
}
