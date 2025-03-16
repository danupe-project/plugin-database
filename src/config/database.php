<?php
return [
    'mysql' => [
        'host' => env('DANUPE_DATABASE_HOST', 'localhost'),
        'database' => env('DANUPE_DATABASE_NAME', 'danupe'),
        'username' => env('DANUPE_DATABASE_USERNAME', 'root'),
        'password' => env('DANUPE_DATABASE_PASSWORD', ''),
        'port' => env('DANUPE_DATABASE_PORT', '3306'),
        'type' => env('DANUPE_DATABASE_CONNECTION', 'mysql')
    ]
];
