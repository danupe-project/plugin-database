<?php

return [
    'mysql' => [
        'host' => danupe()->env()->get('DANUPE_DATABASE_HOST', 'localhost'),
        'database' => danupe()->env()->get('DANUPE_DATABASE_NAME', 'danupe'),
        'username' => danupe()->env()->get('DANUPE_DATABASE_USERNAME', 'root'),
        'password' => danupe()->env()->get('DANUPE_DATABASE_PASSWORD', ''),
        'port' => danupe()->env()->get('DANUPE_DATABASE_PORT', '3306'),
        'type' => danupe()->env()->get('DANUPE_DATABASE_CONNECTION', 'mysql')
    ],
    'danupe' => [
        'migrate' => ['Danupe\Plugin\Database\Classes\DatabaseManager', 'migrate', 'Migrate the database'],
        'migrate:rollback' => ['Danupe\Plugin\Database\Classes\DatabaseManager', 'rollback', 'Rollback the last database migration'],
        'migrate:rollback:specific' => ['Danupe\Plugin\Database\Classes\DatabaseManager', 'rollbackSpecificMigration', 'Rollback a specific database migration'],
        'seed' => ['Danupe\Plugin\Database\Classes\DatabaseManager', 'seed', 'Seed the database'],
        'seed:rollback' => ['Danupe\Plugin\Database\Classes\DatabaseManager', 'rollback', 'Rollback the last database seed'],
        'seed:rollback:specific' => ['Danupe\Plugin\Database\Classes\DatabaseManager', 'rollbackSpecificSeed', 'Rollback a specific database seed'],
    ]
];
