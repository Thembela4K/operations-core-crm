<?php

return [
    /*
    | Bank details printed on job cards.
    */
    'bank' => [
        'name' => env('COMPANY_BANK_NAME', 'Datamatics Swaziland'),
        'bank' => env('COMPANY_BANK_BANK', 'Standard Bank Swaziland'),
        'branch' => env('COMPANY_BANK_BRANCH', 'Mbabane'),
        'branch_code' => env('COMPANY_BANK_BRANCH_CODE', '663164'),
        'account_number' => env('COMPANY_BANK_ACCOUNT_NUMBER', '9110003207628'),
        'account_type' => env('COMPANY_BANK_ACCOUNT_TYPE', 'Current Account'),
        'swift' => env('COMPANY_BANK_SWIFT', 'SBICSZMX'),
    ],

    /*
    | Footer line on printed documents.
    */
    'footer' => env('COMPANY_FOOTER', 'Website Design Development and Maintenance: Computer Repairs: Computer Sales: Intranet Design and Implementation: Networking and Infrastructural Development'),

    /*
    | Default text for the "parts used" block when a job is software only.
    */
    'job_card_parts_default' => env('COMPANY_JOB_CARD_PARTS', 'N/A - software development and implementation'),

    'email_signature' => [
        'company' => env('MAIL_SIGNATURE_COMPANY', 'Your Company'),
        'phone' => env('MAIL_SIGNATURE_PHONE'),
        'landline' => env('MAIL_SIGNATURE_LANDLINE'),
        'email' => env('MAIL_SIGNATURE_EMAIL'),
        'website' => env('MAIL_SIGNATURE_WEBSITE'),
        'address' => env('MAIL_SIGNATURE_ADDRESS'),
        'award_text' => env('MAIL_SIGNATURE_AWARD_TEXT'),
        'tagline' => env('MAIL_SIGNATURE_TAGLINE'),
        'image_path' => env('MAIL_SIGNATURE_IMAGE_PATH'),
    ],
];
