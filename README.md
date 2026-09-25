# condoedge/projects

Project management module for Kompo/Laravel applications: suggestions → feature
requests → tasks, with Kanban and Gantt boards and bidirectional GitHub sync.

## Install

```bash
composer require condoedge/projects
php artisan migrate
```

The service provider auto-registers (Laravel package discovery).

## Config

Publish/override via env:

| Env | Purpose |
|-----|---------|
| `GITHUB_TOKEN` | GitHub API token for issue sync |
| `GITHUB_REPO_OWNER` / `GITHUB_REPO_NAME` | Default repo for sync (per-project overrides live on `pm_projects`) |
| `GITHUB_WEBHOOK_SECRET` | Verifies incoming webhook payloads |
| `PROJECTS_ATTACHMENTS_DISK` | Disk for screenshots/attachments (default: app filesystem) |

## Seed

`php artisan projects:seed-data` loads a fixture from
`database/data/pm_seed.json` into the `pm_*` tables. No fixture ships with the
package — drop your own there (the command reports cleanly if it is absent).

## Requires

`php >=8.1`, `laravel/framework >=10`, `kompo/auth`, `condoedge/utils`.
