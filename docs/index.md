---
title: Introduction
metadata:
  role: Documentation
  group: foundations
  eyebrow: "Data layer · Markdown · GitHub"
  desc: "Pull a package's docs/*.md folder from GitHub into queryable Eloquent models."
  requires: "PHP ^8.3"
  laravel: "12.x / 13.x"
  licence: MIT
---

# Laravel Docs

A headless package that pulls a package's `docs/*.md` folder — from GitHub, or
a local folder for a project with no repository of its own yet — into
queryable Eloquent models. There is no bundled frontend or theme — the
consuming application renders the UI against the `Project` and `Document`
models this package provides.

> [!TIP]
> Let your AI agent do the hard parts. This package ships a [Laravel Boost](https://laravel.com/docs/boost) skill, `docs-development`, that teaches your agent how to install, configure and use Laravel Docs. Install Boost and select the skill when prompted:
>
> ```bash
> composer require laravel/boost --dev
> php artisan boost:install
> ```
>
> Already using Boost? Run `php artisan boost:update --discover` to pick up the skill. Then just ask, for example: *"Register foxws/laravel-ddd as a project and sync its docs every day."*

## Scope

This version only pulls per-package "project" docs (a `docs/` folder living in
each package's own repository). A future version may add a second content
type for site-wide static pages (home, about, etc.) sourced from a single
shared repository — see [upgrading.md](upgrading.md).
