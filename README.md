# Deepstore

**Database + storage backups to compressed `tar.gz`, optional off-site copy via SCP, smart retention, and Forge-style notifications.**

---

## Features

- **`deepstore:store`** Artisan command
- Generates a single **`archive_YYYY-MM-DD.tar.gz`** containing:
    - Full MySQL dump (routines included)
    - Filtered copy of the Laravel `storage` directory
- **Filters**: include/exclude directories, files, and DB tables
- **Remote transfer (SCP)** with resilient paths (handles trailing slashes)
- **Smart retention**:
    - Always keep the **latest 7** backups
    - Always keep the **first backup of each month** (per year)
- **Config-driven** (no direct `.env` reads inside the command)
- **Optional webhook** to a Forge/CI endpoint on success/failure

---

## Requirements

|            |                                  |
|------------|----------------------------------|
| **PHP**    | ≥ 8.2                            |
| **Laravel**| 10, 11, or 12                    |
| **Tools**  | `tar`, `gzip`, `mysqldump`, `scp` in `$PATH` |

---

## Installation

```bash
composer require helios-live/deepstore
php artisan vendor:publish --tag=deepstore-config

```

The second command publishes **`config/deepstore.php`** so you can adjust default settings.

---

## Configuration

| Key                           | Description                                                                                                                                              | Default |
|-------------------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------|---------|
| `backup_path`                 | Local directory where archives are written                                                                                                               | `storage/app/deepstore` |
| `remote_*`                    | SSH host, user, path, port, key for remote copy                                                                                                          | — |
| `include_*` / `exclude_*`     | Lists controlling which storage folders or DB tables are backed up, include means only those will be save, exclude means only those will not be backedup | — |
| `whitelist_*` / `blacklist_*` | Lists controlling which files filtered by name containing                                                                                                | — |
| `include_files`               | Lists controlling which files are included regardless                                                                                                    | — |
| `forge_webhook_url`           | URL that receives a JSON payload after each run                                                                                                          | — |

All list-style options accept **comma-separated strings** in `.env`, e.g.:

```dotenv
DEEPSTORE_EXCLUDE_DIRECTORIES=logs,framework/cache
DEEPSTORE_EXCLUDE_TABLES=failed_jobs,jobs
DEEPSTORE_INCLUDE_DIRECTORIES=app
DEEPSTORE_INCLUDE_FILES=story.png
DEEPSTORE_WHITELIST_FILES=.xml,.jpg,invoices
DEEPSTORE_BLACKLIST_FILES=.png,laravel
```

---

## Usage

```bash
# one-off run
php artisan deepstore:store
```

On success an archive like

```
/home/forge/backups/archive_2025-06-29.tar.gz
```  

is created (and, if configured, copied to the remote server).

---

## Scheduling

Add to `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('deepstore:store')->dailyAt('02:00');
}
```

---

## Environment variables

| Key | Purpose |
|-----|---------|
| `DEEPSTORE_BACKUP_PATH` | Override local backup directory |
| `DEEPSTORE_REMOTE_HOST`, `DEEPSTORE_REMOTE_USER`, `DEEPSTORE_REMOTE_PATH`, `DEEPSTORE_REMOTE_PORT`, `DEEPSTORE_SSH_KEY_PATH` | Remote transfer settings |
| `DEEPSTORE_INCLUDE_DIRECTORIES`, `DEEPSTORE_EXCLUDE_DIRECTORIES` | Storage sub-folders to whitelist / blacklist |
| `DEEPSTORE_INCLUDE_FILES`, `DEEPSTORE_EXCLUDE_FILES` | Glob patterns to include / exclude |
| `DEEPSTORE_INCLUDE_TABLES`, `DEEPSTORE_EXCLUDE_TABLES` | DB tables to include / skip |
| `DEEPSTORE_FORGE_WEBHOOK_URL` | POST target for run status |

---

## Contributing

Pull requests are welcome! Please ensure code passes **PHPStan** and adheres to **PSR-12**:

```bash
composer install
composer pint
vendor/bin/phpstan analyse
```

---

## License

Released under the **MIT License**. See [`LICENSE`](LICENSE) for details.