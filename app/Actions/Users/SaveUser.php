<?php

namespace App\Actions\Users;

use App\Actions\Auth\SetUserPassword;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SaveUser
{
    public function __construct(private readonly SetUserPassword $setPassword) {}

    /**
     * Creates or updates a user with their org links and roles in one transaction.
     * On create, returns the generated temporary password (shown to the admin once).
     *
     * @param  array<string, mixed>  $data  validated UserRequest data
     */
    public function handle(array $data, ?User $user = null): ?string
    {
        return DB::transaction(function () use ($data, &$user) {
            $temporaryPassword = null;
            $user ??= new User;

            $user->fill([
                'emp_code' => $data['emp_code'],
                'name' => $data['name'],
                'email' => $data['email'],
                'mobile' => $data['mobile'] ?? null,
                'department_id' => $data['department_id'] ?? null,
                'designation_id' => $data['designation_id'] ?? null,
                'reporting_manager_id' => $data['reporting_manager_id'] ?? null,
                'date_of_joining' => $data['date_of_joining'] ?? null,
                'is_authorised_signatory' => (bool) ($data['is_authorised_signatory'] ?? false),
            ]);

            if (! $user->exists) {
                $temporaryPassword = Str::password(14);
                $this->setPassword->handle($user, $temporaryPassword, temporary: true);
            } else {
                $user->save();
            }

            $user->syncRoles($data['roles']);
            $user->verticals()->sync($data['vertical_ids'] ?? []);
            $user->verticalTeams()->sync($data['vertical_team_ids'] ?? []);
            $user->products()->sync($data['product_ids'] ?? []);

            return $temporaryPassword;
        });
    }
}
