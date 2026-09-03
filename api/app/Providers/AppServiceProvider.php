<?php

namespace App\Providers;

use App\Modules\ACL\Models\Role;
use App\Modules\ACL\Policies\RolePolicy;
use App\Modules\ApiToken\Models\ApiToken;
use App\Modules\ApiToken\Policies\ApiTokenPolicy;
use App\Modules\Assistant\Models\AssistantSetting;
use App\Modules\Assistant\Models\Conversation;
use App\Modules\Assistant\Policies\AssistantSettingPolicy;
use App\Modules\Assistant\Policies\ConversationPolicy;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Policies\InvoicePolicy;
use App\Modules\Billing\Policies\PlanPolicy;
use App\Modules\Billing\Policies\SubscriptionPolicy;
use App\Modules\Financial\Models\Category;
use App\Modules\Financial\Models\FinancialRecurrence;
use App\Modules\Financial\Models\Payable;
use App\Modules\Financial\Models\Supplier;
use App\Modules\Financial\Policies\CategoryPolicy;
use App\Modules\Financial\Policies\FinancialRecurrencePolicy;
use App\Modules\Financial\Policies\PayablePolicy;
use App\Modules\Financial\Policies\SupplierPolicy;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Policies\TenantPolicy;
use App\Modules\User\Models\User;
use App\Modules\User\Policies\UserPolicy;
use App\Modules\Webhook\Models\Webhook;
use App\Modules\Webhook\Policies\WebhookPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->configureRateLimiting();
        $this->configurePolicies();
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->getKey() ?: $request->ip());
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

    }

    private function configurePolicies(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Tenant::class, TenantPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Conversation::class, ConversationPolicy::class);
        Gate::policy(AssistantSetting::class, AssistantSettingPolicy::class);
        Gate::policy(ApiToken::class, ApiTokenPolicy::class);
        Gate::policy(Webhook::class, WebhookPolicy::class);
        Gate::policy(Plan::class, PlanPolicy::class);
        Gate::policy(Subscription::class, SubscriptionPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(Payable::class, PayablePolicy::class);
        Gate::policy(Supplier::class, SupplierPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(FinancialRecurrence::class, FinancialRecurrencePolicy::class);
    }
}
