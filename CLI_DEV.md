# GEMVC Development CLI (`gemvc/cli-dev`)

Commands in this package extend `vendor/bin/gemvc` when installed as a **dev dependency**.

Current package version: **1.3.0**.

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
vendor/bin/gemvc db:describe user_order_summary   # SQL view / ViewTable
vendor/bin/gemvc db:drop users
vendor/bin/gemvc db:drop user_order_summary --force
vendor/bin/gemvc db:unique users/email
```

### Drivers

`db:init`, `db:list`, `db:describe`, `db:drop`, `db:unique`, and `admin:setadmin` are driver-aware (MySQL, PostgreSQL, SQLite) via `DB_DRIVER` in `.env` (set by `gemvc init` in `gemvc/library`).

| Area | MySQL | PostgreSQL | SQLite |
|------|-------|------------|--------|
| List relations | `SHOW FULL TABLES` | `information_schema.tables` (`BASE TABLE` + `VIEW`) | `sqlite_master` (`table` + `view`) |
| Kind / exists | `INFORMATION_SCHEMA.TABLES.TABLE_TYPE` | `information_schema.tables.table_type` | `sqlite_master.type` |
| Columns | `SHOW COLUMNS` | `information_schema.columns` | `PRAGMA table_info` |
| View definition | `SHOW CREATE VIEW` | `pg_get_viewdef` | `sqlite_master.sql` |
| Drop | `` DROP TABLE/VIEW `name` `` | `DROP TABLE/VIEW "name"` | same quoting as Postgres |

### Tables and views (1.3.0+)

- **`db:list`** — prints **Tables** and **Views** sections with `Table:` / `View:` labels and column summaries. Empty DB: `No tables or views found…`.
- **`db:describe Name`** — works on a base table or view. Views get header `VIEW:` and a **VIEW DEFINITION** section. Indexes / FKs are soft-empty for views; on SQLite, indexes / FKs / stats / options are soft-empty so describe always completes.
- **`db:drop Name`** — connects, resolves relation kind, then confirms (VIEW- vs TABLE-specific warning). Uses `DROP VIEW` for views and `DROP TABLE` for tables. Pass `--force` to skip confirmation (preferred for automation). The name argument is normalized with `strtolower` (same as before 1.3.0); avoid relying on mixed-case identifiers when dropping on Postgres/SQLite.

Pairs with `gemvc/library` **^5.11** `ViewTable` + `db:migrate` for creating views. This package does **not** hard-require library dialects; it uses raw SQL + `resolveDriver()`.

### Other limitations

- SQLite has no native `ALTER TABLE ... ADD CONSTRAINT`; `db:unique` falls back to `CREATE UNIQUE INDEX`.
- On PostgreSQL/SQLite, `db:drop` table preview is a simplified column list (not a full `CREATE TABLE` reconstruction). Views preview via view definition SQL when available.

**Production migrations** (`db:migrate`) remain in `gemvc/library`:

```bash
vendor/bin/gemvc db:migrate UserTable
vendor/bin/gemvc db:migrate UserOrderSummaryViewTable
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
