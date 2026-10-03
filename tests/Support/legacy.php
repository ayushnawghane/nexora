<?php

/*
 * A small stand-in for the legacy Stack database (connection `legacy`, database
 * nexora_legacy_testing in tests), with only the columns the importers read.
 */

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Recreates the legacy tables, empty. Call at the start of each import test. */
function legacySchema(): void
{
    // Safety: only ever touch the dedicated test database, and only its own tables. getTableListing()
    // lists the tables of every schema the connection can see, so it must never drive a drop.
    $database = (string) config('database.connections.legacy.database');
    if ($database !== 'nexora_legacy_testing') {
        throw new RuntimeException("Refusing to reset legacy database \"{$database}\": tests may only use nexora_legacy_testing.");
    }

    $schema = Schema::connection('legacy');
    $schema->disableForeignKeyConstraints();
    foreach ($schema->getTables($database) as $table) {
        if (($table['schema'] ?? $database) !== $database) {
            throw new RuntimeException("Refusing to drop {$table['schema']}.{$table['name']} outside {$database}.");
        }
        $schema->drop($table['name']);
    }

    $flags = function (Blueprint $t) {
        $t->tinyInteger('is_active')->default(1);
        $t->tinyInteger('is_deleted')->default(0);
        $t->timestamp('created_at')->nullable();
        $t->timestamp('updated_at')->nullable();
    };

    $schema->create('master_department', function (Blueprint $t) use ($flags) {
        $t->id();
        $t->string('name');
        $t->string('abbreviation')->nullable();
        $flags($t);
    });
    $schema->create('master_designation', function (Blueprint $t) use ($flags) {
        $t->id();
        $t->string('name');
        $flags($t);
    });
    $schema->create('master_product', function (Blueprint $t) use ($flags) {
        $t->id();
        $t->string('name');
        $t->string('code')->nullable();
        $flags($t);
    });
    $schema->create('master_vertical', function (Blueprint $t) use ($flags) {
        $t->id();
        $t->string('vertical_name');
        $t->string('vertical_code');
        $t->string('product_id')->nullable();
        $flags($t);
    });
    $schema->create('master_vertical_team', function (Blueprint $t) use ($flags) {
        $t->id();
        $t->string('team_name');
        $t->string('team_email')->nullable();
        $t->string('legal_email')->nullable();
        $t->string('compliance_email')->nullable();
        $t->string('billing_email')->nullable();
        $t->bigInteger('vertical_id')->nullable();
        $t->string('product_id')->nullable();
        $t->bigInteger('authorised_sign_id')->nullable();
        $flags($t);
    });
    $schema->create('users', function (Blueprint $t) use ($flags) {
        $t->id();
        $t->string('name')->nullable();
        $t->string('emp_code')->nullable();
        $t->string('email')->nullable();
        $t->bigInteger('designation_id')->nullable();
        $t->bigInteger('department_id')->nullable();
        $t->bigInteger('rm_id')->nullable();
        $t->bigInteger('vertical_id')->nullable();
        $t->bigInteger('vertical_team_id')->nullable();
        $t->string('product_map_id')->nullable();
        $t->string('password')->nullable();
        $t->string('mobile')->nullable();
        $t->date('date_of_joining')->nullable();
        $t->tinyInteger('is_authorised_signatory')->default(0);
        $t->longText('signatory_image')->nullable();
        $t->string('last_login_ip')->nullable();
        $t->dateTime('last_login_date')->nullable();
        $flags($t);
    });
    foreach (['master_lead' => 'lead_name', 'master_contact_type' => 'contact_type', 'master_transaction_type' => 'transaction_type'] as $table => $column) {
        $schema->create($table, function (Blueprint $t) use ($flags, $column) {
            $t->id();
            $t->string($column)->nullable();
            $flags($t);
        });
    }
    $schema->create('master_arranger', function (Blueprint $t) use ($flags) {
        $t->id();
        $t->string('arranger_name')->nullable();
        $t->string('arranger_cin')->nullable();
        $flags($t);
    });
    $schema->create('master_bank', function (Blueprint $t) use ($flags) {
        $t->id();
        $t->string('bank_name')->nullable();
        $t->string('cin')->nullable();
        $flags($t);
    });
    $schema->create('master_pincode', function (Blueprint $t) use ($flags) {
        $t->id();
        $t->string('pincode')->nullable();
        $t->string('city')->nullable();
        $t->string('state')->nullable();
        $flags($t);
    });
    $schema->create('master_cin', function (Blueprint $t) use ($flags) {
        $t->id();
        $t->string('cin')->nullable();
        $t->string('pan_number')->nullable();
        $t->string('company_name')->nullable();
        $t->string('formerly_known')->nullable();
        $t->date('incorp_date')->nullable();
        $t->text('address')->nullable();
        $t->string('category')->nullable();
        $t->string('company_class')->nullable();
        $t->string('isListed')->nullable();
        $flags($t);
    });
    $schema->create('master_gst_no', function (Blueprint $t) use ($flags) {
        $t->id();
        $t->bigInteger('company_id')->nullable();
        $t->string('gstin')->nullable();
        $t->string('name')->nullable();
        $t->string('tradename')->nullable();
        $t->date('registrationDate')->nullable();
        $t->string('status')->nullable();
        $flags($t);
    });
    $schema->create('company_address_master', function (Blueprint $t) use ($flags) {
        $t->id();
        $t->integer('company_id')->nullable();
        $t->integer('master_gst_id')->nullable();
        $t->string('billing_name')->nullable();
        $t->string('billing_address', 1000)->nullable();
        $t->string('regis_address')->nullable();
        $t->string('pincode')->nullable();
        $flags($t);
    });
    $schema->create('company_contact_master', function (Blueprint $t) use ($flags) {
        $t->id();
        $t->bigInteger('company_id')->nullable();
        $t->string('title')->nullable();
        $t->string('contact_name')->nullable();
        $t->string('email')->nullable();
        $t->string('designation')->nullable();
        $t->string('department')->nullable();
        $t->string('landline')->nullable();
        $t->string('mobile')->nullable();
        $t->string('contact_type')->nullable();
        $flags($t);
    });
    $schema->create('transaction', function (Blueprint $t) use ($flags) {
        $t->id();
        $t->bigInteger('product_id')->nullable();
        $t->bigInteger('company_id')->nullable();
        $t->bigInteger('company_address_id')->nullable();
        $flags($t);
    });
    $schema->create('roles', function (Blueprint $t) use ($flags) {
        $t->id();
        $t->string('name');
        $t->string('slug')->nullable();
        $flags($t);
    });
    $schema->create('permissions', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->string('slug');
    });
    $schema->create('roles_permissions', function (Blueprint $t) {
        $t->bigInteger('role_id');
        $t->bigInteger('permission_id');
    });
    $schema->create('users_roles', function (Blueprint $t) {
        $t->bigInteger('user_id');
        $t->bigInteger('role_id');
    });
    $schema->create('users_permissions', function (Blueprint $t) {
        $t->bigInteger('user_id');
        $t->bigInteger('permission_id');
    });
}

/**
 * Inserts rows into a legacy table.
 *
 * @param  list<array<string, mixed>>  $rows
 */
function legacyRows(string $table, array $rows): void
{
    // One insert per row: fixture rows may set different columns.
    foreach ($rows as $row) {
        DB::connection('legacy')->table($table)->insert($row);
    }
}
