<?php

return [
    // Which EmailProviderInterface implementation to use: "mail" (Laravel mailer; transport via MAIL_MAILER) or "mock".
    'email_provider' => env('EMAIL_PROVIDER', 'mail'),

    // Where enquiry notifications go. Server-side only: never taken from request input.
    'sales_inbox' => env('SALES_INBOX_ADDRESS', 'sales@catalogue.test'),
];
