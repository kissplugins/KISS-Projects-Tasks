# Roadmap

### Current PSR4 Status Summary (PAUSED/DEFERRED) 
This current Branch: `08-13-psr4-end-of-day`
- **Phase 1**: ✅ COMPLETE (pending QA)
- **Phase 2**: 🟡 70% Complete (core services done, schema hardening partial)
- **Phase 3**: 🟡 Partially done (moved ACFAdapter/wiring to Phase 2)
- **Phases 4-8**: ❌ Not started

### Pausing this branch to work on PSR4 branch + FSM on 
08-24-psr4-fsm-phase-1

## Phase 1 – Bootstrap PSR-4 Structure ✅ COMPLETE
- [x] Add Composer-based PSR-4 autoloader
- [x] Refactor main plugin bootstrap into `KISS\PTT\Plugin`
- [x] Move time calculation logic into `KISS\PTT\Time\Calculator`
- [x] Wrap existing helper functions to call namespaced classes
- [ ] Human QA Testing - **PENDING: Needs comprehensive testing of PSR-4 conversion**

**Notes:** Core PSR-4 structure is working in production. All procedural functions properly delegate to namespaced classes. TimerService is actively used in today.php.

## Phase 2 – Core Domain & Storage (70% Complete)
- [ ] CPT & Taxonomy audit (Core) - **NEEDS REVIEW**
  - [ ] Confirm project_task args/capabilities; map custom capabilities if needed
  - [ ] Verify taxonomy bindings (client, project, task_status) and UI visibility
- [ ] ACF Field schema hardening (Core) - **PARTIALLY DONE**
  - [x] Lock field keys/types for: sessions repeater (session_start_time, session_stop_time, session_manual_override, session_manual_duration, session_title, session_notes) - **DONE: See FieldGroups.php**
  - [x] Parent-level fields (start_time, stop_time, manual_override, manual_duration), calculated_duration - **DONE: See FieldGroups.php**
  - [ ] Document key->name mapping; add migration notes if any keys change
  - [x] Create ACFAdapter (src/Integration/ACF/ACFAdapter.php)
  - [x] Create SessionRepository (src/Domain/Session/SessionRepository.php)
  - [x] Create TimerService skeleton (src/Domain/Timer/TimerService.php) - **FULLY IMPLEMENTED, not just skeleton**

  - [x] Register local ACF field groups for clean installs (src/Integration/ACF/FieldGroups.php)

- [x] Session Domain & Repository (Core) - **IMPLEMENTED**
  - [x] src/Domain/Session/SessionRepository (minimal-read access; avoid full repeater hydration)
  - [x] Invariants: one active session per user; prevent overlaps; deterministic ordering - **DONE: See TimerService validation logic**
  - [x] Time normalization (UTC) and rounding rules in one place - **DONE: ACFAdapter.normalizeUtc() + Calculator**
- Decision (low‑risk for finish line): Register services directly on Plugin; defer Service Locator to Post‑Project

- [x] Timer Orchestration (Core) - **COMPLETE**
  - [x] src/Domain/Timer/TimerService for start/stop/resume transitions and validation
  - [x] Hooks for auditing: ptt_session_started/resumed/stopped
  - [x] Wire ACFAdapter/SessionRepository/TimerService directly on Plugin (low‑risk)
  - [x] Route start‑timer flow through TimerService - **DONE: See today.php line 719**
  - [x] Add stopActive() and resume() to TimerService; enforce no overlapping sessions per task
  - [x] Replace existing stop calls in start flows with TimerService->stopActive() - **DONE: See today.php lines 309, 766**

**Notes:** Core domain services are fully functional and integrated. TimerService enforces all required invariants. Missing: formal service locator pattern (deferred to Phase 3).



## Phase 3 – Service Seams under PSR‑4 (Not Started)
- [ ] src/Support/Services locator - **DEFERRED: Current direct registration on Plugin works well**
- [x] src/Integration/ACF/ACFAdapter (field‑key access, UTC conversions) - **DONE: Moved to Phase 2**
- [x] Wire repositories/services in Plugin::init/boot - **DONE: See Plugin::init() lines 20-22**

**Notes:** ACFAdapter and service wiring were completed as part of Phase 2. Service locator pattern is optional enhancement.

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

**Notes:** Self-test system provides basic validation. Comprehensive unit testing still needed.

### Sequencing Notes
- ✅ **Phase 2 (Core Domain & Storage) is substantially complete** - Timer orchestration and domain services are working
- Today UI integration is already functional (see today.php using TimerService)
- Centralizing ACF access behind SessionRepository/ACFAdapter makes Phase 4 (CPT or table) a drop‑in change later

### Current Status Summary (Updated)
- **Phase 1**: ✅ Complete (pending QA)
- **Phase 2**: 🟡 70% Complete (core services done, schema hardening partial)
- **Phase 3**: 🟡 Partially done (moved ACFAdapter/wiring to Phase 2)
- **Phases 4-8**: ❌ Not started

### Next Priority Actions
1. **Complete Phase 1**: Run comprehensive QA testing of PSR-4 conversion
2. **Finish Phase 2**: Document ACF field mappings, complete CPT/taxonomy audit
3. **Consider Phase 4**: Evaluate session storage promotion (CPT vs custom table)
