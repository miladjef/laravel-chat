# Changelog

## 2026-10-04

Programmer: Miladjef

- Added trusted host and trusted proxy configuration.
- Added temporary-presence heartbeat and stale-user protection.
- Added normalized display names and collision-safe migration.
- Removed avatar data from user records, presence payloads and message payloads.
- Added short-lived cached message history.
- Added server timestamps and bounded chat DOM retention.
- Moved broadcasts to the queue-ready `ShouldBroadcast` path.
- Added production CSP, noindex headers and no-store caching for chat pages.
- Added database, cache and optional Reverb readiness checks.
- Added independent Docker services for Reverb, queue worker and scheduler.
- Added Larastan, Playwright E2E tests and GitHub Actions CI.
- Added bot honeypot and registration timing guard.
- Updated privacy wording from anonymous identity to temporary identity.
