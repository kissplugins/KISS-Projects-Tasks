# Roadmap

## Phase 1 – Bootstrap PSR-4 Structure
- [x] Add Composer-based PSR-4 autoloader
- [x] Refactor main plugin bootstrap into `KISS\PTT\Plugin`
- [x] Move time calculation logic into `KISS\PTT\Time\Calculator`
- [x] Wrap existing helper functions to call namespaced classes
- [ ] Human QA Testing - Not done yet


## Phase 2 – Convert Remaining Modules (In Progress)
- [ ] Establish service seams under PSR-4
  - [ ] src/Support/Services locator
  - [ ] src/Integration/ACF/ACFAdapter
  - [ ] src/Domain/Session/SessionRepository
  - [ ] src/Presentation/Today/TodayService
- [ ] Migrate Today page data/renderer into classes (use TodayService)
- [ ] Migrate Reports into classes
- [ ] Centralize AJAX callbacks in Plugin::init via namespaced callables

## Phase 3 – Finalize PSR-4 Migration (In Progress)
- [ ] Remove legacy wrappers and global functions after services are adopted
- [ ] Consolidate hook registration in Plugin::boot
- [ ] Add comprehensive tests for new class architecture

## Phase 4 – ACF Performance and Reliability (Phase A)
- [ ] Use minimal-read patterns for Today (avoid full repeater hydration)
- [ ] Introduce per-row session_uuid and update-by-UUID semantics
- [ ] 60s cache for TodayService; invalidate on acf/save_post of affected tasks
- [ ] Manual-override precedence and UTC normalization in the domain layer
- [ ] Instrument timings of get_field/update_field and track improvements

## Phase 5 – Session Storage Promotion (Decision Point)
Option B1 – Session CPT (ptt_session)
- [ ] Register CPT and link to task (parent/meta); add indexes
- [ ] Migration tool: ACF repeater rows -> CPT posts
- [ ] Swap SessionRepository to CPT queries; update TodayService
- [ ] Expose REST for CPT; permissions and capability mapping

Option B2 – Custom table (wp_ptt_sessions)
- [ ] Table schema and install/upgrade routine
- [ ] Repository CRUD + indexed queries
- [ ] Migration tool from ACF repeater
- [ ] Update TodayService to use table queries

## Phase 6 – Public API and Extensibility
- [ ] Enable show_in_rest for CPT/taxonomies (read-only), or
- [ ] Register curated REST endpoints under /ptt/v1 for session/task operations
- [ ] Add extension hooks (actions/filters): ptt_session_saved, ptt_today_entries_built

## Phase 7 – QA, Testing, and Metrics (Ongoing)
- [ ] PHPUnit setup; unit tests for SessionRepository and TodayService
- [ ] E2E smoke tests for key AJAX flows (start/stop, Today load)
- [ ] Performance budgets; track Today page render and session CRUD latency

### Sequencing Notes
- Finish the minimal PSR-4 seams in Phase 2, then start Phase 4 immediately; you do not need to finish all of Phase 3 first.
- Centralizing ACF access behind SessionRepository/ACFAdapter makes Phase 5 (CPT or table) a drop-in change later.
