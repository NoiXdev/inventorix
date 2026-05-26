# Contributing to Inventorix

Thanks for your interest in contributing.

## Local development

This project uses [DDEV](https://ddev.com/) for local development.

```bash
ddev start
ddev composer install
ddev exec pnpm install
ddev artisan migrate --seed
ddev exec pnpm run dev
```

Run all commands through DDEV (`ddev artisan`, `ddev exec pnpm …`, etc.).

## Branching

- `main` — protected, release-ready code only.
- `develop` — integration branch.
- Feature branches — created off `develop`, named `feat/<topic>` or `fix/<topic>`.

## Commit messages

We use [Conventional Commits](https://www.conventionalcommits.org/). The
`Changelog` workflow parses these to bump versions and write `CHANGELOG.md`
automatically, so the format matters.

Examples:

- `feat: add CSV export to assets`
- `fix: prevent null tenant ID from bypassing tenant check`
- `chore(deps): bump filament/filament to ^5.1`

## Pull requests

1. Create a branch off `develop`.
2. Make your change, including tests (`ddev artisan test`).
3. Run linting: `ddev exec vendor/bin/pint`.
4. Open a PR against `develop`. CI must pass (lint, PHPUnit, docker build).
5. A maintainer will review.

## Reporting security issues

See [SECURITY.md](SECURITY.md) — do **not** file public issues for
vulnerabilities.
