<?php

use App\Enums\ConditionStage;
use App\Enums\LegalDocumentCategory;
use App\Enums\Listing;
use App\Models\Arranger;
use App\Models\Bank;
use App\Models\ConditionDocument;
use App\Models\ContactType;
use App\Models\Department;
use App\Models\Designation;
use App\Models\IssuingAuthority;
use App\Models\JobSheetActivity;
use App\Models\LeadSource;
use App\Models\LegalDocumentType;
use App\Models\Pincode;
use App\Models\PoaHolder;
use App\Models\Product;
use App\Models\State;
use App\Models\TransactionType;
use App\Models\User;
use App\Models\Vertical;
use App\Models\VerticalTeam;

/*
| Config-driven masters. Each entry is served by the generic MasterController and Masters/Show page.
|
| fields.<attribute>:
|   type        text | email | select (belongsTo) | multiselect (belongsToMany) | enum | boolean (a checkbox) | date
|   rules       validation rules (uniqueness is added automatically when unique = true)
|   unique      enforce uniqueness (ignoring soft-deleted rows is NOT done: the DB index is the authority)
|   transform   upper | lower — applied before validation
|   list        show as a column on the list
|   search      included in the list search box
|   sortable    column can be sorted
|   relation    Eloquent relation name (select/multiselect)
|   options     [model, label column] for select/multiselect choices (active records only)
|   enum        backed enum class for type = enum (shown as a select; empty_label names the blank choice)
| dependents: relations that must be empty before a record can be deleted.
*/

$name = fn (int $max = 150) => [
    'label' => 'Name', 'type' => 'text', 'rules' => ['required', 'string', "max:{$max}"],
    'unique' => true, 'list' => true, 'search' => true, 'sortable' => true,
];

