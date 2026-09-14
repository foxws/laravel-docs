---
title: Introduction
metadata:
  role: Documentation
  eyebrow: "Data layer · Markdown · GitHub"
  desc: "Pull a package's docs/*.md folder from GitHub into queryable Eloquent models."
---

# Laravel Docs

A headless package that pulls a package's `docs/*.md` folder — from GitHub, or
a local folder for a project with no repository of its own yet — into
queryable Eloquent models. There is no bundled frontend or theme — the
consuming application renders the UI against the `Project` and `Document`
models this package provides.

## Contents

- [Installation](installation.md)
- [Configuration](configuration.md)
- [Registering projects and versions](registering-projects.md)
- [Syncing](syncing.md)
- [Models](models.md)
- [Search](search.md)
- [Upgrading](upgrading.md)

## Scope

This version only pulls per-package "project" docs (a `docs/` folder living in
each package's own repository). A future version may add a second content
type for site-wide static pages (home, about, etc.) sourced from a single
shared repository — see [upgrading.md](upgrading.md).
