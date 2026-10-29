<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        foreach (config('permissions.abilities') as $ability => $scopes) {
            Gate::define($ability, function ($user) use ($ability) {
                $membership = request()->attributes->get('currentMembership');

                return $membership && $membership->user_id === $user->id
                    && app(\App\Services\WorkspaceAccess::class)->scopes($membership, $ability) !== [];
            });
            foreach ($scopes as $scope) {
                Gate::define($ability.'.'.$scope, function ($user) use ($ability, $scope) {
                    $membership = request()->attributes->get('currentMembership');
                    if (! $membership || $membership->user_id !== $user->id) {
                        return false;
                    }
                    $held = app(\App\Services\WorkspaceAccess::class)->scopes($membership, $ability);

                    return in_array($scope, $held, true) || in_array('organization', $held, true);
                });
            }
        }
    }
}
