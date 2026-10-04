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
        $t->string('cl_no')->nullable();
        $t->string('deal_id')->nullable();
        $t->bigInteger('company_id')->nullable();
        $t->bigInteger('company_address_id')->nullable();
        $t->bigInteger('gst_id')->nullable();
        $t->bigInteger('team_id')->nullable();
        $t->bigInteger('rm_id')->nullable();
        $t->bigInteger('signatory_id')->nullable();
        $t->bigInteger('transaction_type_id')->nullable();
        $t->bigInteger('arranger_id')->nullable();
        $t->bigInteger('lead_id1')->nullable();
        $t->string('originated_by')->nullable();
        $t->text('transaction_brief')->nullable();
        $t->string('listed_unlisted')->nullable();
        $t->string('issue_type')->nullable();
        $t->string('secured')->nullable();
        $t->string('rated')->nullable();
        $t->decimal('issue_pool_size', 20, 2)->nullable();
        $t->decimal('gso_size', 20, 2)->nullable();
        $t->decimal('total_issue_size', 20, 2)->nullable();
        $t->decimal('tenure_months', 8, 2)->nullable();
        $t->string('status')->nullable();
        $t->integer('status_id')->nullable();
        $t->date('cl_date')->nullable();
        $t->date('offer_date')->nullable();
        $t->date('deal_closed_date')->nullable();
        $t->tinyInteger('is_schedule_verified')->nullable();
        $t->bigInteger('created_by')->nullable();
        $t->bigInteger('updated_by')->nullable();
        $flags($t);
    });
    $schema->create('issue_details', function (Blueprint $t) use ($flags) {
        $t->id();
        $t->bigInteger('con_id');
        foreach (['issue_pool_size', 'gso_size', 'ncd_issue', 'ocd_issue', 'ccd_issue', 'mld_issue', 'ncd_gso', 'ocd_gso', 'ccd_gso', 'mld_gso'] as $column) {
            $t->decimal($column, 20, 2)->nullable();
        }
        $flags($t);
    });
    $schema->create('transaction_contact', function (Blueprint $t) use ($flags) {
        $t->id();
        $t->bigInteger('con_id');
        $t->bigInteger('contact_id');
        $t->string('recipient')->nullable();
        $t->string('billing_recipient')->nullable();
        $flags($t);
    });
    $schema->create('acceptance_fees', function (Blueprint $t) use ($flags) {
        $t->id();
        $t->bigInteger('con_id');
        $t->tinyInteger('accept_amount_type')->nullable();
        $t->decimal('accpt_amount', 18, 6)->nullable();
        $t->decimal('accpt_perc', 18, 6)->nullable();
        $t->bigInteger('accpt_lavy')->nullable();
        $t->bigInteger('accpt_frequency')->nullable();
        $t->bigInteger('accpt_effect_day')->nullable();
        $t->date('acpt_custom_date')->nullable();
        $t->bigInteger('accpt_payment_term')->nullable();
        $flags($t);
    });
    $schema->create('service_fees', function (Blueprint $t) use ($flags) {
        $t->id();
        $t->bigInteger('con_id');
        $t->tinyInteger('serv_amount_type')->nullable();
        $t->decimal('service_amount', 18, 6)->nullable();
        $t->decimal('service_perc', 18, 6)->nullable();
        $t->bigInteger('service_lavy')->nullable();
        $t->bigInteger('service_frequency')->nullable();
        $t->bigInteger('service_eff_day')->nullable();
        $t->date('service_custom_date')->nullable();
        $t->bigInteger('service_pay_term')->nullable();
        $t->bigInteger('service_escalation')->nullable();
        $t->tinyInteger('service_escalation_type')->nullable();
        $t->decimal('service_escalation_fees', 8, 2)->nullable();
        $t->bigInteger('service_years')->nullable();
        $flags($t);
    });
    $schema->create('master_frequency', function (Blueprint $t) use ($flags) {
        $t->id();
        $t->integer('type');
        $t->integer('flag');
        $t->integer('frequency_id');
        $t->string('frequency');
        $flags($t);
    });
    $schema->create('master_cl_schedules', function (Blueprint $t) use ($flags) {
        $t->id();
        $t->bigInteger('con_id');
        $t->integer('tab_id');
        $t->string('fy')->nullable();
        $t->date('bill_date')->nullable();
        $t->date('from_date')->nullable();
        $t->date('to_date')->nullable();
        $t->decimal('no_of_days', 8, 2)->nullable();
        $t->decimal('no_of_days_in_year', 8, 2)->nullable();
        $t->decimal('base_amount', 18, 2)->nullable();
        $t->decimal('applicable_fees', 18, 2)->nullable();
        $flags($t);
    });
    $schema->create('el_versioning', function (Blueprint $t) use ($flags) {
        $t->id();
        $t->bigInteger('con_id');
        $t->string('cl_no')->nullable();
        $t->string('cl_no_new')->nullable();
        $t->integer('revised_count')->nullable();
        $t->bigInteger('upload_id')->nullable();
        $t->bigInteger('created_by')->nullable();
        $flags($t);
    });
    $schema->create('upload_file', function (Blueprint $t) use ($flags) {
        $t->id();
        $t->string('name')->nullable();
        $t->string('path')->nullable();
        $t->bigInteger('created_by')->nullable();
        $flags($t);
    });
    $schema->create('transaction_billing_address', function (Blueprint $t) use ($flags) {
        $t->id();
        $t->bigInteger('con_id');
        $t->bigInteger('address_id');
        $flags($t);
    });
    $schema->create('update_status', function (Blueprint $t) use ($flags) {
        $t->id();
        $t->bigInteger('con_id');
        $t->string('previous_status')->nullable();
        $t->string('new_status')->nullable();
        $t->date('redemption_date')->nullable();
        $t->bigInteger('upload_id')->nullable();
        $t->tinyInteger('is_approved')->default(0);
        $t->bigInteger('created_by')->nullable();
        $flags($t);
    });
    $schema->create('transaction_status_log', function (Blueprint $t) {
        $t->id();
        $t->integer('tran_id');
        $t->integer('status_id');
        $t->integer('created_by')->nullable();
        $t->dateTime('created_date')->nullable();
    });
    $schema->create('transaction_status_master', function (Blueprint $t) use ($flags) {
        $t->id();
        $t->string('status');
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

    // Documentation
    $schema->create('issuing_authority', function (Blueprint $t) use ($flags) {
        $t->id();
        $t->string('Issuer_name');
        $flags($t);
    });
    $schema->create('master_legal_documents', function (Blueprint $t) use ($flags) {
        $t->id();
        $t->bigInteger('con_id')->default(0);
        $t->bigInteger('doc_category')->default(0);
        $t->string('legal_document_name');
        $t->string('product_id')->default('');
        $t->bigInteger('supplementary_to')->default(0);
        $t->bigInteger('parent_id')->default(0);
        $t->bigInteger('created_by')->nullable();
        $flags($t);
    });
    $schema->create('upload_document_mapping', function (Blueprint $t) use ($flags) {
        $t->id();
        $t->bigInteger('con_id');
        $t->bigInteger('legal_id')->nullable();
        $t->bigInteger('upload_id');
        $t->bigInteger('created_by')->nullable();
        $t->bigInteger('updated_by')->nullable();
        $flags($t);
    });
    foreach (['cp_documents' => 'cp_doc_name', 'cs_document' => 'cs_doc_name'] as $table => $name) {
        $schema->create($table, function (Blueprint $t) use ($flags, $name) {
            $t->id();
            $t->integer('issuing_authority_id')->default(0);
            $t->string($name);
            $t->integer('con_id')->nullable();
            $flags($t);
        });
    }
    foreach (['cp_mapping' => 'cp_doc_id', 'cs_mapping' => 'cs_doc_id'] as $table => $key) {
        $schema->create($table, function (Blueprint $t) use ($flags, $key) {
            $t->id();
            $t->integer($key);
            $t->integer('listed_secured')->nullable();
            $t->integer('listed_unsecured')->nullable();
            $t->integer('unlisted_secured')->nullable();
            $t->integer('unlisted_unsecured')->nullable();
            $flags($t);
        });
    }
    foreach (['pre_documents_data' => 'cp_document_id', 'post_documents_data' => 'cs_document_id'] as $table => $key) {
        $schema->create($table, function (Blueprint $t) use ($key) {
            $t->id();
            $t->integer('con_id');
            $t->integer($key)->nullable();
            $t->integer('issuing_authority_id')->nullable();
            $t->string('issuer_name')->nullable();
            $t->text('document_name')->nullable();
            $t->string('upload_id')->nullable();
            $t->string('status')->default('pending');
            $t->tinyInteger('is_active')->default(1);
            $t->integer('created_by')->nullable();
            $t->dateTime('created_date')->nullable();
            $t->dateTime('updated_at')->nullable();
            $t->integer('updated_by')->nullable();
            $t->integer('verified_by')->nullable();
            $t->dateTime('verified_date')->nullable();
        });
    }
    $schema->create('pre_post_upload_map', function (Blueprint $t) {
        $t->integer('id');
        $t->string('section');
        $t->integer('upload_id');
        $t->tinyInteger('is_active')->default(1);
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
