<?php

use App\Legacy\Models\LegacyUser;
use App\Models\Department;
use App\Models\Product;
use App\Models\User;
use App\Models\Vertical;
use App\Models\VerticalTeam;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Storage::fake('local');
    $this->seed(PermissionSeeder::class);
    Product::query()->create(['code' => 'DEB', 'name' => 'Debenture Trustee']); // seeded before go-live
    legacySchema();

    legacyRows('master_department', [
        ['id' => 1, 'name' => 'Management', 'abbreviation' => 'mgmt'],
        ['id' => 3, 'name' => "Business Development\t", 'abbreviation' => 'acc'],
        ['id' => 9, 'name' => 'Securitization', 'abbreviation' => 'bd', 'is_active' => 0, 'is_deleted' => 1],
    ]);
    legacyRows('master_designation', [['id' => 14, 'name' => 'Manager']]);
    legacyRows('master_product', [
        ['id' => 4, 'name' => 'Debenture Trustee', 'code' => 'DEB'],
        ['id' => 15, 'name' => 'EWT Trustee', 'code' => 'EWT'],
        ['id' => 37, 'name' => 'Employee Welfare Trust', 'code' => 'EWT'],
        ['id' => 49, 'name' => 'ReIT', 'code' => 'ReIT'],
    ]);
    legacyRows('master_vertical', [
        ['id' => 1, 'vertical_name' => 'Debenture Trustee', 'vertical_code' => 'DT', 'product_id' => '4,49,999'],
        ['id' => 14, 'vertical_name' => 'Litigation', 'vertical_code' => 'LIT', 'product_id' => ''],
        ['id' => 15, 'vertical_name' => 'Admin', 'vertical_code' => 'ADM', 'product_id' => null],
    ]);
    legacyRows('master_vertical_team', [
        ['id' => 1, 'team_name' => 'DT - Team A (L)', 'team_email' => 'Operation1@beacontrustee.co.in', 'vertical_id' => 1, 'product_id' => '4', 'authorised_sign_id' => 111, 'billing_email' => 'ops@b.test, acc@b.test'],
        ['id' => 15, 'team_name' => 'Team A', 'team_email' => '', 'vertical_id' => 14, 'product_id' => null, 'authorised_sign_id' => null, 'billing_email' => null],
        ['id' => 16, 'team_name' => 'Team A', 'team_email' => 'operation1@beacontrustee.co.in', 'vertical_id' => 15, 'product_id' => null, 'authorised_sign_id' => null, 'billing_email' => null],
    ]);
    legacyRows('users', [
        ['id' => 111, 'name' => 'Pratap Signatory', 'emp_code' => '040', 'email' => 'pratap@beacontrustee.co.in', 'department_id' => 1, 'designation_id' => 14, 'rm_id' => null,
            'vertical_id' => 1, 'vertical_team_id' => 1, 'product_map_id' => '4,15', 'password' => Hash::make('Legacy-Pass-1'), 'is_authorised_signatory' => 1,
            'signatory_image' => 'data:image/png;base64, '.base64_encode('PNG-BYTES')],
        ['id' => 112, 'name' => 'Ravi Officer', 'emp_code' => 'bex002', 'email' => 'ravi@beacontrustee.co.in', 'department_id' => 3, 'designation_id' => 0, 'rm_id' => 111,
            'vertical_id' => null, 'vertical_team_id' => null, 'product_map_id' => null, 'password' => Hash::make('x'), 'is_authorised_signatory' => 0, 'signatory_image' => null],
        // No employee code, shares Ravi's email, and left long ago.
        ['id' => 64, 'name' => 'Old Director', 'emp_code' => null, 'email' => 'ravi@beacontrustee.co.in', 'department_id' => 0, 'designation_id' => null, 'rm_id' => 0,
            'vertical_id' => null, 'vertical_team_id' => null, 'product_map_id' => null, 'password' => Hash::make('y'), 'is_authorised_signatory' => 0, 'signatory_image' => null, 'is_active' => 0, 'is_deleted' => 1],
    ]);
});

