# Roadmap

## NEXT MAJOR PROJECT: FSM

- Objective: Introduce a Finite State Machine (FSM) architecture for the Today page to improve reliability, debuggability, and code clarity.
- Plan: See PROJECT-FSM.md for the phased, actionable checklist (TimerFSM + DataFSM, controller, effects, rollout plan).
- Status: Planning complete; next step is Phase 0 (Preparation) and Phase 1 (TimerFSM pilot behind feature flag).



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
