<p align="center">
    <a href="https://www.rekuest.com" target="_blank" rel="noopener">
        <img src="docs/logo-rekuest.svg" alt="Rekuest" width="200">
    </a>
</p>

# Laravel Artifact Deployer

> ⚠️ **Work in progress** — this package is still under active development and may change without notice until the first stable release. Use in production at your own risk.

[![License](https://img.shields.io/badge/license-MIT-green.svg?style=flat-square)](LICENSE.md)
[![PHP](https://img.shields.io/badge/PHP-%E2%89%A5%208.2-777BB4.svg?style=flat-square)](https://www.php.net)
[![Laravel](https://img.shields.io/badge/Laravel-11%20%C2%B7%2012%20%C2%B7%2013-FF2D20.svg?style=flat-square)](https://laravel.com)

A robust, secure and configurable **artifact-based deploy runner** for Laravel — covering the whole loop:
**pack** the build into a single artifact, ship it (FTP/SFTP or your CI), then **lay it down and run a post-deploy
pipeline** on the server with **one HTTP call** or an artisan command. No SSH session required to finalize a deploy.

Built around a few principles: **POST/CLI only** with the secret in a **dedicated header** compared via `hash_equals`,
**fail-closed** when the key is unset, **anti zip-slip** extraction, optional **SHA-256** integrity, a single-flight
**lock**, a **maintenance window** with smart recovery, and structured **JSON / logs / events**.

## Why

Deploying a Laravel app — large or small — should not require SSH access to the box to "finish" the deploy.
This package lets you build the whole app into a **single artifact**, transfer just that one file via **FTP/SFTP**
(or your CI's copy step), and finalize everything — extraction, migrations, cache warming — with **one HTTP call**.

It fits wherever you deploy:

- **Shared / managed hosting** where you only have FTP and no shell: upload `build_artifact.zip`, hit the endpoint, done.
- **A Jenkins (or any CI) pipeline**: `rad:pack` on the build agent, ship the file, then a single `curl` POST as the deploy step.
- **No CI/CD at all**: build locally, drag the file up over SFTP, trigger the URL from a browser-free `curl`.

And it does it **safely**. Laying an archive down over a live application and running migrations is easy to get
wrong, so the package handles the sharp edges for you:

- **POST/CLI only**, secret in a dedicated header compared with `hash_equals` (never a `GET ?key=` that leaks into logs).
- **Anti zip-slip** extraction: every entry validated before a single byte is written, protected paths (`.env`, `storage/`) never overwritten.
- **Atomic lock** (no corrupt parallel deploys), optional **SHA-256** integrity, **maintenance window** with smart recovery.
- **Structured output** — explicit HTTP codes, JSON result, per-deploy logs and events — instead of `dd()`/`die()` and leaked stack traces.
- A **declarative, configurable** command pipeline: the package runs whatever post-deploy commands your app declares, and knows nothing about it.

## Features

- **Full loop** — `rad:pack` builds the artifact on CI; `rad:run` (or the HTTP webhook) deploys it on the server.
- **Two triggers, one pipeline** — `POST /{prefix}/run` for the CI webhook, `php artisan rad:run` for SSH/CI. Identical behaviour.
- **Secure by default** — POST only, key in the `laravel-artifact-deployer-key` header (`hash_equals`), query-string disabled, fail-closed when `RAD_KEY` is unset, environment allow-list, failed-auth audit logging.
- **Safe extraction** — every archive entry is validated (path-traversal, absolute paths, symlinks, protected paths) **before** anything is written; the whole archive is rejected on the first unsafe entry. After validation, native `extractTo()` is used for speed.
- **Pluggable format** — `zip` (ext-zip) or `targz` (PharData / ext-phar), selected via config; a missing extension fails explicitly.
- **Optional integrity** — SHA-256 verified when a `.sha256` sidecar or a body hash is provided; can be made mandatory.
- **Configurable command pipeline** — `package:discover` → `migrate --force` → your `custom[]` commands → `optimize:clear`, each toggleable. The package knows nothing about your app.
- **Single-flight lock** — concurrent deploys get `409 Conflict`.
- **Smart maintenance window** — the site goes `down` only at the moment extraction is about to write (after all checks pass), and `up` on success. A failure **during the checks** (artifact missing, bad archive, checksum mismatch) **never takes the site offline**; a failure **after** mutation started leaves it **down** (no automatic rollback). The deploy route is **excluded from maintenance** (no secret needed) so the CI can always re-trigger.
- **Observable** — `deploy_id` on every log line, a persisted `last.json`, `rad:status`, and `DeployStarted/StepCompleted/Succeeded/Failed` events.

## Requirements

- PHP `^8.2`
- Laravel `^11.0` · `^12.0` · `^13.0`
- `ext-zip` (for `zip`) and/or `ext-phar` (for `targz`)
- For **packing** `targz` (`rad:pack --format=targz`): `phar.readonly=0` in php.ini. Extraction and zip packing have no such requirement.

## Installation

```bash
composer require rekuest/laravel-artifact-deployer
php artisan vendor:publish --tag="artifact-deployer-config"
```

Only two variables are required in `.env`:

```dotenv
RAD_ENABLED=true
RAD_KEY=        # 32+ byte hex — required for the HTTP endpoint
```

Generate the key:

```bash
php -r "echo bin2hex(random_bytes(32)).PHP_EOL;"
```

Everything else has sensible defaults — change it in the published `config/artifact-deployer.php`
rather than `.env`. A few optional env overrides you may actually want:

| Variable | Default | Set it when… |
|---|---|---|
| `RAD_MAINTENANCE_SECRET` | — | you want a bypass token to view the site while it's in maintenance |
| `RAD_ARTIFACT_FORMAT` | `zip` | your artifact is a tar.gz (`targz`) |
| `RAD_REQUIRE_CHECKSUM` | `false` | you want to force SHA-256 verification (`true`) |
| `RAD_ARTIFACT_AFTER` | `rename` | you want `delete` or `keep` instead of renaming after a deploy |
| `RAD_ALLOW_QUERY_STRING` | `false` | your CI cannot send custom headers and must pass the key as `?key=` |

> `RAD_ARTIFACT_PATH` defaults to `base_path('build_artifact.zip')`, so you normally don't set it —
> only override it if the artifact lives somewhere else. The same goes for the route prefix, migration
> and `optimize:clear` toggles, etc.: they're all in the config file.

## The deploy loop

```
┌── CI / build machine ───────────────┐        ┌── target server ──────────────────────┐
│ composer install --no-dev && build  │  scp   │ POST /artifact-deployer/run            │
│ php artisan rad:pack  ───────────────┼──────▶ │   or  ssh … php artisan rad:run        │
│   → build_artifact.zip (+ .sha256)  │        │   → verify → extract → migrate → up    │
└─────────────────────────────────────┘        └────────────────────────────────────────┘
```

### 1. Pack the artifact (CI) — `rad:pack`

Replaces a raw `zip -r build_artifact.zip .`. It honours `artifact.format`, names the archive from `artifact.path`,
applies the configured exclusions — **and always excludes the archive itself and its `.sha256`** (which `zip -r` does
not) — and writes the SHA-256 sidecar.

```bash
php artisan rad:pack
#   [--output=path] [--format=zip|targz] [--source=dir] [--no-checksum]
```

Exclusions live in `config('artifact-deployer.artifact.pack.exclude')` (same matching convention as `protected_paths`):

```php
'pack' => [
    'source'  => base_path(),
    'exclude' => ['.git/', '.env', '.env.*', 'node_modules/', 'storage/logs/', 'tests/', '*.sha256', '*.deployed'],
    'checksum' => true, // also write <artifact>.sha256
],
```

### 2. Deploy on the server

**HTTP (CI webhook):**

```bash
curl -fsS -X POST https://app.example.com/artifact-deployer/run \
     -H "laravel-artifact-deployer-key: $RAD_KEY" \
     -H "Content-Type: application/json" \
     -d '{"artifact":"build_artifact.zip","sha256":"'"$(cut -d' ' -f1 build_artifact.zip.sha256)"'"}'
```

**CLI (SSH, preferred — no HTTP port exposed):**

```bash
php artisan rad:run [--artifact=path] [--sha=hex] [--no-maintenance] [--dry-run]
php artisan rad:status [--json]
```

> `--dry-run` validates the artifact (and integrity) and runs the pipeline without writing files, migrating or
> entering maintenance. There is no `rad:rollback` — see [Rollback strategy](#rollback-strategy).

### Response

```json
{
  "deploy_id": "2026-06-08T10-12-33_a1b2c3",
  "status": "success",
  "duration_ms": 18234,
  "steps": [
    { "name": "verify_artifact", "status": "ok", "output": "format=zip; archive readable; sha256 match" },
    { "name": "extract_artifact", "status": "ok", "output": "412 files extracted" },
    { "name": "run_artisan:migrate", "status": "ok", "output": "..." }
  ]
}
```

| Code | Meaning |
|---|---|
| `200` | deploy completed |
| `401` | missing / wrong token |
| `403` | environment not allowed |
| `409` | a deploy is already running |
| `422` | artifact missing or checksum invalid |
| `500` | a step failed (no automatic rollback — re-deploy the previous artifact) |
| `503` | package disabled |

## The pipeline

Ordered steps (`DeployPipeline`):

1. `VerifyArtifact` — extension available? artifact present? archive openable? SHA-256 (if provided or required). **Read-only.**
2. `ExtractArtifact` — validates every entry (anti path-traversal, protected paths), then writes. **At the point of no return** — once validation passes and the first byte is about to be written — it enters maintenance (`artisan down`, idempotent and non-fatal).
3. `PreMigrateDump` — optional manual-DR DB dump (off by default), taken while the site is down.
4. `RunArtisanCommands` — `package:discover` → `migrate --force` → `custom[]` → `optimize:clear` (per toggles).
5. `RestartServices` — `queue:restart`, optional `horizon:terminate`.
6. On success: `ExitMaintenanceMode` (`up`) + `DisposeArtifact` (`rename`/`delete`/`keep`).

> **Maintenance is entered only when the deploy is about to mutate the app.** Every check — artifact missing,
> bad checksum, unopenable/corrupt archive, even a path-traversal entry — happens *before* `down`, so a failed
> check **never takes the site offline**. The site goes down only at the instant extraction starts writing, and a
> failure from that point on leaves it down (no automatic rollback).

> **Queue / Horizon and maintenance.** There is no explicit worker "stop" step paired with `down`: it isn't needed
> when maintenance is enabled, because queue workers and Horizon **auto-pause while the app is in maintenance**
> (unless a worker runs with `--force`). So `down` suspends them, and `RestartServices` (`queue:restart` /
> `horizon:terminate`) makes them reload the new code on `up`. With `maintenance.enabled = false` (hot deploy)
> workers keep running old code during the window — the package can't stop supervisor-managed processes, so only
> the final restart applies.

The deploy route is registered as **excluded from maintenance mode** (`PreventRequestsDuringMaintenance::except`),
so once the site is down the CI can still reach `/artifact-deployer/run` to re-trigger — no secret required.

## Customising the pipeline (consumer projects)

The `commands` config is what makes the package generic. Any consumer declares its own commands:

```php
// config/artifact-deployer.php
'commands' => [
    'package_discover' => true,
    'migrate'          => true,
    'custom' => [
        ['my-command-1', []],
        ['my-command-2', []],
    ],
    'optimize_clear'   => true,
],
```

Order: `package:discover` → `migrate --force` → `my-command-1` → `my-command-2` → `optimize:clear`.

### Events

Hook integrations (Slack/Teams, audit table, monitor ping) without touching the package:

- `Rekuest\ArtifactDeployer\Events\DeployStarted`
- `Rekuest\ArtifactDeployer\Events\DeployStepCompleted`
- `Rekuest\ArtifactDeployer\Events\DeploySucceeded`
- `Rekuest\ArtifactDeployer\Events\DeployFailed`

## Rollback strategy

This package uses an **overlay in-place** model and has **no automatic rollback** by design:

- **Files** — rollback = re-deploy the previous artifact kept by the CI.
- **Database** — forward-only; write backward-compatible (expand/contract) migrations. The optional `pre_migrate_dump` is for **manual** disaster recovery only.

A deploy that fails *after* mutation started leaves the site in maintenance; recovery is a re-deploy of the previous
artifact. A deploy that fails *before* mutation (bad/missing artifact) leaves the app untouched and brings it back up.

## Testing

```bash
composer test       # Pest + Orchestra Testbench
composer analyse    # PHPStan / Larastan
composer format     # Pint
```

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md). © Rekuest Srl.
