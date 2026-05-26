<p align="center"><a href="https://inventorix.noix.dev/" target="_blank"><img src="https://github.com/NoiXdev/inventorix/blob/develop/public/asset/logo/header.png?raw=true" width="400" alt="Inventorix Logo"></a></p>

<p align="center">
  <a href="https://github.com/NoiXdev/inventorix/actions/workflows/ci.yml"><img src="https://github.com/NoiXdev/inventorix/actions/workflows/ci.yml/badge.svg" alt="CI"></a>
  <a href="https://hub.docker.com/r/noixdev/inventorix"><img src="https://img.shields.io/docker/pulls/noixdev/inventorix.svg?logo=docker&logoColor=white" alt="Docker Pulls"></a>
  <a href="https://hub.docker.com/r/noixdev/inventorix"><img src="https://img.shields.io/docker/v/noixdev/inventorix?sort=semver&logo=docker&logoColor=white&label=docker" alt="Docker Image Version"></a>
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-MIT-blue.svg" alt="License: MIT"></a>
  <img src="https://img.shields.io/badge/PHP-8.4-777BB4.svg" alt="PHP 8.4">
  <img src="https://img.shields.io/badge/Laravel-13-FF2D20.svg" alt="Laravel 13">
  <img src="https://img.shields.io/badge/Filament-5-F59E0B.svg" alt="Filament 5">
</p>

## About Inventorix

Inventorix is a self-hostable inventory management application built on
Laravel 13 and Filament 5. It's designed for small teams that need to track
assets, locations, and movements with a clean admin UI and SSO out of the box.

Highlights:

- Filament v5 admin panel
- QR-code generation for assets
- PDF export (DomPDF)
- Microsoft Entra ID (Azure AD) SSO via Socialite
- Activity log + tags via the Spatie ecosystem
- Production runtime: Laravel Octane on FrankenPHP
- Background jobs via Laravel Horizon

## Quickstart (Docker)

```bash
docker run -d \
  -p 8000:8000 \
  -e APP_KEY=base64:$(openssl rand -base64 32) \
  -e APP_ENV=production \
  -e APP_DEBUG=false \
  -e DB_CONNECTION=mysql \
  -e DB_HOST=... \
  -e DB_DATABASE=inventorix \
  -e DB_USERNAME=... \
  -e DB_PASSWORD=... \
  -e RUN_MIGRATIONS=true \
  noixdev/inventorix:latest
```

The image runs the entrypoint, waits for the database, runs migrations
(if `RUN_MIGRATIONS=true`), warms caches, and starts Octane on port 8000.

See [`docker/entrypoint.sh`](docker/entrypoint.sh) and the production env vars
documented in [`.env.example`](.env.example).

## Local development (DDEV)

```bash
ddev start
ddev composer install
ddev exec pnpm install
ddev artisan migrate --seed
ddev exec pnpm run dev
```

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

## Code of Conduct

This project follows the [Contributor Covenant v2.1](CODE_OF_CONDUCT.md).

## Security

Report vulnerabilities privately — see [SECURITY.md](SECURITY.md).

## License

Inventorix is open-source software licensed under the [MIT license](LICENSE).
