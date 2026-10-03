<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Only an explicit allow-list of user fields is shared, never the whole model.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        /** @var User|null $user */
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->ulid,
                    'emp_code' => $user->emp_code,
                    'name' => $user->name,
                    'email' => $user->email,
                    'theme' => $user->theme,
                ] : null,
                'permissions' => fn () => $user ? $this->permissionsFor($user) : [],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info' => fn () => $request->session()->get('info'),
                // Shown once to the admin right after creating a user or resetting a password.
                'temporary_password' => fn () => $request->session()->get('temporary_password'),
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private function permissionsFor(User $user): array
    {
        if ($user->hasRole(Permissions::superAdminRole())) {
            return Permissions::all();
        }

        return $user->getAllPermissions()->pluck('name')->values()->all();
    }
}
