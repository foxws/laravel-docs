# Laravel Docs

This repository is a Laravel package. Keep the package focused, idiomatic, and easy for Laravel developers to install, test, and maintain.

## Package Conventions

- Use Laravel-native package APIs and the existing service provider shape before adding abstractions.
- Keep package names, namespaces, Composer metadata, publish tags, documentation, and examples aligned with `foxws/laravel-docs`.
- Add only the files and dependencies needed for the package behavior being implemented.
- Prefer explicit Laravel package code over helper abstractions unless the extension point is real.
- Keep tests focused on observable package behavior through public APIs, service provider wiring, commands, and published resources.
- This is a headless data package — no bundled frontend/theme. The consuming application owns all rendering.
- V1 scope is per-package "project" docs only (a `docs/*.md` folder in each package's own repo). A "Page"/site-wide content type is intentionally out of scope for now.

## Quick Commands

- Full validation: `composer test`
- Formatting check: `composer lint:check`
- Static analysis: `composer analyse`
- Pest tests: `composer test:unit`
- Type coverage: `composer test:types`

## Local Skills

- `docs-development` (`resources/boost/skills/docs-development/SKILL.md`): use when integrating `foxws/laravel-docs` into a consuming Laravel application.
