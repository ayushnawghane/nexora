<?php

use App\Models\Arranger;
use App\Models\Bank;
use App\Models\ContactType;
use App\Models\Department;
use App\Models\Designation;
use App\Models\LeadSource;
use App\Models\Pincode;
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
|   type        text | email | select (belongsTo) | multiselect (belongsToMany)
|   rules       validation rules (uniqueness is added automatically when unique = true)
|   unique      enforce uniqueness (ignoring soft-deleted rows is NOT done: the DB index is the authority)
|   transform   upper | lower — applied before validation
|   list        show as a column on the list
|   search      included in the list search box
|   sortable    column can be sorted
|   relation    Eloquent relation name (select/multiselect)
|   options     [model, label column] for select/multiselect choices (active records only)
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
    ],
];
