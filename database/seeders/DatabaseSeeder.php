<?php

namespace Database\Seeders;

use App\Actions\Auth\SetUserPassword;
use App\Models\Product;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([PermissionSeeder::class, StateSeeder::class, TaxRateSeeder::class]);

        Product::query()->firstOrCreate(
            ['code' => Product::DEBENTURE_TRUSTEE],
            ['name' => 'Debenture Trustee'],
        );

        if (app()->environment('local') && ! User::query()->where('emp_code', 'ADMIN')->exists()) {
            $password = Str::password(16);

            $admin = User::query()->make([
                'emp_code' => 'ADMIN',
                'name' => 'System Administrator',
                'email' => 'admin@nexora.test',
            ]);
            app(SetUserPassword::class)->handle($admin, $password, temporary: true);
            $admin->assignRole(Permissions::superAdminRole());

            $this->command->warn("Local super-admin created. Employee code: ADMIN  Temporary password: {$password}");
        }
    }
}
