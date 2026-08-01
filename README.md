# gemvc/cli-dev

Development CLI commands for the [GEMVC](https://gemvc.de) framework.

Composer package: `gemvc/cli-dev`  
GitHub repo: [gemvc/cli-dev](https://github.com/gemvc/cli-dev)  
Current package version: **1.3.0** (`composer.json`; publish/tag when releasing).

> **WARNING**
>
> This is an **internal GEMVC ecosystem package**. Install it **only as a dev dependency** alongside `gemvc/library`.
>
> **Do not** install in production (`composer install --no-dev` should omit this package).
>
> Core commands (`gemvc init`, `db:migrate`) remain in `gemvc/library`.

## Installation

```bash
composer require --dev gemvc/cli-dev
```

Hard Composer require: `gemvc/cli-base` ^1.0.1 (PHP ^8.2).  
Soft ecosystem pairing: install beside `gemvc/library` in the app. For SQL **views** (`ViewTable` + `db:migrate`), use `gemvc/library` **^5.11**. Multi-driver `.env` (`DB_DRIVER`) needs library ^5.9+.

## Commands

| Group | Commands |
|-------|----------|
| Code generation | `create:service`, `create:controller`, `create:model`, `create:table`, `create:crud` |
| Database (dev) | `db:init`, `db:list` (tables + views), `db:describe` (table or view), `db:drop` (table or view), `db:unique` |
| Admin | `admin:setpassword`, `admin:setadmin` |

Production migrations stay in the core library: `gemvc db:migrate TableClass` (or a `ViewTable` class).

## Namespace

`Gemvc\CLI\Commands\*` — unchanged from the monorepo layout.

Shared internal traits (same namespace, not part of the public CLI surface):

- `ResolvesDatabaseEnvironment` — `loadProjectEnv()`, `resolveDatabaseName()`, `resolveDriver()` (`mysql` / `pgsql` / `sqlite`)
- `ResolvesDatabaseRelations` — shared table/view list, kind, definition, and drop SQL for `db:list` / `db:describe` / `db:drop`

## Dependencies

- `gemvc/cli-base` — terminal I/O and codegen abstracts (**hard** require)
- `gemvc/library` — database layer, helpers, `DbConnect` / `DbMigrate` / `ViewTable` in the consuming app (**soft** pairing; not a Composer require of this package)

## Pairing with gemvc/library

```
gemvc/library  →  does NOT require cli-dev (suggest only)
gemvc/cli-dev  →  requires gemvc/cli-base only (no circular dependency on library)
```

## Development

```bash
composer install
composer test              # unit + feature (241 tests)
composer test:unit         # unit tests only
composer test:feature      # feature tests (project-style runs)
composer test:coverage     # full coverage report
composer test:feature:coverage
composer phpstan           # level 9
```

Feature tests run commands against a seeded project under `build/feature-projects/` (`.env`, `composer.json`, templates). Unit tests use stubs in `stubs/` for library types not bundled in this package.

See [CLI_DEV.md](CLI_DEV.md) for command reference.
