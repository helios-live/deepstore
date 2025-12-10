<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Local Backup Path
    |--------------------------------------------------------------------------
    |
    | The directory where Deepstore writes its timestamped archives
    | before they are optionally shipped to a remote location.
    | Defaults to storage/app/deepstore.
    |
    */

    'backup_path'          => env('DEEPSTORE_BACKUP_PATH', storage_path('app/deepstore')),

    /*
    |--------------------------------------------------------------------------
    | Remote Transfer (SCP)
    |--------------------------------------------------------------------------
    |
    | When all three values below are present, Deepstore will attempt to copy
    | the generated archive to the remote server via SCP.  The port and
    | key path are optional; key-based auth is recommended.
    |
    */

    'remote_host'          => env('DEEPSTORE_REMOTE_HOST'),
    'remote_user'          => env('DEEPSTORE_REMOTE_USER'),
    'remote_path'          => env('DEEPSTORE_REMOTE_PATH'),
    'remote_port'          => env('DEEPSTORE_REMOTE_PORT', 22),
    'ssh_key_path'         => env('DEEPSTORE_SSH_KEY_PATH'),

    /*
    |--------------------------------------------------------------------------
    | Storage Directory Filters
    |--------------------------------------------------------------------------
    |
    | Fine-grained control over which folders and files from Laravel’s
    | storage directory make it into the tar.gz.  Lists are provided as
    | comma-separated strings in .env (e.g. "*.jpg,*.png").
    |
    | “Whitelsit” lists act as a whitelist.  If an include list is non-empty,
    | only the given patterns are copied.  Blacklist lists are always honored.
    | Included files are added in addition to any files matched by the whitelist.
    |
    */

    'include_directories'  => array_filter(explode(',', env('DEEPSTORE_INCLUDE_DIRECTORIES', ''))),
    'exclude_directories'  => array_filter(explode(',', env('DEEPSTORE_EXCLUDE_DIRECTORIES', ''))),

    'whitelist_files'        => array_filter(explode(',', env('DEEPSTORE_WHITELIST_FILES', ''))),
    'blacklist_files'        => array_filter(explode(',', env('DEEPSTORE_BLACKLIST_FILES', ''))),
    'include_files'        => array_filter(explode(',', env('DEEPSTORE_INCLUDE_FILES', ''))),



    /*
    |--------------------------------------------------------------------------
    | Database Table Filters
    |--------------------------------------------------------------------------
    |
    | Works just like the storage filters but applies to the MySQL dump.
    | If “include_tables” is set, only those tables are exported.
    | Otherwise the whole DB is dumped except tables listed in “exclude_tables”.
    |
    */

    'include_tables'       => array_filter(explode(',', env('DEEPSTORE_INCLUDE_TABLES', ''))),
    'exclude_tables'       => array_filter(explode(',', env('DEEPSTORE_EXCLUDE_TABLES', ''))),

    /*
    |--------------------------------------------------------------------------
    | Forge / CI Webhook
    |--------------------------------------------------------------------------
    |
    | Optional URL that Deepstore will POST to after each run with a JSON body:
    | { "status": "success" | "failed", "command": "deepstore:store", "time": "YYYY-MM-DD HH:MM:SS" }
    |
    */

    'forge_webhook_url'    => env('DEEPSTORE_FORGE_WEBHOOK_URL'),

    /*|--------------------------------------------------------------------------
    | Miscellaneous Settings
    |--------------------------------------------------------------------------
    |
    | These settings control the date format used for archive names and the
    | prefix for archive files.  The retention policy defines how many
    | archives are kept in the backup directory and whether the first of
    | each month is preserved.
    |*/

    'date_format' => 'Y-m-d',
    'archive_prefix' => 'archive_',

    'retention' => [
        'latest' => 7,
        'keep_first_of_month' => true,
    ],
];
