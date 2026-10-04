<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Site form security policy
    |--------------------------------------------------------------------------
    |
    | Owner-approved defaults for the centralized anti-spam layer. A Site may
    | override any value through its stored security policy; absent values fall
    | back to these defaults (FORMS_AND_INTEGRATIONS.md §11–§12).
    |
    */

    'security' => [
        'ip_limit' => 5,
        'ip_window_minutes' => 10,
        'phone_limit' => 2,
        'phone_window_minutes' => 30,
        'duplicate_window_minutes' => 15,
        'captcha_required' => false,
    ],

];
