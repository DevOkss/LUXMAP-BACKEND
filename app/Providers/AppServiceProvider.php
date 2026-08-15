<?php

namespace App\Providers;

use App\Models\Attendance;
use App\Models\Event as EventModel;
use App\Models\Fee;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\User;
use App\Policies\AttendancePolicy;
use App\Policies\SomEventPolicy;
use App\Policies\EventPolicy;
use App\Policies\FeePolicy;
use App\Policies\OrganizationPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\ReceiptPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Attendance::class, AttendancePolicy::class);
        Gate::policy(EventModel::class, SomEventPolicy::class);
        Gate::policy(Fee::class, FeePolicy::class);
        Gate::policy(Organization::class, OrganizationPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(Receipt::class, ReceiptPolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        Gate::before(fn (?User $user, string $ability) => $user?->isSuperAdmin() ? true : null);
    }
}
