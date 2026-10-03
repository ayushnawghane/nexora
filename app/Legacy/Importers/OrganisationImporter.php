<?php

namespace App\Legacy\Importers;

use App\Legacy\Importer;
use App\Legacy\Models\LegacyDepartment;
use App\Legacy\Models\LegacyDesignation;
use App\Legacy\Models\LegacyProduct;
use App\Legacy\Models\LegacyUser;
use App\Legacy\Models\LegacyVertical;
use App\Legacy\Models\LegacyVerticalTeam;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Product;
use App\Models\User;
use App\Models\Vertical;
use App\Models\VerticalTeam;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Departments, designations, products, verticals, vertical teams and users.
 *
 * Rules (see docs/PLAN.md, M6):
 *  - Deleted legacy rows are imported as inactive: old transactions and letters still point at them.
 *  - Users keep their legacy password hash but must change it, and set up 2FA, on first sign-in.
 *  - A user without a usable employee code gets "L{legacy id}" and is imported inactive.
 *  - Emails must be unique: a repeated or missing email stays with the first active user; the others
 *    get an undeliverable placeholder (…@legacy.invalid) until an administrator fixes it.
 */
class OrganisationImporter extends Importer
{
    public function area(): string
    {
        return 'organisation';
    }

    public function description(): string
    {
        return 'Departments, designations, products, verticals, vertical teams and users';
    }

    protected function import(): void
    {
        $this->departments();
        $this->designations();
        $this->products();
        $this->verticals();
        $teams = $this->verticalTeams();
        $users = $this->users();
        $this->links($teams, $users);
    }

    private function departments(): void
    {
        foreach (LegacyDepartment::query()->orderBy('id')->get() as $row) {
            $this->report->read('departments');
            $name = Str::limit((string) $row->text('name'), 100, '');
            $this->claim(Department::class, $row->id, 'name', $name);
            // Legacy abbreviations don't match the departments (e.g. "acc" for Business Development), so no code is carried over.
            $this->upsert(Department::class, $row->id, ['name' => $name, 'is_active' => $row->isLegacyActive()], 'departments');
        }
    }

    private function designations(): void
    {
        foreach (LegacyDesignation::query()->orderBy('id')->get() as $row) {
            $this->report->read('designations');
            $name = Str::limit((string) $row->text('name'), 100, '');
            $this->claim(Designation::class, $row->id, 'name', $name);
            $this->upsert(Designation::class, $row->id, ['name' => $name, 'is_active' => $row->isLegacyActive()], 'designations');
        }
    }

    private function products(): void
    {
        $codes = [];
        foreach (LegacyProduct::query()->orderBy('id')->get() as $row) {
            $this->report->read('products');
            $code = Str::upper((string) preg_replace('/[^A-Za-z0-9&\-]/', '', (string) $row->text('code')));
            if ($code === '' || isset($codes[$code])) {
                $fallback = ($code === '' ? 'P' : $code).'-'.$row->id;
                $this->report->warn('products', $row->id, "Code \"{$row->text('code')}\" is ".($code === '' ? 'empty' : 'used by another product')."; imported as {$fallback}.");
                $code = $fallback;
            }
            $codes[$code] = true;

            $this->claim(Product::class, $row->id, 'code', $code);
            $this->upsert(Product::class, $row->id, [
                'code' => $code,
                'name' => Str::limit((string) $row->text('name'), 150, ''),
                'is_active' => $row->isLegacyActive(),
            ], 'products');
        }
    }

    private function verticals(): void
    {
        foreach (LegacyVertical::query()->orderBy('id')->get() as $row) {
            $this->report->read('verticals');
            $this->claim(Vertical::class, $row->id, 'code', Str::upper((string) $row->text('vertical_code')));
            $vertical = $this->upsert(Vertical::class, $row->id, [
                'code' => Str::upper((string) $row->text('vertical_code')),
                'name' => (string) $row->text('vertical_name'),
                'is_active' => $row->isLegacyActive(),
            ], 'verticals');
            $vertical->products()->sync($this->ids(Product::class, $row->idList('product_id')));
        }
    }

