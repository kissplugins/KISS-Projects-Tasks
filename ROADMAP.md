## Priority Plan (PSR‑4 → TypeScript → FSM)

- PSR‑4 (one more pass — best bang for buck)
  - [ ] Migrate Reports to PSR‑4 (Presentation\Reports: Controller/Service)
  - [ ] Fix Reports date‑range UTC normalization during the move
  - [ ] Register TodayController::register() from Plugin::init (avoid double hooks with compat)
  - [ ] Optional: Add deprecation flag for today‑helpers‑compat wrappers (log _doing_it_wrong when enabled)

- TypeScript (Today page pilot — highest UX/stability ROI)
  - [ ] Add minimal build (esbuild or Vite) with strict tsconfig
  - [ ] Define types: Entry, Session, TimerAction; typed fetch/AJAX client
  - [ ] Implement typed modules: start/stop/resume controls; move‑session flow; inline duration editing
  - [ ] Scope bundle to Today admin page; graceful fallback; add a few Vitest tests

- FSM (after TS pilot)
  - [ ] Integrate TimerFSM with typed controls behind a feature flag
  - [ ] Add lightweight metrics/logging for state transitions
  - [ ] Plan DataFSM next (read model, cache invalidation)


# Roadmap

## Human QA Testing
- [ ] 15 Minute E2E Demo/Test page (Deferred ~1 week)
  - [ ] Opens in a new tab; creates demo data; cleans up after confirmation
  - [ ] Walkthrough: client → project → task → start/stop → verify Today page
  - [ ] Step-by-step feedback and a Skip-to-Results option

## NEXT MAJOR PROJECT: FSM
- [ ] Introduce Today page FSM to improve reliability and debuggability
- [ ] See PROJECT-FSM.md for detailed phases (TimerFSM + DataFSM, controller/effects)
- [ ] Next: Phase 0 prep and Phase 1 pilot behind a feature flag


## Near‑Term Priorities (August 2025)
- [ ] Reports: Fix date‑range UTC normalization (best bang for buck)
- [ ] Reports: Migrate to PSR‑4 (Controller/Service), apply the fix during migration
- [ ] Today: Call TodayController::register() from Plugin::init (avoid double hooks)
- [ ] Optional: Deprecation flag for today‑helpers‑compat wrappers
- [ ] QA: Quick admin action to run Self‑Tests and show results inline
- [ ] QA: 15 Minute E2E Demo/Test page (Deferred ~1 week)

Notes on FSM and PSR‑4
- The Today‑page FSM (TimerFSM/DataFSM) is an independent UI/controller improvement. It is not required to ship the two bug fixes above.
- FSM will live under src/Presentation/Today/ (controller + effects) and can be introduced behind a feature flag after PSR‑4 Phase 2 hardening steps.
- Completing PSR‑4 phases helps FSM adoption (clean seams), but FSM is not a blocker for current bug fixes.



## Phase 1 – Bootstrap PSR-4 Structure
- [x] Add Composer-based PSR-4 autoloader
- [x] Refactor main plugin bootstrap into `KISS\PTT\Plugin`
- [x] Move time calculation logic into `KISS\PTT\Time\Calculator`
- [x] Wrap existing helper functions to call namespaced classes
- [x] Self-Tests suite stabilized (67/67 passing)
- [ ] Human QA Testing (15 Minute E2E Demo/Test page) — Deferred ~1 week


## Phase 2 – Core Domain & Storage (High Priority)
- [ ] CPT & Taxonomy audit (Core)
  - [ ] Confirm project_task args/capabilities; map custom capabilities if needed
  - [ ] Verify taxonomy bindings (client, project, task_status) and UI visibility
- [ ] ACF Field schema hardening (Core)
  - [ ] Lock field keys/types for: sessions repeater (session_start_time, session_stop_time, session_manual_override, session_manual_duration, session_title, session_notes)
  - [ ] Parent-level fields (start_time, stop_time, manual_override, manual_duration), calculated_duration
  - [ ] Document key->name mapping; add migration notes if any keys change
  - [x] Create ACFAdapter (src/Integration/ACF/ACFAdapter.php)
  - [x] Create SessionRepository (src/Domain/Session/SessionRepository.php)
  - [x] Create TimerService skeleton (src/Domain/Timer/TimerService.php)
  - [x] Diagnostics: ACF schema checks and admin warnings (src/Integration/ACF/Diagnostics.php)

  - [x] Register local ACF field groups for clean installs (src/Integration/ACF/FieldGroups.php)

### ACF Field Schema (Authoritative Mapping)

Parent Task fields (group_ptt_task_fields):
- field_ptt_start_time → name: start_time → type: date_time_picker (Y-m-d H:i:s)
- field_ptt_stop_time → name: stop_time → type: date_time_picker (Y-m-d H:i:s)
- field_ptt_calculated_duration → name: calculated_duration → type: text (read-only)
- field_ptt_manual_override → name: manual_override → type: true_false (ui)
- field_ptt_manual_duration → name: manual_duration → type: number
- field_ptt_task_max_budget → name: task_max_budget → type: number
- field_ptt_task_deadline → name: task_deadline → type: date_time_picker (Y-m-d H:i:s)
- field_ptt_sessions → name: sessions → type: repeater

