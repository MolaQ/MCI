<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\Group;
use App\Models\User;
use App\Support\Appearance;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Settings::class);
        $this->app->singleton(Appearance::class);
    }

    public function boot(): void
    {
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        Relation::enforceMorphMap([
            'user' => User::class,
            'group' => Group::class,
        ]);

        // Administrators pass every permission check (policies still apply
        // structural rules such as "no self-suspension" through `before` = null).
        Gate::before(function (User $user, string $ability) {
            return str_contains($ability, '.') && $user->hasRole(Role::Admin->value) ? true : null;
        });

        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(10)->letters()->numbers()->uncompromised()
            : Password::min(8));

        // Lazy: settings are only read when a view actually uses them.
        View::share('appearance', $this->app->make(Appearance::class));
    }
}
