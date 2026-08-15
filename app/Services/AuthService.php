<?php

namespace App\Services;

use App\Models\Institute;
use App\Models\Program;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(
        private WorkspaceService $workspaceService,
        private InstitutionApiService $institutionApiService,
        private AcademicTermService $academicTermService
    ) {}

    public function attempt(array $credentials, ?string $deviceFingerprint = null): array
    {
        $login = $credentials['student_number'];
        $password = $credentials['password'];

        $user = User::where('student_number', $login)
            ->orWhere('email', $login)
            ->first();

        if ($user) {
            $this->authenticateLocalUser($user, $password);
        } else {
            $user = $this->authenticateInstitutionStudent($login, $password);
        }

        // Refresh profile + enrollment snapshot from the institution API
        // (source of truth for enrollment/graduation/year/term). Best effort:
        // a successful local login is never rolled back if the API is down.
        $this->syncFromInstitution($user, $password);

        $token = $this->issueToken($user, $credentials['device_name'] ?? 'soms-api', $deviceFingerprint);

        $workspaces = $this->workspaceService->getAvailableWorkspaces($user);

        return [
            'token' => $token,
            'user' => $user,
            'workspaces' => $workspaces,
        ];
    }

    /**
     * Create an API token for the user. When a device fingerprint is supplied,
     * any previous token issued to that same device is revoked so each device
     * holds at most one active session.
     */
    private function issueToken(User $user, string $name, ?string $deviceFingerprint = null): string
    {
        if ($deviceFingerprint) {
            $user->tokens()
                ->where('device_fingerprint', $deviceFingerprint)
                ->delete();
        }

        $token = $user->createToken($name);

        if ($deviceFingerprint) {
            $token->accessToken->forceFill(['device_fingerprint' => $deviceFingerprint])->save();
        }

        return $token->plainTextToken;
    }

    /**
     * Refresh an authenticated user's profile + enrollment from the institution
     * API using their stored (encrypted) institution password.
     *
     * @return array{user: User, synced: bool}
     */
    public function refreshUser(User $user): array
    {
        $password = $user->institution_password_enc
            ? Crypt::decryptString($user->institution_password_enc)
            : null;

        if (!$password) {
            return ['user' => $user, 'synced' => false];
        }

        $student = $this->institutionApiService->authenticate($user->student_number, $password);

        if (!$student) {
            return ['user' => $user, 'synced' => false];
        }

        if ((int) ($student['isGraduated'] ?? 0) === 1) {
            $this->markGraduated($user, $student);

            return ['user' => $user->fresh(), 'synced' => true];
        }

        $this->applyInstitutionSync($user, $student, $password);

        return ['user' => $user->fresh(), 'synced' => true];
    }

    private function authenticateLocalUser(User $user, string $password): void
    {
        if ($user->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'student_number' => ['Super admins must sign in via the Admin Portal.'],
            ]);
        }

        if (Hash::check($password, $user->password)) {
            return;
        }

        // The institution password may have changed since the user was
        // registered locally. Fall back to the institution API first.
        $this->reauthenticateWithInstitution($user, $password);
    }

    private function authenticateInstitutionStudent(string $studentNumber, string $password): User
    {
        $student = $this->institutionApiService->authenticate($studentNumber, $password);

        if (!$student) {
            throw ValidationException::withMessages([
                'student_number' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ((int) ($student['isGraduated'] ?? 0) === 1) {
            throw ValidationException::withMessages([
                'student_number' => ['Graduated students cannot access the portal.'],
            ]);
        }

        return User::create([
            'student_number' => $student['StudID'],
            'name' => $this->buildStudentName($student),
            'password' => Hash::make($password),
            'institution_password_enc' => Crypt::encryptString($password),
            'is_enrolled' => false,
        ]);
    }

    /**
     * The institution password may have changed. Verify against the API and
     * update the stored hash; the profile/enrollment sync happens afterwards
     * in syncFromInstitution().
     */
    private function reauthenticateWithInstitution(User $user, string $password): void
    {
        $student = $this->institutionApiService->authenticate($user->student_number, $password);

        if (!$student) {
            throw ValidationException::withMessages([
                'student_number' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user->update(['password' => Hash::make($password)]);
    }

    /**
     * Best-effort profile + enrollment refresh from the institution API at
     * login. Blocks access for graduated or un-enrolled students.
     */
    private function syncFromInstitution(User $user, string $password): void
    {
        $student = $this->institutionApiService->authenticate($user->student_number, $password);

        if (!$student) {
            // Institution API unavailable: fail closed for non-enrolled users,
            // otherwise keep the last-known-good state.
            if (!$user->is_enrolled) {
                throw ValidationException::withMessages([
                    'student_number' => ['This account is not enrolled.'],
                ]);
            }

            return;
        }

        if ((int) ($student['isGraduated'] ?? 0) === 1) {
            $this->markGraduated($user, $student);

            throw ValidationException::withMessages([
                'student_number' => ['Graduated students cannot access the portal.'],
            ]);
        }

        $this->applyInstitutionSync($user, $student, $password);

        if (!$user->is_enrolled) {
            throw ValidationException::withMessages([
                'student_number' => ['This account is not enrolled.'],
            ]);
        }
    }

    /**
     * Apply the institution API payload to the student:
     *  - find/create the academic term and create/refresh that term's
     *    enrollment snapshot (never touching other terms' records),
     *  - mirror the current snapshot onto the users table.
     */
    private function applyInstitutionSync(User $user, array $student, string $password): void
    {
        $isEnrolled = (int) ($student['isEnrolled'] ?? 1) === 1;
        $yearLevel = $student['StudYear'] ?? $user->year_level;

        $term = $this->academicTermService->fromPayload(
            $student['academic_year'] ?? null,
            $student['semester'] ?? null
        );

        if ($term) {
            $this->academicTermService->syncEnrollment($user, $term, [
                'is_enrolled' => $isEnrolled,
                'year_level' => $yearLevel,
            ]);
        }

        $user->update([
            'name' => $this->buildStudentName($student),
            'phone' => $student['StudCNum'] ?? $user->phone,
            'year_level' => $yearLevel,
            'sex' => $student['StudSex'] ?? $user->sex,
            'is_enrolled' => $isEnrolled,
            'institution_password_enc' => Crypt::encryptString($password),
        ]);
    }

    private function markGraduated(User $user, array $student): void
    {
        $term = $this->academicTermService->fromPayload(
            $student['academic_year'] ?? null,
            $student['semester'] ?? null
        );

        if ($term) {
            $this->academicTermService->syncEnrollment($user, $term, [
                'is_enrolled' => false,
            ]);
        }

        $user->update(['is_enrolled' => false]);
    }

    private function buildStudentName(array $student): string
    {
        $firstName = $student['StudFName'] ?? '';
        $lastName = $student['StudLName'] ?? '';
        $middleName = $student['StudMName'] ?? null;

        $middleInitial = $middleName
            ? ' '.strtoupper(substr(trim($middleName), 0, 1)).'.'
            : '';

        return trim($firstName.$middleInitial.' '.$lastName);
    }

    public function register(array $data, ?string $deviceFingerprint = null): array
    {
        $instituteId = isset($data['institute'])
            ? Institute::where('code', $data['institute'])->value('id')
            : null;
        $programId = isset($data['program'])
            ? Program::where('code', $data['program'])->value('id')
            : null;

        $user = User::create([
            'student_number' => $data['student_number'],
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'phone' => $data['phone'] ?? null,
            'institute_id' => $instituteId,
            'program_id' => $programId,
            'year_level' => $data['year_level'] ?? null,
            'is_enrolled' => true,
        ]);

        $term = $this->academicTermService->current();
        if ($term) {
            $this->academicTermService->syncEnrollment($user, $term, [
                'institute_id' => $instituteId,
                'program_id' => $programId,
                'year_level' => $data['year_level'] ?? null,
                'is_enrolled' => true,
            ]);
        }

        $token = $this->issueToken($user, 'soms-api', $deviceFingerprint);

        $workspaces = $this->workspaceService->getAvailableWorkspaces($user);

        return [
            'token' => $token,
            'user' => $user,
            'workspaces' => $workspaces,
        ];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }
}