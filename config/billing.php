<?php

return [
    // Invoice numbers: {fy} is the financial year as "2627", {code} INV / TAX / CN / DN, {serial} the
    // running number in that year and kind. Stack's format, e.g. BTL/2627/INV265.
    'number_format' => env('BILLING_NUMBER_FORMAT', 'BTL/{fy}/{code}{serial}'),
    'serial_digits' => 3,

    // SAC printed on fee lines (Stack: 997154 on all but 5 bills).
    'sac' => env('BILLING_SAC', '997154'),

    // Fee periods appear in the billing queue this many days before their bill date.
    'queue_days' => (int) env('BILLING_QUEUE_DAYS', 30),

    // An issued tax invoice or reimbursement bill is overdue this many days after its date.
    'payment_terms_days' => (int) env('BILLING_PAYMENT_TERMS_DAYS', 30),

    // GST rules allow an IRN (and so a tax invoice) to be cancelled only within 24 hours.
    'irn_cancel_hours' => 24,

    // E-invoicing (GST IRP via IRIS). "fake" issues made-up IRNs for development and tests; "none"
    // issues invoices without an IRN. The IRIS driver is added when UAT credentials arrive.
    'einvoice' => [
        'driver' => env('EINVOICE_DRIVER', 'fake'),
    ],

    // Printed on every invoice. Defaults are Stack's (config/global.php); change them in .env.
    'issuer' => [
        'name' => env('BILLING_ISSUER_NAME', 'Beacon Trusteeship Limited'),
        'address' => env('BILLING_ISSUER_ADDRESS', '5W, 5th Floor, The Metropolitan, E-Block, Bandra Kurla Complex, Bandra (E), Mumbai 400051'),
        'phone' => env('BILLING_ISSUER_PHONE', '+91 95554 49955'),
        'email' => env('BILLING_ISSUER_EMAIL', 'contact@beacontrustee.co.in'),
        'website' => env('BILLING_ISSUER_WEBSITE', 'www.beacontrustee.co.in'),
        'cin' => env('BILLING_ISSUER_CIN', 'L74999MH2015PLC271288'),
        'pan' => env('BILLING_ISSUER_PAN', 'AAGCB5444C'),
        'bank' => [
            'beneficiary' => env('BILLING_BANK_BENEFICIARY', 'Beacon Trusteeship Limited'),
            'bank' => env('BILLING_BANK_NAME', 'IDFC FIRST BANK'),
            'account' => env('BILLING_BANK_ACCOUNT', '10003065608'),
            'ifsc' => env('BILLING_BANK_IFSC', 'IDFB0040101'),
        ],
    ],
];
