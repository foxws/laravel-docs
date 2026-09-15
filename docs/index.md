---
title: Introduction
metadata:
  role: Documentation
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

## Scope

This version only pulls per-package "project" docs (a `docs/` folder living in
each package's own repository). A future version may add a second content
type for site-wide static pages (home, about, etc.) sourced from a single
shared repository — see [upgrading.md](upgrading.md).