return [
    'groups' => [
        'organisation' => 'Organisation',
        'business' => 'Business development',
        'deals' => 'Deals',
        'reference' => 'Reference data',
    ],

    'definitions' => [
        'departments' => [
            'group' => 'organisation', 'label' => 'Departments', 'singular' => 'department',
            'model' => Department::class,
            'fields' => [
                'name' => $name(100),
                'code' => ['label' => 'Code', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:20', 'alpha_dash'], 'unique' => true, 'transform' => 'upper', 'list' => true, 'search' => true],
            ],
            'dependents' => ['users'],
        ],
        'designations' => [
            'group' => 'organisation', 'label' => 'Designations', 'singular' => 'designation',
            'model' => Designation::class,
            'fields' => ['name' => $name(100)],
            'dependents' => ['users'],
        ],
        'products' => [
            'group' => 'organisation', 'label' => 'Products', 'singular' => 'product',
            'model' => Product::class,
            'fields' => [
                'code' => ['label' => 'Code', 'type' => 'text', 'rules' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9&\-]+$/'], 'unique' => true, 'transform' => 'upper', 'list' => true, 'search' => true, 'sortable' => true, 'hint' => 'Used in EL numbers, e.g. BTL/DEB/EL/…. Changing it affects new numbers only.'],
                'name' => $name(),
            ],
            'dependents' => ['users', 'verticals'],
        ],
        'verticals' => [
            'group' => 'organisation', 'label' => 'Verticals', 'singular' => 'vertical',
            'model' => Vertical::class,
            'fields' => [
                'code' => ['label' => 'Code', 'type' => 'text', 'rules' => ['required', 'string', 'max:20', 'alpha_dash'], 'unique' => true, 'transform' => 'upper', 'list' => true, 'search' => true, 'sortable' => true],
                'name' => $name(),
                'signatory_id' => ['label' => 'Authorised signatory', 'type' => 'select', 'rules' => ['nullable', 'integer'], 'relation' => 'signatory', 'options' => [User::class, 'name'], 'list' => true],
                'product_ids' => ['label' => 'Products', 'type' => 'multiselect', 'rules' => ['array'], 'relation' => 'products', 'options' => [Product::class, 'name'], 'list' => true],
            ],
            'dependents' => ['teams', 'users'],
        ],
        'vertical-teams' => [
            'group' => 'organisation', 'label' => 'Vertical teams', 'singular' => 'vertical team',
            'model' => VerticalTeam::class,
            'fields' => [
                'vertical_id' => ['label' => 'Vertical', 'type' => 'select', 'rules' => ['required', 'integer'], 'relation' => 'vertical', 'options' => [Vertical::class, 'name'], 'list' => true],
                'name' => $name(),
                'email' => ['label' => 'Team email', 'type' => 'email', 'rules' => ['nullable', 'email:rfc', 'max:255'], 'unique' => true, 'transform' => 'lower', 'list' => true, 'search' => true],
                'legal_email' => ['label' => 'Legal email', 'type' => 'email', 'rules' => ['nullable', 'email:rfc', 'max:255'], 'transform' => 'lower'],
                'compliance_email' => ['label' => 'Compliance email', 'type' => 'email', 'rules' => ['nullable', 'email:rfc', 'max:255'], 'transform' => 'lower'],
                'billing_email' => ['label' => 'Billing email', 'type' => 'email', 'rules' => ['nullable', 'email:rfc', 'max:255'], 'transform' => 'lower'],
                'signatory_id' => ['label' => 'Authorised signatory', 'type' => 'select', 'rules' => ['nullable', 'integer'], 'relation' => 'signatory', 'options' => [User::class, 'name']],
                'product_ids' => ['label' => 'Products', 'type' => 'multiselect', 'rules' => ['array'], 'relation' => 'products', 'options' => [Product::class, 'name']],
            ],
            'dependents' => ['users'],
        ],
        'lead-sources' => [
            'group' => 'business', 'label' => 'Lead sources', 'singular' => 'lead source',
            'model' => LeadSource::class,
            'fields' => ['name' => $name()],
        ],
        'arrangers' => [
            'group' => 'business', 'label' => 'Arrangers', 'singular' => 'arranger',
            'model' => Arranger::class,
            'fields' => [
                'name' => $name(200),
                'cin' => ['label' => 'CIN', 'type' => 'text', 'rules' => ['nullable', 'string', 'regex:/^[LUF][0-9]{5}[A-Z]{2}[0-9]{4}[A-Z]{3}[0-9]{6}$/'], 'unique' => true, 'transform' => 'upper', 'list' => true, 'search' => true],
            ],
        ],
        'banks' => [
            'group' => 'business', 'label' => 'Banks', 'singular' => 'bank',
            'model' => Bank::class,
            'fields' => [
                'name' => $name(200),
                'cin' => ['label' => 'CIN', 'type' => 'text', 'rules' => ['nullable', 'string', 'regex:/^[LUF][0-9]{5}[A-Z]{2}[0-9]{4}[A-Z]{3}[0-9]{6}$/'], 'unique' => true, 'transform' => 'upper', 'list' => true, 'search' => true],
            ],
        ],
        'contact-types' => [
            'group' => 'business', 'label' => 'Contact types', 'singular' => 'contact type',
            'model' => ContactType::class,
            'fields' => ['name' => $name()],
            'dependents' => ['companyContacts'],
        ],
        'transaction-types' => [
            'group' => 'business', 'label' => 'Transaction types', 'singular' => 'transaction type',
            'model' => TransactionType::class,
            'fields' => ['name' => $name()],
        ],
        'pincodes' => [
            'group' => 'reference', 'label' => 'Pincodes', 'singular' => 'pincode',
            'model' => Pincode::class,
            'fields' => [
                'pincode' => ['label' => 'Pincode', 'type' => 'text', 'rules' => ['required', 'string', 'regex:/^[1-9][0-9]{5}$/'], 'list' => true, 'search' => true, 'sortable' => true],
                'city' => ['label' => 'City', 'type' => 'text', 'rules' => ['required', 'string', 'max:120'], 'list' => true, 'search' => true, 'sortable' => true],
                'state_id' => ['label' => 'State', 'type' => 'select', 'rules' => ['required', 'integer'], 'relation' => 'state', 'options' => [State::class, 'name'], 'list' => true],
            ],
            // pincode + city together must be unique
            'unique_together' => [['pincode', 'city']],
            'default_sort' => 'pincode',
        ],
        'job-sheet-activities' => [
            'group' => 'deals', 'label' => 'Job sheet activities', 'singular' => 'job sheet activity',
            'model' => JobSheetActivity::class,
            'fields' => [
                'name' => $name(200),
                'listing' => [
                    'label' => 'Applies to', 'type' => 'enum', 'enum' => Listing::class, 'empty_label' => 'All deals',
                    'rules' => ['nullable'], 'list' => true,
                    'hint' => 'Leave empty for every deal, or limit it to listed or unlisted issues.',
                ],
            ],
            'dependents' => ['entries'],
        ],
        'issuing-authorities' => [
            'group' => 'deals', 'label' => 'Issuing authorities', 'singular' => 'issuing authority',
            'model' => IssuingAuthority::class,
            'fields' => ['name' => $name()],
            'dependents' => ['conditionDocuments', 'dealConditions'],
        ],
        'legal-document-types' => [
            'group' => 'deals', 'label' => 'Legal documents', 'singular' => 'legal document',
            'model' => LegalDocumentType::class,
            'fields' => [
                'name' => $name(200),
                'category' => ['label' => 'Category', 'type' => 'enum', 'enum' => LegalDocumentCategory::class, 'rules' => ['required'], 'list' => true],
                'product_ids' => [
                    'label' => 'Products', 'type' => 'multiselect', 'rules' => ['array', 'min:1'], 'relation' => 'products', 'options' => [Product::class, 'name'], 'list' => true,
                    'hint' => 'Deals of these products can add the document.',
                ],
            ],
            'dependents' => ['dealDocuments'],
        ],
        'condition-documents' => [
            'group' => 'deals', 'label' => 'CP / CS documents', 'singular' => 'CP / CS document',
            'model' => ConditionDocument::class,
            'fields' => [
                'stage' => ['label' => 'Stage', 'type' => 'enum', 'enum' => ConditionStage::class, 'rules' => ['required'], 'list' => true],
                'name' => ['label' => 'Document', 'type' => 'text', 'rules' => ['required', 'string', 'max:500'], 'list' => true, 'search' => true, 'sortable' => true],
                'issuing_authority_id' => ['label' => 'Issuing authority', 'type' => 'select', 'rules' => ['nullable', 'integer'], 'relation' => 'issuingAuthority', 'options' => [IssuingAuthority::class, 'name'], 'list' => true],
                'listed_secured' => ['label' => 'Suggested for listed, secured issues', 'type' => 'boolean'],
                'listed_unsecured' => ['label' => 'Suggested for listed, unsecured issues', 'type' => 'boolean'],
                'unlisted_secured' => ['label' => 'Suggested for unlisted, secured issues', 'type' => 'boolean'],
                'unlisted_unsecured' => ['label' => 'Suggested for unlisted, unsecured issues', 'type' => 'boolean'],
            ],
            // the same document name can be both a CP and a CS
            'unique_together' => [['name', 'stage']],
            'dependents' => ['dealConditions'],
            'default_sort' => 'name',
        ],
        'poa-holders' => [
            'group' => 'deals', 'label' => 'POA holders', 'singular' => 'POA holder',
            'model' => PoaHolder::class,
            'fields' => [
                'name' => $name(),
                'email' => ['label' => 'Email', 'type' => 'email', 'rules' => ['nullable', 'email:rfc', 'max:255'], 'transform' => 'lower', 'list' => true, 'search' => true,
                    'hint' => 'Execution instructions are emailed here.'],
                'mobile' => ['label' => 'Mobile', 'type' => 'text', 'rules' => ['nullable', 'string', 'regex:/^\+?[0-9]{10,15}$/']],
                'valid_from' => ['label' => 'Valid from', 'type' => 'date', 'rules' => ['nullable'], 'list' => true],
                'valid_till' => ['label' => 'Valid till', 'type' => 'date', 'rules' => ['nullable', 'after_or_equal:valid_from'], 'list' => true, 'sortable' => true,
                    'hint' => 'Only a POA valid on the execution date can be chosen as a signatory.'],
            ],
            'dependents' => ['executions'],
        ],
    ],
];