    /**
     * @return array<int, int|null> legacy team id => legacy signatory user id
     */
    private function verticalTeams(): array
    {
        $rows = LegacyVerticalTeam::query()->orderBy('id')->get();
        $nameCounts = $rows->countBy(fn (LegacyVerticalTeam $t) => Str::lower((string) $t->text('team_name')));
        $emails = [];
        $signatories = [];

        foreach ($rows as $row) {
            $this->report->read('vertical teams');
            $verticalId = $this->idFor(Vertical::class, $row->ref('vertical_id'));
            if ($verticalId === null) {
                $this->report->reject('vertical teams', $row->id, "Its vertical (legacy {$row->ref('vertical_id')}) wasn't imported.");

                continue;
            }

            $name = (string) $row->text('team_name');
            if ($nameCounts[Str::lower($name)] > 1) {
                $name .= ' ('.Vertical::query()->whereKey($verticalId)->value('name').')';
            }

            $email = Str::lower((string) $row->text('team_email')) ?: null;
            if ($email !== null && isset($emails[$email])) {
                $this->report->warn('vertical teams', $row->id, "Team email {$email} is shared with another team; left empty here.");
                $email = null;
            }
            $emails[(string) $email] = true;

            $this->claim(VerticalTeam::class, $row->id, 'name', Str::limit($name, 150, ''));
            $team = $this->upsert(VerticalTeam::class, $row->id, [
                'vertical_id' => $verticalId,
                'name' => Str::limit($name, 150, ''),
                'email' => $email,
                'legal_email' => $row->text('legal_email'),
                'compliance_email' => $row->text('compliance_email'),
                'billing_email' => $row->text('billing_email'),
                'is_active' => $row->isLegacyActive(),
            ], 'vertical teams');
            $team->products()->sync($this->ids(Product::class, $row->idList('product_id')));
            $signatories[$row->id] = $row->ref('authorised_sign_id');
        }

        return $signatories;
    }

    /**
     * @return array<int, int|null> legacy user id => legacy reporting manager id
     */
    private function users(): array
    {
        // Active users first, so they keep a shared email or code ahead of old accounts.
        $rows = LegacyUser::query()->orderBy('is_deleted')->orderByDesc('is_active')->orderBy('id')->get();
        $codes = [];
        $emails = [];
        $managers = [];

        foreach ($rows as $row) {
            $this->report->read('users');

            $code = Str::upper((string) $row->text('emp_code'));
            $usable = $code !== '' && preg_match('/^[A-Z0-9\-]{1,20}$/', $code) && ! isset($codes[$code]);
            if (! $usable) {
                if ($code !== '') {
                    $this->report->warn('users', $row->id, "Employee code \"{$code}\" is invalid or repeated.");
                }
                $code = "L{$row->id}";
            }
            $codes[$code] = true;
            $this->claim(User::class, $row->id, 'emp_code', $code);

            $email = Str::lower((string) $row->text('email'));
            $takenInNexora = $email !== '' && User::query()->withTrashed()->where('email', $email)
                ->where(fn ($q) => $q->whereNull('legacy_id')->orWhere('legacy_id', '!=', $row->id))->exists();
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || isset($emails[$email]) || $takenInNexora) {
                $this->report->warn('users', $row->id, ($email === '' ? 'No email' : "Email {$email} is invalid or used by another user").'; a placeholder was set.');
                $email = "user-{$row->id}@legacy.invalid";
            }
            $emails[$email] = true;

            $active = $usable && $row->isLegacyActive();

            $user = $this->upsert(User::class, $row->id, [
                'emp_code' => $code,
                'name' => Str::limit((string) ($row->text('name') ?? $code), 150, ''),
                'email' => $email,
                'mobile' => Str::limit((string) $row->text('mobile'), 20, '') ?: null,
                'department_id' => $this->idFor(Department::class, $row->ref('department_id')),
                'designation_id' => $this->idFor(Designation::class, $row->ref('designation_id')),
                'is_authorised_signatory' => (bool) $row->getAttribute('is_authorised_signatory'),
                'date_of_joining' => $row->date('date_of_joining'),
                'is_active' => $active,
                'last_login_at' => $row->date('last_login_date'),
                'last_login_ip' => Str::limit((string) $row->text('last_login_ip'), 45, '') ?: null,
            ] + $this->credentials($row), 'users');

            $user->verticals()->sync($this->ids(Vertical::class, array_filter([$row->ref('vertical_id')])));
            $user->verticalTeams()->sync($this->ids(VerticalTeam::class, array_filter([$row->ref('vertical_team_id')])));
            $user->products()->sync($this->ids(Product::class, $row->idList('product_map_id')));
            $this->signature($row, $user);
            $managers[$row->id] = $row->ref('rm_id');
        }

