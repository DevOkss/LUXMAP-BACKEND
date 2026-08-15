<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeviceBinding;
use App\Models\Organization;
use App\Services\AccessScopeService;
use App\Services\DeviceBindingService;
use App\Services\EligibilityService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DeviceBindingController extends Controller
{
    public function __construct(
        private AccessScopeService $access,
        private EligibilityService $eligibility,
        private DeviceBindingService $deviceService,
        private NotificationService $notificationService,
    ) {}

    public function index(Request $request): Response
    {
        $query = DeviceBinding::with('user:id,name,student_number,email')
            ->when($request->input('q'), function ($q, $term) {
                $q->whereHas('user', function ($userQuery) use ($term) {
                    $userQuery
                        ->where('name', 'like', "%{$term}%")
                        ->orWhere('student_number', 'like', "%{$term}%");
                });
            });

        if (! $request->user()->isSuperAdmin()) {
            $query->whereIn('user_id', $this->scopedStudentIds($request->user()));
        }

        $bindings = $query->orderByDesc('bound_at')->paginate(20)->through(fn (DeviceBinding $binding) => [
            'id' => $binding->id,
            'user' => [
                'id' => $binding->user?->id,
                'name' => $binding->user?->name,
                'student_number' => $binding->user?->student_number,
                'email' => $binding->user?->email,
            ],
            'device_fingerprint' => $binding->device_fingerprint,
            'device_meta' => $binding->device_meta,
            'bound_at' => $binding->bound_at?->toDateTimeString(),
        ]);

        return Inertia::render('admin/device-bindings/Index', [
            'bindings' => $bindings,
            'filters' => ['q' => $request->input('q')],
        ]);
    }

    public function unbind(Request $request, DeviceBinding $binding): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $this->deviceService->unbind($binding->user, $data['reason'], $request->user());

        if ($binding->user) {
            $this->notificationService->notifyUser(
                $binding->user,
                'Device binding removed',
                'An administrator removed this account\'s device binding. Sign in again on your device to bind it.',
                ['type' => 'device_unbind', 'url' => '/security'],
            );
        }

        return redirect()->route('admin.device-bindings.index')
            ->with('success', 'Device binding removed and recorded in the audit log.');
    }

    /**
     * Student IDs visible to a non-super-admin via their managed organizations
     * (super admins see everything).
     *
     * @return array<int>
     */
    private function scopedStudentIds($user): array
    {
        $orgs = $this->access->scopeOrganizations($user);

        return $orgs
            ->reduce(function (Collection $carry, Organization $org) {
                return $carry->merge($this->eligibility->studentIds($org));
            }, collect())
            ->unique()
            ->values()
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}