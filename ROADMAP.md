# Roadmap

## Human QA Testing
Missing: A comprehensive end-to-end test that simulates real user workflow.

My Thoughts on Your "Special 15 Minute End to End Test" Idea
This is an excellent idea for several reasons:

Real-world validation - Tests the actual user journey, not just isolated functions
User confidence - Gives users a way to verify everything works before using it for real work
Debugging aid - When users report issues, you can ask them to run this test first
Demo functionality - New users can see how the plugin works without creating real data
Regression testing - After updates, users can quickly verify nothing broke
Proposed Implementation
I suggest creating a "Demo & Test" page that:

Opens in a new tab (as you suggested) to avoid disrupting their current work
Creates temporary demo data with clear naming (e.g., "DEMO - Client ABC", "DEMO - Project XYZ")
Walks through the complete workflow:
Create a client
Create a project under that client
Create a task under that project
Start a timer session
Stop the timer after a few seconds
Show the results on the Today page
Provides step-by-step feedback showing what's happening
Cleans up after itself (with user confirmation)
Includes a "Skip to Results" option for quick validation
Would you like me to implement this "15 Minute End to End Test" page? I can:

Create a new admin page accessible from the Tasks menu
Build an interactive test that guides users through the complete workflow
Make it open in a new tab as you suggested
Include real-time progress indicators and validation
Add cleanup functionality to remove demo data when done
This would complement the existing self-tests perfectly - the current tests validate the technical foundation, while this new test would validate the user experience.

## NEXT MAJOR PROJECT: FSM

- Objective: Introduce a Finite State Machine (FSM) architecture for the Today page to improve reliability, debuggability, and code clarity.
- Plan: See PROJECT-FSM.md for the phased, actionable checklist (TimerFSM + DataFSM, controller, effects, rollout plan).
- Status: Planning complete; next step is Phase 0 (Preparation) and Phase 1 (TimerFSM pilot behind feature flag).


## Near‑Term Priorities (August 2025)

1) Ship two targeted bug fixes before further PSR‑4 work
   - Reports → Classic: date‑range filtering can miss expected rows due to UTC/local parsing of session_start_time. Fix by normalizing to UTC during comparisons.
   - Today → Quick Start: duplicate entries appear because the page shows a task‑level "created" entry and a session entry on the same day. Suppress the task‑level entry when a same‑day session exists.

2) Resume incremental PSR‑4 tasks after the fixes
   - Keep Plugin as the simple service container for now.
   - Add UTC/date helpers to ACFAdapter and route reporting/TODAY comparisons through those (no UI refactor).

3) QA/User Validation Enhancements
   - Deferred ~1 week: Implement the "15 Minute End‑to‑End Test" Demo page that opens in a new tab and cleans up after itself (preferred workflow test).
   - Add a quick admin command/button to run Self‑Tests and display results inline, with a link to detailed logs.

4) PSR‑4 Hardening for Today Helpers
   - [x] Split TodayHelpers classes (EntryRenderer, DataProvider, PageManager) into separate files to align with Composer’s PSR‑4 expectations and remove explicit requires.
   - [x] Retired explicit include in Plugin::init; Composer autoload now loads classes.
   - [x] Removed the TodayHelpers placeholder class/file.
   - [ ] Consider deprecating today-helpers-compat.php wrappers (add notices behind a flag) once plugin usage migrates to PSR‑4 everywhere.

5) Compatibility & Hooks
   - Call TodayController::register() directly from Plugin::init; gradually retire today-compat hook registrations.
   - Keep legacy wrappers for a deprecation window and add _doing_it_wrong() notices behind a flag.

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
- [ ] Human QA Testing (End‑to‑End): Provide "15 Minute E2E Test" page that opens in a new tab and cleans up after itself (Deferred ~1 week)


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