        return $managers;
    }

    /**
     * Only set when the user is first imported: a later run must not undo a password the user has
     * since changed in Nexora.
     *
     * @return array<string, mixed>
     */
    private function credentials(LegacyUser $row): array
    {
        if (User::query()->withTrashed()->where('legacy_id', $row->id)->whereNotNull('password_changed_at')->exists()) {
            return [];
        }

        $hash = (string) $row->getAttribute('password');
        // @phpstan-ignore argument.type (Laravel's facade stub types this parameter wrongly; it takes the hash string)
        if (! Hash::isHashed($hash) || ! Hash::verifyConfiguration($hash)) {
            $this->report->warn('users', $row->id, 'The legacy password hash can\'t be used; an administrator must reset this password.');
            $hash = Hash::make(Str::password(32));
        }

        return [
            'password' => $hash,
            'must_change_password' => true,
            'password_changed_at' => null,
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    /** Legacy keeps signatures as data URIs in the users table; Nexora keeps them as private files. */
    private function signature(LegacyUser $row, User $user): void
    {
        $uri = (string) $row->getAttribute('signatory_image');
        if (! preg_match('#^data:image/(png|jpe?g);base64,\s*(.+)$#s', $uri, $m)) {
            return;
        }
        $bytes = base64_decode(preg_replace('/\s+/', '', $m[2]) ?? '', true);
        if ($bytes === false || $bytes === '') {
            $this->report->warn('users', $row->id, 'The signature image could not be read.');

            return;
        }

        $path = "signatures/{$user->ulid}.".($m[1] === 'png' ? 'png' : 'jpg');
        if ($this->report->dryRun) {
            return; // files aren't part of the rolled-back transaction, so a dry run doesn't write them
        }
        Storage::disk('local')->put($path, $bytes);
        if ($user->signature_path !== $path) {
            $user->forceFill(['signature_path' => $path])->save();
        }
    }

    /**
     * Second pass, once every user exists: team signatories and reporting managers.
     *
     * @param  array<int, int|null>  $signatories
     * @param  array<int, int|null>  $managers
     */
    private function links(array $signatories, array $managers): void
    {
        foreach ($signatories as $legacyTeam => $legacyUser) {
            $team = VerticalTeam::query()->withTrashed()->where('legacy_id', $legacyTeam)->first();
            $team?->forceFill(['signatory_id' => $this->idFor(User::class, $legacyUser)])->save();
        }

        foreach ($managers as $legacyUser => $legacyManager) {
            if ($legacyManager === null || $legacyManager === $legacyUser) {
                continue;
            }
            $user = User::query()->withTrashed()->where('legacy_id', $legacyUser)->first();
            $user?->forceFill(['reporting_manager_id' => $this->idFor(User::class, $legacyManager)])->save();
        }
    }

    /**
     * Nexora ids for a list of legacy ids, skipping any that weren't imported.
     *
     * @param  class-string<Model>  $class
     * @param  list<int>  $legacyIds
     * @return list<int>
     */
    private function ids(string $class, array $legacyIds): array
    {
        return array_values(array_filter(array_map(fn (int $id) => $this->idFor($class, $id), $legacyIds)));
    }
}
