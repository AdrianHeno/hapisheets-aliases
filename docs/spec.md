# Hapisheets Alias Manager - MVP Spec

## Goal
A web app that lets users generate and manage email aliases under hapisheets.com for form signups and online accounts.

## MVP Features
- User can register/login
- User can create multiple aliases under their account
- User can list aliases
- User can disable an alias

## Email Features (later / in progress)
- Inbound email receiving (AWS SES) -> store messages
- **Inbox UI** — A minimal inbox (list messages per alias, view message, delete) is implemented for testing. See README and `docs/mvp-design.md` for current routes and data model.
- Optional forwarding per alias (with verification)

## Non-goals (MVP)
- Full email server implementation
- Multi-domain support
- Team accounts / sharing

## Constraints
- Symfony (latest stable), Twig
- Local dev without Docker
- AWS free tier friendly initially
