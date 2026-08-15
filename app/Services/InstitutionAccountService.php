<?php

namespace App\Services;

use App\Models\InstitutionAccount;
use Illuminate\Support\Facades\Hash;

class InstitutionAccountService
{
    /**
     * Authenticate a student against the local institution account records.
     *
     * Returns the student payload on success, or null on failure.
     */
    public function authenticate(string $studId, string $password): ?array
    {
        $account = InstitutionAccount::where('stud_id', $studId)->first();

        if (!$account || !Hash::check($password, $account->password)) {
            return null;
        }

        return [
            'StudID' => $account->stud_id,
            'StudCNum' => $account->stud_cnum,
            'StudFName' => $account->stud_fname,
            'StudLName' => $account->stud_lname,
            'StudMName' => $account->stud_mname,
            'StudSex' => $account->stud_sex,
            'StudYear' => $account->stud_year,
            'academic_year' => $account->academic_year,
            'semester' => $account->semester,
            'isGraduated' => (int) $account->is_graduated,
            'isEnrolled' => (int) $account->is_enrolled,
        ];
    }
}
