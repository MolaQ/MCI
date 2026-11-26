<?php

namespace App\Providers;

use App\Enums\Feature;
use App\Enums\Role;
use App\Models\Announcement;
use App\Models\Event;
use App\Models\Group;
use App\Models\Invite;
use App\Models\School;
use App\Models\Task;
use App\Models\TaxonomyTerm;
use App\Models\User;
use App\Models\WorkflowAction;
use App\Models\WorkflowRequest;
use App\Models\WorkflowTemplate;
use App\Support\Appearance;
use App\Support\CurrentSchool;
use App\Support\Features;
use App\Support\Push;
use App\Support\Settings;
use App\Support\Taxonomy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Minishlink\WebPush\WebPush;
use NotificationChannels\WebPush\WebPushChannel;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Settings::class);
        $this->app->singleton(Appearance::class);
        $this->app->scoped(Features::class);
        $this->app->scoped(Taxonomy::class);
        $this->app->scoped(CurrentSchool::class);
    }

    public function boot(): void
    {
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        Relation::enforceMorphMap([
            'user' => User::class,
            'group' => Group::class,
            'school' => School::class,
            'invite' => Invite::class,
            'announcement' => Announcement::class,
            'event' => Event::class,
            'taxonomy_term' => TaxonomyTerm::class,
            'task' => Task::class,
            'workflow_template' => WorkflowTemplate::class,
            'workflow_request' => WorkflowRequest::class,
            'workflow_action' => WorkflowAction::class,
        ]);

        // Site admins and school admins pass every *permission* check (dotted
        // names). Policy abilities (update, suspend…) still run, so structural
        // rules such as "no self-suspension" keep applying to them.
        Gate::before(function (User $user, string $ability) {
            if (! str_contains($ability, '.')) {
                return null;
            }

            return $user->is_site_admin || $user->hasRole(Role::Admin->value) ? true : null;
        });

        Gate::define('site-admin', fn (User $user) => $user->is_site_admin);

        // Invite links are public: keep brute force and sign-up spam in check.
        RateLimiter::for('join', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
        RateLimiter::for('join-register', fn (Request $request) => [
            Limit::perMinute(5)->by($request->ip()),
            Limit::perHour(30)->by($request->ip()),
        ]);

        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(10)->letters()->numbers()->uncompromised()
            : Password::min(8));

        // Lazy: settings are only read when a view actually uses them.
        View::share('appearance', $this->app->make(Appearance::class));

        // Web Push client that survives hosts without GMP/BCMath (see Push::client).
        $this->app->when(WebPushChannel::class)->needs(WebPush::class)->give(fn () => Push::client());

        // @feature('events') … @endfeature — for the active school.
        Blade::if('feature', fn (string $key) => app(Features::class)->enabled(Feature::from($key)));
    }
}
