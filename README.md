# Deepstore

**Database + storage backups to compressed `tar.gz`, with optional off-site copy and Forge-style notifications.**

---

## Features

- **`deepstore:store`** Artisan command
- Generates a single **`archive_yyyy-mm-dd.tar.gz`** that contains
    - Full MySQL dump (routines included)
    - Filtered copy of the Laravel `storage` directory
- Fine-grained **include / exclude** filters for directories, files and database tables
- Optional **SCP transfer** to a remote server (with automatic retries)
- **Forge / CI webhook** on success or failure

---

## Requirements

|            |                                              |
|------------|----------------------------------------------|
| **PHP**    | ≥ 8.1                                        |
| **Laravel**| ≥ 10                                         |
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

| Key | Description | Default |
|-----|-------------|---------|
| `backup_path` | Local directory where archives are written | `storage/app/deepstore` |
| `remote_*` | SSH host, user, path, port, key for remote copy | — |
| `include_*` / `exclude_*` | Lists controlling which storage folders, files or DB tables are backed up | — |
| `forge_webhook_url` | URL that receives a JSON payload after each run | — |

All list-style options accept **comma-separated strings** in `.env`, e.g.:

```dotenv
DEEPSTORE_EXCLUDE_DIRECTORIES=logs,framework/cache
DEEPSTORE_EXCLUDE_TABLES=failed_jobs,jobs
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