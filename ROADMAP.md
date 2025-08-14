# Roadmap

## Phase 1 – Bootstrap PSR-4 Structure
- [x] Add Composer-based PSR-4 autoloader
- [x] Refactor main plugin bootstrap into `KISS\PTT\Plugin`
- [x] Move time calculation logic into `KISS\PTT\Time\Calculator`
- [x] Wrap existing helper functions to call namespaced classes

## Phase 2 – Convert Remaining Modules
- [ ] Migrate Reports and Today pages to classes
- [ ] Refactor remaining procedural logic from `legacy-core.php`

## Phase 3 – Finalize PSR-4 Migration
- [ ] Remove legacy wrappers and global functions
- [ ] Add comprehensive tests for new class architecture
