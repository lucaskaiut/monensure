<?php

namespace App\Modules\Tenant\Providers;

use App\Modules\Tenant\Resolution\Contracts\TenantResolverInterface;
use App\Modules\Tenant\Resolution\Strategies\ApiTokenStrategy;
use App\Modules\Tenant\Resolution\Strategies\AuthenticatedUserStrategy;
use App\Modules\Tenant\Resolution\Strategies\RefererStrategy;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Resolution\TenantResolver;
use App\Modules\Tenant\Support\CurrentTenant;
use App\Modules\User\Models\User;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class TenantServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::bind('child', function (string $value): Tenant {
            $user = auth()->user();

            if (! $user instanceof User || ! $user->is_master) {
                throw (new ModelNotFoundException)->setModel(Tenant::class, [$value]);
            }

            return Tenant::query()
                ->where('uuid', $value)
                ->where('parent_id', $user->tenant_id)
                ->firstOrFail();
        });
    }

    public function register(): void
    {
        $this->app->scoped(CurrentTenant::class);

        $this->app->bind(TenantResolverInterface::class, TenantResolver::class);

        $this->app->bind(TenantResolver::class, function (Application $app): TenantResolver {
            return new TenantResolver(
                [
                    $app->make(AuthenticatedUserStrategy::class),
                    $app->make(ApiTokenStrategy::class),
                    $app->make(RefererStrategy::class),
                ],
                $app->make(CurrentTenant::class),
            );
        });
    }
}
