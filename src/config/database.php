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
    ],
    'language' => [
        'en' => [
            'errors.invalid_where_clause' => 'Invalid number of arguments for where clause.',
            'errors.invalid_sort_direction' => "Invalid sort direction for column ':column'. Use 'ASC' or 'DESC'.",
            'clearing_database' => 'Clearing the database...',
            'database_cleared' => 'Database has been cleared.',
            'no_migrations' => 'No migrations found to rollback.',
            'migrated' => 'Migrated: :migration',
            'rolled_back' => 'Rolled back: :migration',
            'seeded' => 'Seeded: :seed',
            'rolled_back_seed' => 'Rolled back seed: :seed',
            'migration_already_applied' => 'Migration :migration already applied.',
            'seed_already_applied' => 'Seed :seed already applied.',
        ],
        'de' => [
            'errors.invalid_where_clause' => 'Ungültige Anzahl von Argumenten für die WHERE-Klausel.',
            'errors.invalid_sort_direction' => "Ungültige Sortierrichtung für Spalte ':column'. Verwenden Sie 'ASC' oder 'DESC'.",
            'clearing_database' => 'Leere die Datenbank...',
            'database_cleared' => 'Datenbank wurde geleert.',
            'no_migrations' => 'Keine Migrationen zum Zurücksetzen gefunden.',
            'migrated' => 'Migriert: :migration',
            'rolled_back' => 'Zurückgesetzt: :migration',
            'seeded' => 'Gesät: :seed',
            'rolled_back_seed' => 'Seed zurückgesetzt: :seed',
            'migration_already_applied' => 'Migration :migration bereits angewendet.',
            'seed_already_applied' => 'Seed :seed bereits angewendet.',

        ],
    ],
];
