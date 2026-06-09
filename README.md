# gemvc/cli-dev

Development CLI commands for the [GEMVC](https://gemvc.de) framework.

Composer package: `gemvc/cli-dev`  
GitHub repo: [gemvc/cli-dev](https://github.com/gemvc/cli-dev)  
Current release: **1.1.0** (9 June 2026) — see [RELEASE_NOTES.md](RELEASE_NOTES.md)

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

Requires `gemvc/library` ^5.8 (pulled in automatically).

## Commands

| Group | Commands |
|-------|----------|
| Code generation | `create:service`, `create:controller`, `create:model`, `create:table`, `create:crud` |
| Database (dev) | `db:init`, `db:list`, `db:describe`, `db:drop`, `db:unique` |
| Admin | `admin:setpassword`, `admin:setadmin` |

Production migrations stay in the core library: `gemvc db:migrate TableClass`.

## Namespace

`Gemvc\CLI\Commands\*` — unchanged from the monorepo layout.

Shared internal traits (same namespace, not part of the public CLI surface):

- `ResolvesDatabaseEnvironment` — DB env loading for `db:*` commands

## Dependencies

- `gemvc/cli-base` — terminal I/O and codegen abstracts
- `gemvc/library` — database layer, helpers, `DbConnect` / `DbMigrate` (core)

## Pairing with gemvc/library

```
gemvc/library  →  does NOT require cli-dev (suggest only after 5.9)
gemvc/cli-dev  →  requires gemvc/library (no circular dependency)
```

## Development

```bash
composer install
composer test              # unit + feature (223 tests)
composer test:unit         # unit tests only
composer test:feature      # feature tests (project-style runs)
composer test:coverage     # full coverage report
composer test:feature:coverage
composer phpstan           # level 9
```

Feature tests run commands against a seeded project under `build/feature-projects/` (`.env`, `composer.json`, templates). Unit tests use stubs in `stubs/` for library types not bundled in this package.

See [CLI_DEV.md](CLI_DEV.md) for command reference.
