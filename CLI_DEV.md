# GEMVC Development CLI (`gemvc/cli-dev`)

Commands in this package extend `vendor/bin/gemvc` when installed as a **dev dependency**.

## Code generation

```bash
vendor/bin/gemvc create:service User
vendor/bin/gemvc create:service User -cmt    # controller + model + table
vendor/bin/gemvc create:controller User
vendor/bin/gemvc create:model User -t
vendor/bin/gemvc create:table User
vendor/bin/gemvc create:crud User
```

Flags: `-c` controller, `-m` model, `-t` table (combinable, e.g. `-cmt`).

Templates resolve from:
1. `{project}/templates/cli/*.template` (override)
2. `vendor/gemvc/cli-dev/templates/cli/*.template`

## Database (development)

```bash
vendor/bin/gemvc db:init
vendor/bin/gemvc db:list
vendor/bin/gemvc db:describe users
vendor/bin/gemvc db:drop users
vendor/bin/gemvc db:unique users/email
```

**Production migrations** (`db:migrate`) remain in `gemvc/library`:

```bash
vendor/bin/gemvc db:migrate UserTable
```

## Admin (development)

```bash
vendor/bin/gemvc admin:setpassword
vendor/bin/gemvc admin:setadmin
```

## Optional tools (during init)

When `gemvc/cli-dev` is installed, `gemvc init` may offer PHPStan / PHPUnit / Pest via `OptionalToolsInstaller`.

## Not in this package

| Command | Package |
|---------|---------|
| `init`, `setup` | `gemvc/library` |
| `db:migrate`, `DbConnect` | `gemvc/library` |
