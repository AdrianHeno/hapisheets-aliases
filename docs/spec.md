# Hapisheets Alias Manager - MVP Spec

## Goal
A web app that lets users generate and manage email aliases under hapisheets.com for form signups and online accounts.

## MVP Features
- User can register/login
- User can create multiple aliases under their account
- User can list aliases
- User can disable an alias

## Email Features (later)
- Inbound email receiving (AWS SES) -> store messages
- Inbox UI to view messages
- Optional forwarding per alias (with verification)

## Non-goals (MVP)
- Full email server implementation
- Multi-domain support
- Team accounts / sharing

## Constraints
- Symfony (latest stable), Twig
- Local dev without Docker
- AWS free tier friendly initially
