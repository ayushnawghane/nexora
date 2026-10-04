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
        $t->tinyInteger('type')->nullable();
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
    // Execution
    $schema->create('poa_master', function (Blueprint $t) use ($flags) {
        $t->id();
        $t->string('poa_name');
        $t->string('email')->nullable();
        $t->string('mobile')->nullable();
        $t->date('valid_from')->nullable();
        $t->date('valid_till')->nullable();
        $flags($t);
    });
    $schema->create('execution_details', function (Blueprint $t) use ($flags) {
        $t->id();
        $t->integer('con_id');
        $t->integer('doc_id');
        $t->string('exe_place')->nullable();
        $t->date('exe_date')->nullable();
        $t->time('exe_time')->nullable();
        $t->string('sign_type')->nullable();
        $t->string('sign_name')->nullable();
        $t->integer('upload_id')->nullable();
        $t->string('uploaded_by')->nullable();
        $t->string('uploaded_date')->nullable();
        $t->date('document_date')->nullable();
        $t->date('execution_date')->nullable();
        $t->string('comments', 1000)->nullable();
        $t->tinyInteger('is_verified')->default(0);
        $t->integer('verified_by')->nullable();
        $t->dateTime('verified_datetime')->nullable();
        $t->integer('created_by')->nullable();
        $t->dateTime('created_date')->nullable();
        $flags($t);
    });
    // Security
    foreach (['master_asset_type' => 'type_asset', 'master_type_charge' => 'type_charge'] as $table => $column) {
        $schema->create($table, function (Blueprint $t) use ($flags, $column) {
            $t->id();
            $t->string($column);
            $flags($t);
        });
    }
    $schema->create('master_security', function (Blueprint $t) use ($flags) {
        $t->id();
        $t->string('security_name');
        $t->integer('asset_type_id')->nullable();
        $flags($t);
    });
    $schema->create('ea_master', function (Blueprint $t) use ($flags) {
        $t->id();
        $t->string('ea_code')->nullable();
        $t->string('ea_name')->nullable();
        $t->string('city')->nullable();
        $flags($t);
    });
    $schema->create('legal_compliance_documents_data', function (Blueprint $t) use ($flags) {
        $t->id();
        $t->integer('con_id');
        $t->integer('legal_id')->default(0);
        foreach (['asset_owner', 'charge_type', 'pertaining_to', 'asset_type', 'encumbered', 'street_name', 'area', 'pincode', 'city', 'state', 'type', 'form_of_securities', 'confirming_party', 'cin_pan_num'] as $c) {
            $t->string($c)->nullable();
        }
        $t->text('asset_office')->nullable();
        $t->tinyInteger('form_type')->nullable();
        $t->integer('created_by')->nullable();
        $t->integer('updated_by')->nullable();
        $flags($t);
    });
    $schema->create('security_mapping', function (Blueprint $t) use ($flags) {
        $t->id();
        $t->integer('legal_id');
        $t->integer('security_id');
        $flags($t);
    });
    $schema->create('sec_roc_mapping', function (Blueprint $t) {
        $t->id();
        $t->integer('con_id');
        $t->string('security_map_id');
        $t->integer('section');
        $t->tinyInteger('is_active')->default(1);
        $t->integer('created_by')->nullable();
        $t->dateTime('created_date')->nullable();
    });
    $schema->create('sec_roc_asset_type', function (Blueprint $t) {
        $t->id();
        $t->integer('con_id');
        $t->integer('map_id');
        foreach (['amount', 'charge_id', 'srn_no', 'remark', 'modify_reason', 'reson_modify_satisfy'] as $c) {
            $t->string($c)->nullable();
        }
        $t->date('challan_date')->nullable();
        foreach (['upload_id', 'challan_id', 'sign_id', 'modify_signed_roc_id', 'modify_challan_id', 'modify_certificate_id', 'modify_lender_noc_id', 'satisfy_signed_roc_id', 'satisfy_challan_id', 'satisfy_certificate_id', 'satisfy_lender_noc_id', 'created_by', 'updated_by'] as $c) {
            $t->integer($c)->nullable();
        }
        $t->tinyInteger('status')->default(0);
        $t->tinyInteger('is_active')->default(1);
        $t->dateTime('created_date')->nullable();
        $t->dateTime('updated_date')->nullable();
    });
    $schema->create('sec_cersai_asset_type', function (Blueprint $t) {
        $t->id();
        $t->integer('con_id');
        $t->integer('map_id');
        foreach (['amount', 'modify_reason', 'reson_modify_satisfy', 'type'] as $c) {
            $t->string($c)->nullable();
        }
        $t->date('challan_date')->nullable();
        $t->date('satisfaction_date')->nullable();
        foreach (['asset_id', 'si_id', 'transaction_id', 'ack_id', 'modify_ack_id', 'modify_challan_id', 'satisfy_ack_id', 'satisfy_challan_id', 'satisfy_lender_noc_id', 'created_by', 'updated_by'] as $c) {
            $t->bigInteger($c)->nullable();
        }
        $t->tinyInteger('status')->default(0);
        $t->tinyInteger('is_active')->default(1);
        $t->dateTime('created_date')->nullable();
        $t->dateTime('updated_date')->nullable();
    });
    $schema->create('sec_pledge_asset_type', function (Blueprint $t) {
        $t->id();
        $t->integer('con_id');
        $t->integer('map_id');
        $t->integer('pledge_unpledge');
        $t->date('pledge_unpledge_date')->nullable();
        foreach (['isin', 'type', 'security_name', 'depository', 'pledgors_dp_name', 'pledgors_client_id', 'pledgee_dp_id', 'beacons_dp_id'] as $c) {
            $t->string($c)->nullable();
        }
        foreach (['number_of_securities', 'face_value_per_security', 'pledgors_dp_id', 'pledgee_client_id', 'upload_pmr_file_id', 'created_by'] as $c) {
            $t->bigInteger($c)->nullable();
        }
        $t->tinyInteger('is_active')->default(1);
        $t->dateTime('created_date')->nullable();
    });
    $schema->create('executed_asset_owner_data', function (Blueprint $t) use ($flags) {
        $t->id();
        $t->string('asset_owner_name');
        $t->integer('con_id');
        $flags($t);
    });
    $diligence = [
        'due_dilligience_roc_search_data' => ['executed_asset_owner_id' => 'int', 'document' => 'str', 'issuing_authority_id' => 'int'],
        'due_dilligience_security_certificate' => ['document' => 'str', 'issuing_authority_id' => 'int', 'udin_unique_number' => 'str'],
        'due_dilligience_noc_document_data' => ['legal_id' => 'int', 'security_mapping_id' => 'int', 'security' => 'str', 'document' => 'str', 'charge_holder' => 'str', 'charge_id' => 'str'],
        'due_dilligience_document_security_data' => ['legal_id' => 'int', 'security_mapping_id' => 'int', 'security' => 'str', 'issuing_authority_id' => 'int', 'udin_unique_no' => 'str'],
        'due_dilligience_additional_data' => ['document' => 'str', 'description' => 'str'],
        'due_dilligience_annexure_data' => ['annexure_type' => 'int', 'annexure_name' => 'str'],
        'due_dilligience_upload_files' => ['type' => 'int', 'upload_id' => 'int', 'executed_asset_owner_id' => 'int', 'security_mapping_id' => 'int', 'legal_id' => 'int', 'additional_data_id' => 'int', 'is_verified' => 'int', 'verified_by' => 'int', 'verified_at' => 'date'],
    ];
    foreach ($diligence as $table => $columns) {
        $schema->create($table, function (Blueprint $t) use ($flags, $columns) {
            $t->id();
            $t->integer('con_id');
            foreach ($columns as $c => $type) {
                match ($type) {
                    'int' => $t->integer($c)->nullable(),
                    'date' => $t->dateTime($c)->nullable(),
                    default => $t->string($c)->nullable(),
                };
            }
            $t->integer('created_by')->nullable();
            $flags($t);
        });
    }

    // ISIN (the `_new` tables Stack uses now).
    $schema->create('mon_payment_schedule_new', function (Blueprint $t) {
        $t->id();
        $t->integer('con_id');
        foreach (['isin', 'seriesname', 'stock_exchange', 'depository', 'base_coupon_rate', 'coupon_desc', 'couponrate', 'put_date', 'call_date', 'comments', 'int_comments'] as $c) {
            $t->string($c)->nullable();
        }
        $t->enum('interest_weekend', ['precede', 'succeed'])->nullable();
        $t->enum('principal_weekend', ['precede', 'succeed'])->nullable();
        foreach (['principal_frequency', 'interest_frequency', 'listing_status', 'placement_type', 'year_convention_int', 'user_id'] as $c) {
            $t->integer($c)->nullable();
        }
        $t->date('allotmentdate')->nullable();
        $t->date('payment_redumtion_date')->nullable();
        $t->tinyInteger('active')->default(1);
        $t->timestamp('ts')->nullable();
    });
    $schema->create('mon_isin_details_new', function (Blueprint $t) {
        $t->id();
        $t->integer('con_id');
        $t->integer('mon_id')->nullable();
        $t->string('type', 50)->nullable();
        $t->date('issue_opening_date')->nullable();
        $t->date('issue_closing_date')->nullable();
        $t->decimal('face_value', 18, 2)->nullable();
        $t->integer('qty_issued')->nullable();
        $t->integer('qty_subscribed')->nullable();
        $t->decimal('subscription_total', 18, 2)->nullable();
        $t->date('allotment_date')->nullable();
        $t->tinyInteger('is_active')->default(1);
        $t->integer('created_by')->nullable();
        $t->dateTime('created_date')->nullable();
    });
    $schema->create('mon_payment_listing_new', function (Blueprint $t) {
        $t->id();
        $t->integer('con_id');
        $t->integer('allotment_id');
        $t->string('type', 50)->nullable();
        $t->date('listing_date')->nullable();
        $t->string('upload_id', 200)->nullable();
        $t->tinyInteger('is_active')->default(1);
    });
    $schedule = fn (string $table, array $columns) => $schema->create($table, function (Blueprint $t) use ($columns) {
        $t->id();
        $t->integer('con_id');
        $t->integer('pay_schedule_id')->nullable();
        $t->date($columns['due'])->nullable();
        $t->date($columns['paid'])->nullable();
        foreach ([$columns['status'], $columns['remark'], $columns['basis'], $columns['amount'], $columns['fv'], $columns['qty']] as $c) {
            $t->string($c)->nullable();
        }
        foreach (['upload_id', 'dlt_upload_id', 'user_id', 'created_by', 'updated_by'] as $c) {
            $t->integer($c)->nullable();
        }
        $t->tinyInteger('active')->default(1);
        $t->dateTime('created_at')->nullable();
        $t->dateTime('updated_at')->nullable();
    });
    $schedule('mon_paymt_interst_sch_new', ['due' => 'in_due_date', 'paid' => 'in_paid_date', 'status' => 'in_status', 'remark' => 'in_remark', 'basis' => 'intrest_redemp_type', 'amount' => 'intrest_total_amnt', 'fv' => 'interest_face_value', 'qty' => 'interest_qty']);
    $schedule('mon_paymt_prin_sch_new', ['due' => 'due_date', 'paid' => 'paid_date', 'status' => 'status', 'remark' => 'prin_remark', 'basis' => 'redemp_type', 'amount' => 'prin_total_amnt', 'fv' => 'prin_face_value', 'qty' => 'prin_qty']);

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