Sessions repeater sub-fields:
- field_ptt_session_title → name: session_title → type: text
- field_ptt_session_notes → name: session_notes → type: textarea
- field_ptt_session_start_time → name: session_start_time → type: date_time_picker (Y-m-d H:i:s)
- field_ptt_session_stop_time → name: session_stop_time → type: date_time_picker (Y-m-d H:i:s)
- field_ptt_session_manual_override → name: session_manual_override → type: true_false (ui)
- field_ptt_session_manual_duration → name: session_manual_duration → type: number
- field_ptt_session_calculated_duration → name: session_calculated_duration → type: text (read-only)
- field_ptt_session_timer_controls → name: session_timer_controls → type: message (JS renders timer UI)

Notes:
- Date fields use display_format and return_format: Y-m-d H:i:s
- Timer controls field is a message field; UI is injected via JS; content placeholder is non-semantic.

### Migration Notes

If your site has different field keys or names:
1) Prefer renaming via ACF UI to match the keys above, or programmatically register local groups (FieldGroups.php) and export/import.
2) If only names differ but keys match, update names to match the canonical mapping to avoid code depending on names diverging.
3) If keys differ and you cannot rename, create a small compatibility map in ACFAdapter to resolve to canonical keys; long-term recommendation is to normalize keys.
4) Diagnostics (src/Integration/ACF/Diagnostics.php) will emit admin warnings when keys, names, or types do not match.


- [ ] Session Domain & Repository (Core)
  - [x] src/Domain/Session/SessionRepository (minimal-read access; avoid full repeater hydration)
  - [ ] Invariants: one active session per user; prevent overlaps; deterministic ordering
  - [ ] Time normalization (UTC) and rounding rules in one place
- Decision (low‑risk for finish line): Register services directly on Plugin; defer Service Locator to Post‑Project

- [ ] Timer Orchestration (Core)
  - [x] src/Domain/Timer/TimerService for start/stop/resume transitions and validation
  - [x] Hooks for auditing: ptt_session_started/resumed/stopped
  - [x] Wire ACFAdapter/SessionRepository/TimerService directly on Plugin (low‑risk)
  - [x] Route start‑timer flow through TimerService
  - [x] Add stopActive() and resume() to TimerService; enforce no overlapping sessions per task
  - [x] Replace existing stop calls in start flows with TimerService->stopActive()



## Phase 3 – Service Seams under PSR‑4 (In Progress)
- [ ] src/Support/Services locator
- [x] src/Integration/ACF/ACFAdapter (field‑key access, UTC conversions) — partial in place
- [x] Wire repositories/services in Plugin::init (ACFAdapter, SessionRepository, TimerService)

## Phase 4 – Session Storage Promotion (Decision Point)
Option B1 – Session CPT (ptt_session)
- [ ] Register CPT and link to task (parent/meta); add indexes
- [ ] Migration tool: ACF repeater rows -> CPT posts
- [ ] Swap SessionRepository to CPT queries; update services
- [ ] Expose REST for CPT; permissions and capability mapping

Option B2 – Custom table (wp_ptt_sessions)
- [ ] Table schema and install/upgrade routine
- [ ] Repository CRUD + indexed queries
- [ ] Migration tool from ACF repeater
- [ ] Update services to use table queries

## Phase 5 – ACF Performance and Reliability (Phase A)
- [ ] Minimal-read patterns for ACF (avoid full repeater hydration)
- [ ] Introduce per-row session_uuid and update-by-UUID semantics
- [ ] 60s cache for TodayService; invalidate on acf/save_post of affected tasks
- [ ] Manual-override precedence and UTC normalization in the domain layer
- [ ] Instrument timings of get_field/update_field and track improvements

## Phase 6 – Today UI and Controllers (Lower Priority)
- [x] Migrate Today page data/renderer into classes (Presentation\Today) — implemented via PSR‑4 classes with compatibility wrappers
- [x] Centralize AJAX callbacks in Plugin::init via namespaced callables — TodayController provides PSR‑4 endpoints; compat file registers procedural handlers
- [ ] Replace procedural handlers with service calls; defer UI polish to later
- [x] Split TodayHelpers classes into separate files and remove explicit requires (see Near‑Term Priority 4)
- [x] Remove TodayHelpers placeholder class/file

## Phase 7 – Public API and Extensibility
- [ ] Enable show_in_rest for CPT/taxonomies (read-only), or
- [ ] Register curated REST endpoints under /ptt/v1 for session/task operations
- [ ] Add extension hooks (actions/filters): ptt_session_saved, ptt_today_entries_built

## Phase 8 – QA, Testing, and Metrics (Ongoing)
- [ ] PHPUnit setup; unit tests for SessionRepository/TimerService and TodayService
- [ ] E2E smoke tests for key flows (start/stop, Today load)
- [ ] Performance budgets; track Today render and session CRUD latency

### Sequencing Notes
- Focus Phase 2 (Core Domain & Storage) first; Today UI is intentionally deferred to Phase 6.
- Centralizing ACF access behind SessionRepository/ACFAdapter makes Phase 4 (CPT or table) a drop‑in change later.
