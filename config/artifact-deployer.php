<?php

return [
    // Master switch: when false the HTTP endpoint and rad:run refuse to operate.
    'enabled' => env('RAD_ENABLED', true),

    // Environments in which the HTTP deploy is permitted (empty = all environments).
    'allowed_environments' => ['production', 'staging'],

    // --- HTTP route ---
    'route' => [
        // Prefix of the route: the endpoint becomes POST /{prefix}/run
        // default -> POST /artifact-deployer/run
        'prefix' => env('RAD_ROUTE_PREFIX', 'artifact-deployer'),

        // Middleware applied to the route (VerifyKey is appended automatically).
        'middleware' => ['api'],
    ],

    // --- Authentication ---
    'auth' => [
        // Mandatory. NO silent random default: a missing key fails closed (HTTP 500).
        'key' => env('RAD_KEY'),

        // Dedicated header carrying the key (default, recommended).
        'header' => env('RAD_KEY_HEADER', 'laravel-artifact-deployer-key'),

        // Accept the key ALSO in the query string (?key=...).
        // Default OFF: discouraged (the URL ends up in access logs / proxies / referrers).
        // Enable only if the CI cannot send custom headers.
        'allow_query_string' => env('RAD_ALLOW_QUERY_STRING', false),
        'query_param' => 'key',
    ],

    // --- Artifact ---
    'artifact' => [
        // Artifact format: 'zip' (requires ext-zip) | 'targz' (tar.gz, uses PharData).
        'format' => env('RAD_ARTIFACT_FORMAT', 'zip'),

        'path' => env('RAD_ARTIFACT_PATH', base_path('build_artifact.zip')),
        'checksum_path' => env('RAD_ARTIFACT_SHA', base_path('build_artifact.zip.sha256')),

        // SHA-256 verification is OPTIONAL (default OFF): not every build mechanism can
        // produce and pass the hash to the call. When true, a missing or mismatching
        // checksum fails the deploy with 422. When the checksum IS provided (a .sha256
        // file or the request body), it is verified even with require_checksum = false.
        'require_checksum' => env('RAD_REQUIRE_CHECKSUM', false),

        // Extraction destination.
        'extract_to' => base_path(),

        // Paths/globs that the artifact may NEVER overwrite (protection whitelist).
        'protected_paths' => ['.env', 'storage/', 'auth.json'],

        // What to do with the artifact after a SUCCESSFUL deploy:
        //   'rename' -> rename adding a suffix (e.g. build_artifact.zip.deployed)
        //   'delete' -> remove the file
        //   'keep'   -> leave the file in place
        'after' => env('RAD_ARTIFACT_AFTER', 'rename'),
        'rename_suffix' => '.deployed',

        // --- Packing (rad:pack) ---
        // Build the artifact on the CI/build machine, replacing a raw
        // `zip -r build_artifact.zip .`. Uses artifact.format and artifact.path.
        'pack' => [
            // Directory to pack (default: the project root).
            'source' => base_path(),

            // Patterns (same convention as protected_paths) to EXCLUDE from the
            // artifact. The output archive and its .sha256 are always excluded.
            'exclude' => [
                '.git/',
                '.github/',
                '.env',
                '.env.*',
                'node_modules/',
                'storage/logs/',
                'storage/framework/cache/',
                'storage/framework/sessions/',
                'storage/framework/views/',
                'storage/framework/testing/',
                'tests/',
                '.DS_Store',
                '*.sha256',
                '*.deployed',
            ],

            // Also write a <artifact>.sha256 sidecar next to the archive.
            'checksum' => true,
        ],
    ],

    // --- Maintenance mode ---
    'maintenance' => [
        'enabled' => true,
        'secret' => env('RAD_MAINTENANCE_SECRET'), // bypass URL while the site is down
        'retry' => 30,

        // On a FAILED deploy: keep the site in maintenance (true, default - safer since
        // there is no automatic rollback) or bring it back up (false).
        'keep_down_on_failure' => true,
    ],

    // --- Rollback ---
    // Chosen strategy: NO automatic rollback (see the package docs §2.13).
    //   - Files: rollback = RE-DEPLOY of the previous artifact kept by the CI.
    //   - DB: forward-only, backward-compatible migrations (expand/contract).
    // The pre-migrate dump below is only for MANUAL disaster recovery, not a rollback.
    'pre_migrate_dump' => [
        'enabled' => env('RAD_PRE_MIGRATE_DUMP', false),
        'disk' => 'local',
        'path' => 'artifact-deployer/db-dumps',
        'keep' => 3, // how many dumps to retain
    ],

    // --- Post-deploy commands ---
    // This block is what makes the package GENERIC: the consumer project decides what
    // to run. Fixed execution order:
    //   1. package:discover  (if 'package_discover')
    //   2. migrate --force   (if 'migrate')
    //   3. 'custom' commands (in declaration order)
    //   4. optimize:clear    (if 'optimize_clear')
    'commands' => [
        // package:discover - needed after the unzip to re-discover packages.
        'package_discover' => true,

        // Run migrations? (migrate --force)
        'migrate' => env('RAD_RUN_MIGRATIONS', true),

        // Project-specific custom commands, run IN ORDER between migrate and optimize:clear.
        // Each entry: 'command'  or  ['command', ['--option' => value]].
        // Example (lives in the consumer project config, NOT in the package):
        'custom' => [
            // ['my-command-1', []],
            // ['my-command-2', []],
        ],

        // Final cache cleanup? (optimize:clear)
        'optimize_clear' => env('RAD_OPTIMIZE_CLEAR', true),
    ],

    // Services to restart at the END of the deploy, so workers reload the new code.
    // Note: there is no explicit "stop before" here — it isn't needed when
    // maintenance is enabled, because queue workers and Horizon automatically
    // PAUSE while the app is in maintenance mode (unless started with --force).
    // So entering maintenance suspends them and these commands resume them with
    // the new code. With maintenance.enabled = false (hot deploy) workers keep
    // running old code during the window — only the final restart applies.
    'restart' => [
        'queue' => true,    // queue:restart
        'horizon' => false, // horizon:terminate
    ],

    // --- Logging ---
    'log_channel' => env('RAD_LOG_CHANNEL', 'stack'),

    // Directory (relative to storage/app) where the deploy state file is persisted.
    'state_path' => 'artifact-deployer',

    // Maximum duration of the whole deploy (seconds). Also used as the lock TTL.
    'timeout' => 600,
];
