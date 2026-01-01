# Project & Task Time Tracker — PRD and Rebuild Plan

## Purpose
Rebuild the time-tracking experience with a single-screen UI, centralized UTC time handling, and modular architecture that is easy to extend and test. Keep scope tight with phased delivery and mock data so flows can be validated immediately.

## Previous Project's Pain Points to Avoid for this round:
- Mixed procedural/OO pathways, static globals, and duplicated time logic cause brittle behavior.
- ACF repeater dependence limits queryability and can drift from domain invariants.
- Inconsistent task visibility logic across legacy code paths.
- Time handling is not fully centralized (multiple clocks/formatters).
 - Hard to test flows without seed data; waiting for real time to pass slows verification.

## Goals (high level)
- Single time provider (UTC storage, site-local display) used everywhere; mockable clock for tests.
- Clear layering: Domain (pure PHP), Application (use-cases/policies), Infrastructure (WP adapters), Presentation (single-screen UI first), Compatibility (thin shims only).
- Consistent task/session storage with an explicit choice: ACF repeater (short term) or custom table (preferred for queryability) behind a repository interface.
- Mock data generator to seed realistic past sessions/tasks for instant testing.
- Robust self-tests for time, sessions, visibility, and UI flows.

## User Capabilities 
WP Admin users can define Clients, Projects, and Tasks using WP taxonomy CPTs. Admins can define and edit a max hourly budget for each Project.

All Users (WP admins, editors, and users) can select a project to start/pause/stop task timers while they are working, view task lists, and see reports. Users can go over budget but the displays would warn the users and also be called out on reports.

All Users can view reports of their time tracking activities by Project, Client, or Task by date range.

Additional capabilities/clarifications to cover:
- WP Admin/Editor can bulk-import or seed mock projects/tasks/sessions (dev-only) for testing; non-admins cannot seed mock data.
- WP Admin/Editor can edit or delete sessions (with audit note); standard users can edit their own session titles only, not timestamps.
- WP Admin can configure per-project budget warnings and hard/soft limits; soft limits warn, hard limits block new sessions (configurable).
- All users see one active session per user guardrail; starting a new timer stops any existing active timer for that user.
- WP Admin can export reports (CSV) filtered by date range/client/project; standard users can export their own data only.
- All users see local-time display with UTC storage; timezone is consistent and shared formatting is used across UI and reports.

## Non-Goals (for now)
- Multi-screen UX; keep a single consolidated UI surface.
- Broad feature creep beyond core time capture, display, reporting, and integrity.

## Architecture Direction
- **Domain**: Entities/VOs for Session, TaskSummary; `TimerService`, `Calculator`, `TimeProvider` interface.
- **Infrastructure**: `ACFSessionRepository` or `DBSessionRepository` (custom table) behind a `SessionRepository` interface; `ACFAdapter`; `Hypercart_Time` (or equivalent) implementing `TimeProvider` for UTC clock + local formatting.
- **Application**: Use-case layer for start/stop/resume, recalc, task visibility, mock data seeding; capability checks; validation.
- **Presentation**: Single-screen UI (tasks list + detail + controls) consuming use-cases; shared formatting helpers only.
- **Compatibility**: Procedural shims delegating to application layer until fully removed.

## Data Storage Strategy
- Option A (faster start): keep ACF repeater but wrap all access in `SessionRepository` and calculator; forbid direct field access elsewhere.
- Option B (preferred for rebuild): introduce a custom `ptt_sessions` table via `DBSessionRepository`; keep a compat read/write adapter if ACF repeater must coexist during migration.
- Choose per phase: start with the repository interface so storage can be swapped without UI/domain changes.

## Time Handling Policy
- Single `TimeProvider` (UTC now, parsing, formatting; site-local display via wp_date). Storage in UTC, display via converter. Tests and mock data generator can mock time.
- All date rendering flows through shared helpers (no direct `wp_date()`/`time()` in feature code).

---

## Phased Plan (narrow scope, low conflation)

### Phase 1 — Foundations: Time, Contracts, Mock Data
- [ ] Introduce `TimeProvider` interface implemented by `Hypercart_Time` (UTC now + local display; mockable).
- [ ] Wire `ACFAdapter` (or replacement adapter) to use `TimeProvider` for all time reads/formatting.
- [ ] Ensure `TimerService`/`Calculator` consume `TimeProvider`; remove direct `time()/wp_date()` in domain.
- [ ] Add mock data generator: create projects/tasks/sessions in the past for quick end-to-end testing.
- [ ] Add self-test covering UTC storage, local display, and mockable clock + seeded data sanity.

### Phase 2 — Storage Choice & Repository Discipline
- [ ] Define `SessionRepository` interface; provide `ACFSessionRepository` and a stub `DBSessionRepository` (custom table) behind feature flag.
- [ ] Route all session reads/writes through the repository; forbid direct ACF field access.
- [ ] Add bounds/shape validation in repository to prevent malformed rows.
- [ ] Add logging/hooks for session mutations for diagnostics.
- [ ] Decide storage mode for rebuild (ACF vs DB) and document the flag/default.

### Phase 3 — Single-Screen UI MVP
- [ ] Build single-page admin screen: task list (filters), task detail pane, start/stop/resume controls, session timeline.
- [ ] Wire UI to `TimerUseCases` and shared formatting helpers (UTC storage, local display).
- [ ] Integrate mock data generator trigger from UI (dev-only capability) to seed past sessions.
- [ ] Add capability checks and guards (one active session per user across tasks).
- [ ] UI regression self-tests hitting start/stop/resume and display of seeded past data.

### Phase 4 — Reporting & Visibility Consistency
- [ ] Introduce `TaskQueryService` for consistent visibility/meta/taxonomy filters; update UI/reporting to use it.
- [ ] Add summary/report widgets on the single screen (totals by client/project/status) using the same queries.
- [ ] Add self-tests for visibility per user/capability and taxonomy filters.
- [ ] Remove duplicate time formatting in templates; rely on centralized helper.
- [ ] Deprecate procedural shims once UI/reporting paths are migrated.

### Phase 5 — Optional: Custom Session Table Go-Live
- [ ] Finalize `ptt_sessions` schema and migration (dual-write or one-time) under a feature flag.
- [ ] Implement full `DBSessionRepository` and flag-driven switch; keep read compatibility during rollout.
- [ ] Add integrity self-tests against both backends (flag-driven) and migration verification.
- [ ] Remove remaining direct ACF repeater dependence from core paths when flag is on; keep legacy read only if needed for display.
- [ ] Load/perf test the new backend; document rollback/disable steps.

---

## Success Criteria (per phase summary)
- Single time authority in use across domain/app layers.
- No direct ACF field access outside repositories/adapters.
- Legacy procedural paths delegate; no duplicate business logic.
- Self-tests cover time, sessions, visibility, and core flows.
- Optional DB backend can be enabled without breaking existing installs.
