# gemvc/cli-dev Release Notes

## Version 1.1.0 — Refactor, tests, and stability

**Release Date**: 9 June 2026  
**Type**: Minor release  
**Tag**: `1.1.0`

### Overview

Internal refactor of all CLI commands into testable `protected` helpers without changing the public command API (`execute(): bool`, class names, and existing hooks). Expanded unit and feature test suites, PHPStan level 9 clean, and fixes for edge cases in database and admin commands.

**No application changes required** — same `vendor/bin/gemvc` entry point and command names.

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
