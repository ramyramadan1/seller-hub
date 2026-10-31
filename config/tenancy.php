<?php

declare(strict_types=1);

use App\Models\Tenant;
use Stancl\Tenancy\Bootstrappers;

return [
    'tenant_model' => Tenant::class,
    'id_generator' => Stancl\Tenancy\UUIDGenerator::class,

    // Database-per-store, no domains: the current store comes from the authenticated user (SetTenant middleware).
    'domain_model' => null,
    'central_domains' => [],

    /*
     * Bootstrappers run when a tenant is initialized.
     * CacheTenancyBootstrapper needs cache tags, so it is enabled only when the cache store is Redis.
     */
    'bootstrappers' => array_values(array_filter([
        Bootstrappers\DatabaseTenancyBootstrapper::class,
        env('CACHE_STORE', 'file') === 'redis' ? Bootstrappers\CacheTenancyBootstrapper::class : null,
        Bootstrappers\FilesystemTenancyBootstrapper::class,
        Bootstrappers\QueueTenancyBootstrapper::class,
    ])),

    'database' => [
        // Users, tokens, plans, credits, jobs and failed_jobs live here and are never read through a tenant connection.
        'central_connection' => env('DB_CONNECTION', 'mysql'),
        'template_tenant_connection' => null,

        // Tenant databases are named {prefix}{tenant id}{suffix}, e.g. tenant_3f1c...
        'prefix' => 'tenant_',
        'suffix' => '',

        'managers' => [
            'sqlite' => Stancl\Tenancy\TenantDatabaseManagers\SQLiteDatabaseManager::class,
            'mysql' => Stancl\Tenancy\TenantDatabaseManagers\MySQLDatabaseManager::class,
            'mariadb' => Stancl\Tenancy\TenantDatabaseManagers\MySQLDatabaseManager::class,
            'pgsql' => Stancl\Tenancy\TenantDatabaseManagers\PostgreSQLDatabaseManager::class,
        ],
    ],

    'cache' => [
        'tag_base' => 'tenant',
    ],

    'filesystem' => [
        'suffix_base' => 'tenant',
        'disks' => [
            'local',
            'public',
        ],
        'root_override' => [
            // Laravel 11's "local" disk root is storage/app/private.
            'local' => '%storage_path%/app/private/',
            'public' => '%storage_path%/app/public/',
        ],
        'suffix_storage_path' => true,
        'asset_helper_tenancy' => false,
    ],

    'redis' => [
        'prefix_base' => 'tenant',
        'prefixed_connections' => [],
    ],

    'features' => [],

    // No tenant asset route: the SPA is served from public/build.
    'routes' => false,

    'migration_parameters' => [
        '--force' => true,
        '--path' => [database_path('migrations/tenant')],
        '--realpath' => true,
    ],

    'seeder_parameters' => [
        '--class' => 'TenantDatabaseSeeder',
    ],
];
