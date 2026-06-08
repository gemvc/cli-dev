# gemvc/cli-dev Release Notes

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
