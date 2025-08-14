# Roadmap

## Phase 1 – Bootstrap PSR-4 Structure
- [x] Add Composer-based PSR-4 autoloader
- [x] Refactor main plugin bootstrap into `KISS\PTT\Plugin`
- [x] Move time calculation logic into `KISS\PTT\Time\Calculator`
- [x] Wrap existing helper functions to call namespaced classes
- [ ] Human QA Testing - Not done yet


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

- [ ] Session Domain & Repository (Core)
  - [ ] src/Domain/Session/SessionRepository (minimal-read access; avoid full repeater hydration)
  - [ ] Invariants: one active session per user; prevent overlaps; deterministic ordering
  - [ ] Time normalization (UTC) and rounding rules in one place
- Decision (low‑risk for finish line): Register services directly on Plugin; defer Service Locator to Post‑Project

- [ ] Timer Orchestration (Core)
  - [ ] src/Domain/Timer/TimerService for start/stop/resume transitions and validation
  - [ ] Hooks for auditing: ptt_session_started/updated/stopped
  - [x] Wire ACFAdapter/SessionRepository/TimerService directly on Plugin (low‑risk)
  - [x] Route start‑timer flow through TimerService


## Phase 3 – Service Seams under PSR‑4 (In Progress)
- [ ] src/Support/Services locator
- [ ] src/Integration/ACF/ACFAdapter (field‑key access, UTC conversions)
- [ ] Wire repositories/services in Plugin::init/boot

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
- [ ] Migrate Today page data/renderer into classes (Presentation\Today)
- [ ] Centralize AJAX callbacks in Plugin::init via namespaced callables
- [ ] Replace procedural handlers with service calls; defer UI polish to later

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