test('the organisation import maps departments, products, verticals, teams and users', function () {
    $this->artisan('legacy:import', ['area' => 'organisation'])->assertSuccessful();

    expect(Department::query()->where('legacy_id', 3)->value('name'))->toBe('Business Development')
        ->and(Department::query()->where('legacy_id', 9)->value('is_active'))->toBeFalse()
        ->and(Department::query()->whereNotNull('code')->count())->toBe(0);

    // The seeded DEB product is adopted, not duplicated; a repeated code is made unique.
    expect(Product::query()->where('code', 'DEB')->sole()->legacy_id)->toBe(4)
        ->and(Product::query()->where('legacy_id', 37)->value('code'))->toBe('EWT-37')
        ->and(Product::query()->where('legacy_id', 49)->value('code'))->toBe('REIT');

    $dt = Vertical::query()->where('legacy_id', 1)->sole();
    expect($dt->products()->pluck('code')->sort()->values()->all())->toBe(['DEB', 'REIT']);

    // Same-named teams get their vertical in brackets; a shared email stays with the first team.
    expect(VerticalTeam::query()->where('legacy_id', 15)->value('name'))->toBe('Team A (Litigation)')
        ->and(VerticalTeam::query()->where('legacy_id', 16)->value('name'))->toBe('Team A (Admin)')
        ->and(VerticalTeam::query()->where('legacy_id', 1)->value('email'))->toBe('operation1@beacontrustee.co.in')
        ->and(VerticalTeam::query()->where('legacy_id', 16)->value('email'))->toBeNull()
        ->and(VerticalTeam::query()->where('legacy_id', 15)->value('email'))->toBeNull();

    $pratap = User::query()->where('legacy_id', 111)->sole();
    $ravi = User::query()->where('legacy_id', 112)->sole();
    $old = User::query()->where('legacy_id', 64)->sole();

    expect($pratap->emp_code)->toBe('040')
        ->and($pratap->is_active)->toBeTrue()
        ->and(Hash::check('Legacy-Pass-1', $pratap->password))->toBeTrue()
        ->and($pratap->must_change_password)->toBeTrue()
        ->and($pratap->two_factor_secret)->toBeNull()
        ->and($pratap->products()->count())->toBe(2)
        ->and($pratap->verticalTeams()->value('legacy_id'))->toBe(1)
        ->and(Storage::disk('local')->get($pratap->signature_path))->toBe('PNG-BYTES')
        ->and(VerticalTeam::query()->where('legacy_id', 1)->value('signatory_id'))->toBe($pratap->id);

    expect($ravi->emp_code)->toBe('BEX002')
        ->and($ravi->reporting_manager_id)->toBe($pratap->id)
        ->and($ravi->designation_id)->toBeNull()
        ->and($ravi->email)->toBe('ravi@beacontrustee.co.in');

    expect($old->emp_code)->toBe('L64')
        ->and($old->is_active)->toBeFalse()
        ->and($old->email)->toBe('user-64@legacy.invalid');
});

test('running it again changes nothing, and never undoes a password changed in Nexora', function () {
    $this->artisan('legacy:import', ['area' => 'organisation'])->assertSuccessful();
    $ravi = User::query()->where('legacy_id', 112)->sole();
    $ravi->forceFill(['password' => 'New-Nexora-Pass-1!', 'password_changed_at' => now(), 'must_change_password' => false])->save();

    $this->artisan('legacy:import', ['area' => 'organisation'])->assertSuccessful();

    expect(User::query()->count())->toBe(3)
        ->and(Department::query()->count())->toBe(3)
        ->and(Hash::check('New-Nexora-Pass-1!', $ravi->fresh()->password))->toBeTrue()
        ->and($ravi->fresh()->must_change_password)->toBeFalse();
});

test('a dry run reports everything and leaves the database and files untouched', function () {
    $this->artisan('legacy:import', ['area' => 'all', '--dry-run' => true])
        ->expectsOutputToContain('Dry run: everything above was rolled back.')
        ->assertSuccessful();

    expect(User::query()->count())->toBe(0)
        ->and(Department::query()->count())->toBe(0)
        ->and(Role::query()->where('name', '!=', 'super-admin')->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('signatures'))->toBe([]);
});

test('the legacy database can\'t be written through the import models', function () {
    $user = LegacyUser::query()->findOrFail(111);

    expect(fn () => $user->forceFill(['name' => 'changed'])->save())->toThrow(LogicException::class)
        ->and(fn () => $user->delete())->toThrow(LogicException::class);
});

test('the test helper refuses to reset any database but the dedicated test one', function () {
    config(['database.connections.legacy.database' => 'beacon_stack']);

    expect(fn () => legacySchema())->toThrow(RuntimeException::class, 'Refusing to reset legacy database "beacon_stack"');
});
