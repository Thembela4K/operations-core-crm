<?php

return [
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
