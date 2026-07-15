# gemvc/cli-dev Release Notes

## Version 1.2.0 — PostgreSQL and SQLite support

**Release Date**: 15 July 2026
**Type**: Minor release
**Tag**: `1.2.0`

### Overview

`gemvc/library` ^5.9 added first-class PostgreSQL/SQLite support (`gemvc init --db=postgres|sqlite`, dialect-aware `db:migrate`). This release brings the rest of the development CLI up to parity so a project initialized with a non-MySQL driver has a fully working dev workflow, not just migrations.

**No application changes required** — same `vendor/bin/gemvc` entry point and command names.

### Improvements

- **`ResolvesDatabaseEnvironment` trait** — added `resolveDriver(): string` (normalizes `DB_DRIVER` to `mysql`/`pgsql`/`sqlite`, defaulting to `mysql`), used by all database commands instead of ad-hoc `strtolower($_ENV['DB_DRIVER'] ?? 'mysql')` checks.
- **`db:describe`** — table existence, column/index/foreign-key introspection, and table statistics now branch per driver: `to_regclass`/`information_schema.columns`/`pg_index`/`pg_class` for PostgreSQL, `sqlite_master` for SQLite existence checks, `SHOW ...`/`INFORMATION_SCHEMA` for MySQL (unchanged).
- **`db:init`** — database existence check and `CREATE DATABASE` statement are driver-aware (`pg_database` lookup + double-quoted identifier for PostgreSQL; no-op for SQLite, which creates its file lazily on first connection).
- **`db:list`** — table/column listing now supports PostgreSQL (`information_schema.tables`/`.columns`) and SQLite (`sqlite_master`, `PRAGMA table_info`) in addition to MySQL `SHOW TABLES`/`SHOW COLUMNS`.
- **`db:drop`** — table-exists check, pre-drop structure preview, and the `DROP TABLE` statement itself are driver-aware, including correct identifier quoting (`` ` `` for MySQL, `"` for PostgreSQL/SQLite).
- **`db:unique`** — duplicate-detection query and constraint creation use driver-correct identifier quoting; SQLite (which has no `ALTER TABLE ... ADD CONSTRAINT`) falls back to `CREATE UNIQUE INDEX`.
- **`admin:setadmin`** — database-exists and `users`-table-exists checks now work correctly on PostgreSQL (previously queried `information_schema.SCHEMATA`/`.tables` using MySQL-only semantics, which cannot detect a Postgres database or the correct schema); recognizes `postgres`/`pgsql` as Docker Compose hostnames alongside `db`/`mysql`/`database`; error message no longer hardcodes "MySQL".

### Testing

- **235 tests** (unit + feature), PHPStan **level 9**, all passing
- `PdoMock` test double extended to stub `fetchColumn()` so PostgreSQL code paths (`to_regclass`, `pg_database`, `reltuples`) are covered
- New PostgreSQL-path test cases added for `db:describe`, `db:init`, `db:list`, `db:drop`, `db:unique`, and `admin:setadmin`

### Requirements

- `gemvc/library` ^5.9 (for `DB_DRIVER`-aware `.env` generation via `gemvc init`)
- `gemvc/cli-base` ^1.0.1
- PHP ^8.2

### Migration

No breaking changes. Existing MySQL projects continue to work unmodified — all new branches are additive and only activate when `DB_DRIVER` is `pgsql` or `sqlite`.

---

## Version 1.1.2 — Refactor, tests, and stability

**Release Date**: 9 June 2026  
**Type**: Minor release  
**Tag**: `1.1.2`

### Overview

Internal refactor of all CLI commands into testable `protected` helpers without changing the public command API (`execute(): bool`, class names, and existing hooks). Expanded unit and feature test suites, PHPStan level 9 clean, and fixes for edge cases in database and admin commands.

**No application changes required** — same `vendor/bin/gemvc` entry point and command names.

### Packaging

- `composer.json` `version` set to `1.1.2` so Packagist accepts the git tag
- `extra.branch-alias.dev-main` updated to `1.1.x-dev`

### Improvements

- **Shared traits**
  - `ResolvesDatabaseEnvironment` — `loadProjectEnv()` and `resolveDatabaseName()` for DB commands
- **Codegen base (`DevGenerator`)**
  - Centralised `formatServiceName()` and `determineProjectRoot()` (removed duplication across Create\* commands)
- **Database commands**
  - `DbUnique`, `DbInit`, `DbList`, `DbDrop` — logic split into named `protected` helpers
  - `DbDescribe` — fetch/render separation (`fetchColumns`, `fetchIndexes`, `fetchForeignKeys`, etc.)
- **Admin commands**
  - `AdminSetpassword` — `readEnvContent`, `mergeAdminPassword`, `writeEnvContent`
  - `SetAdmin` — `promptAdminDetails`, `createAdminUser`, `configureCliDatabaseHost`, `ensureNoExistingUsers`
  - `CreateCrud` — `runCrudGeneration()`; nested `CreateService` no longer calls `exit()` on success
- **OptionalToolsInstaller** — config helpers exposed as `protected` for testing (`getPhpunitConfigTemplate`, etc.)

### Bug fixes

- **Create\*** commands — use `\PROJECT_ROOT` (global constant) instead of namespaced `PROJECT_ROOT`
- **DbUnique** — validate `table/column` before `explode()`; report all duplicate rows (not only the first)
- **DbInit** — safe `$_ENV['DB_NAME'] ?? null` handling
- **AdminSetpassword** — `is_file()` check before reading `.env`
- **CreateCrud** — completes and prints its own success message when run from `gemvc create:crud`

### Testing

- **223 tests** (unit + feature), PHPStan **level 9**
- Separate PHPUnit suites: `composer test:unit`, `composer test:feature`, `composer test:feature:coverage`
- Feature tests seed a real project layout (`.env`, `composer.json`, templates) under `build/feature-projects/`
- Test doubles use non-interactive `FileSystemManager` to avoid stdin hangs on file overwrite

### Requirements

Unchanged from 1.0.0:

- `gemvc/library` ^5.8
- `gemvc/cli-base` ^1.0.1
- PHP ^8.2

---

## Version 1.0.0 — Initial extraction (B2 split)

**Release Date**: 8 June 2026  
**Type**: Initial release  
**Tag**: `1.0.0`

### Overview

Development CLI commands extracted from `gemvc/library` 5.9 (B2 split). Core onboarding (`gemvc init`, `db:migrate`) stays in the library.

### Included commands

- **Codegen**: `create:service`, `create:controller`, `create:model`, `create:table`, `create:crud`
- **Database (dev)**: `db:init`, `db:list`, `db:describe`, `db:drop`, `db:unique`
- **Admin**: `admin:setpassword`, `admin:setadmin`
- **Init helper**: `OptionalToolsInstaller` (PHPStan / PHPUnit / Pest prompts during `gemvc init` when cli-dev is installed)

### Templates

Shipped under `templates/cli/` (service, controller, model, table).

### Requirements

- `gemvc/library` ^5.8
- `gemvc/cli-base` ^1.0.1
- PHP ^8.2

### Migration

After `gemvc/library` 5.9:

```bash
composer require --dev gemvc/cli-dev
```

No application code changes — same `vendor/bin/gemvc` entry point and command names.
