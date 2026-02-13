# Decisions

## Framework
Symfony latest stable + Twig.

## Local development
No Docker. SQLite for local dev.

## Alias format (tentative)
Readable word + short suffix, e.g. river-9k2f@hapisheets.com

## Domain
Domain (e.g. hapisheets.com) is a config value, not stored in DB. Single-domain MVP; `local_part` is unique globally.

## Alias enabled flag
Keep `enabled` as boolean. When disabled: alias is hidden/disabled in UI and **reject mail later** (inbound handling will use this).
