<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Users\ResetUserSecurity;
use App\Actions\Users\SaveUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\UserRequest;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Product;
use App\Models\User;
use App\Models\Vertical;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $users = QueryBuilder::for(User::query()->with(['roles:id,name', 'department:id,name', 'designation:id,name']))
            ->allowedFilters(
                AllowedFilter::callback('search', function (Builder $query, mixed $value) {
                    $term = '%'.trim((string) $value).'%';
                    $query->where(fn (Builder $q) => $q
                        ->where('name', 'like', $term)
                        ->orWhere('emp_code', 'like', $term)
                        ->orWhere('email', 'like', $term));
                }),
                AllowedFilter::exact('is_active'),
                AllowedFilter::callback('role', fn (Builder $query, mixed $value) => $query
                    ->whereHas('roles', fn (Builder $roles) => $roles->where('name', (string) $value))),
            )
            ->allowedSorts('name', 'emp_code', 'last_login_at', 'created_at')
            ->defaultSort('name')
            ->paginate($request->integer('per_page', 25) > 0 ? min($request->integer('per_page', 25), 100) : 25)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->ulid,
                'emp_code' => $user->emp_code,
                'name' => $user->name,
                'email' => $user->email,
                'department' => $user->department?->name,
                'designation' => $user->designation?->name,
                'roles' => $user->roles->pluck('name'),
                'is_active' => $user->is_active,
                'two_factor' => $user->hasTwoFactorEnabled(),
                'last_login_at' => $user->last_login_at?->toIso8601String(),
            ]);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => [
                'search' => $request->input('filter.search', ''),
                'is_active' => $request->input('filter.is_active', ''),
                'role' => $request->input('filter.role', ''),
            ],
            'sort' => $request->input('sort', 'name'),
            'roles' => Role::query()->orderBy('name')->pluck('name'),
            'can' => [
                'create' => $request->user()->can('create', User::class),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('Admin/Users/Form', [
            'user' => null,
            'options' => $this->options(),
        ]);
    }

    public function store(UserRequest $request, SaveUser $saveUser): RedirectResponse
    {
        $temporaryPassword = $saveUser->handle($request->validated());

        return redirect()->route('users.index')
            ->with('success', 'User created.')
            ->with('temporary_password', [
                'emp_code' => $request->validated('emp_code'),
                'password' => $temporaryPassword,
            ]);
    }

    public function edit(Request $request, User $user): Response
    {
        $this->authorize('update', $user);

        $user->load(['roles:id,name', 'verticals:id', 'verticalTeams:id', 'products:id']);

        return Inertia::render('Admin/Users/Form', [
            'user' => [
                'id' => $user->ulid,
                'emp_code' => $user->emp_code,
                'name' => $user->name,
                'email' => $user->email,
                'mobile' => $user->mobile,
                'department_id' => $user->department_id,
                'designation_id' => $user->designation_id,
                'reporting_manager_id' => $user->reporting_manager_id,
                'date_of_joining' => $user->date_of_joining?->toDateString(),
                'is_authorised_signatory' => $user->is_authorised_signatory,
                'is_active' => $user->is_active,
                'two_factor' => $user->hasTwoFactorEnabled(),
                'roles' => $user->roles->pluck('name'),
                'vertical_ids' => $user->verticals->pluck('id'),
                'vertical_team_ids' => $user->verticalTeams->pluck('id'),
                'product_ids' => $user->products->pluck('id'),
            ],
            'options' => $this->options($user),
            'can' => [
                'toggle_active' => $request->user()->can('toggleActive', $user),
                'reset_security' => $request->user()->can('resetSecurity', $user),
            ],
        ]);
    }

    public function update(UserRequest $request, User $user, SaveUser $saveUser): RedirectResponse
    {
        $saveUser->handle($request->validated(), $user);

        return redirect()->route('users.edit', $user)->with('success', 'User updated.');
    }

    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        $this->authorize('toggleActive', $user);

        $user->forceFill(['is_active' => ! $user->is_active])->save();

        if (! $user->is_active) {
            $user->sessions()->delete();
        }

        return back()->with('success', $user->is_active ? 'User activated.' : 'User deactivated and signed out.');
    }

    public function resetPassword(User $user, ResetUserSecurity $reset): RedirectResponse
    {
        $this->authorize('resetSecurity', $user);

        $password = $reset->resetPassword($user);

        return back()
            ->with('success', 'Password reset. The user must change it at next sign-in.')
            ->with('temporary_password', ['emp_code' => $user->emp_code, 'password' => $password]);
    }

    public function resetTwoFactor(User $user, ResetUserSecurity $reset): RedirectResponse
    {
        $this->authorize('resetSecurity', $user);

        $reset->resetTwoFactor($user);

        return back()->with('success', '2FA reset. The user will set it up again at next sign-in.');
    }

    /**
     * @return array<string, mixed>
     */
    private function options(?User $editing = null): array
    {
        return [
            'roles' => Role::query()->orderBy('name')->pluck('name'),
            'departments' => Department::query()->active()->orderBy('name')->get(['id', 'name']),
            'designations' => Designation::query()->active()->orderBy('name')->get(['id', 'name']),
            'products' => Product::query()->active()->orderBy('name')->get(['id', 'code', 'name']),
            'verticals' => Vertical::query()->active()->orderBy('name')
                ->with(['teams' => fn ($q) => $q->active()->orderBy('name')->select(['id', 'vertical_id', 'name'])])
                ->get(['id', 'code', 'name']),
            'managers' => User::query()->active()
                ->when($editing, fn (Builder $q) => $q->whereKeyNot($editing->id))
                ->orderBy('name')
                ->get(['id', 'name', 'emp_code'])
                ->map(fn (User $u) => ['value' => $u->id, 'label' => $u->name, 'description' => $u->emp_code]),
        ];
    }
}
