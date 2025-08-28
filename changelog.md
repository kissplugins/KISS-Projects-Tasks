# Changelog

## Version 2.2.67 - Unified Timer and Session FSM
- **Refactor**: Merged TimerFSM and SessionFSM into a single TaskFSM to simplify coordination and reduce brittleness



## Version 2.2.66 - Fix SessionFSM Stuck in EDITING State
- **CRITICAL FIX**: Fixed SessionFSM getting stuck in EDITING state when clicking "Add Session" button
- **Enhanced**: Added auto-reset logic to detect when FSM is stuck in EDITING with no meaningful changes
- **Improved**: Added click-outside-session-fields handler to auto-transition back to IDLE state
- **Fixed**: "Session system is busy (EDITING)" error now automatically resolves when appropriate
- **Robust**: SessionFSM now has better recovery mechanisms to prevent getting stuck in intermediate states

## Version 2.2.65 - Route Update Button Through FSM
- **CRITICAL FIX**: Added missing event handler for ACF "Update" button to route through SessionFSM instead of bypassing it
- **Enhanced**: Update button now properly goes through EDIT_SESSION → VALIDATE_SESSION → SAVE_SESSION FSM flow
- **Improved**: Update button now respects FSM validation and state management like other session operations
- **Fixed**: Update button operations are now properly tracked and managed by the FSM system
- **Consistent**: All session operations (add, update, delete, duplicate, reorder) now go through FSM

## Version 2.2.64 - Fix Session Update Button Save Completion
- **CRITICAL FIX**: Fixed "Update" button getting stuck in "Updating..." state and triggering "Session system is busy (EDITING)" errors
- **Enhanced**: SessionEffects.saveSession() now properly waits for save completion like autoSavePost() does
- **Improved**: Added proper timeout handling (30 seconds) and ACF/WordPress save event listeners to saveSession()
- **Fixed**: Session FSM no longer gets stuck in EDITING state when save operations don't complete properly
- **Resolved**: Update button now correctly transitions from SAVING back to IDLE state after successful save

## Version 2.2.63 - Restore Safe Auto-Save Features
- **Restored**: Safe auto-save after timer start/stop - now only triggers when WordPress detects unsaved changes
- **Restored**: Safe auto-save when adding session rows - now only triggers when WordPress detects unsaved changes
- **Enhanced**: Added `ptt-saving` CSS class to prevent duplicate save triggers during auto-save operations
- **Improved**: Auto-save logic now uses `window.wp.autosave.server.postChanged()` to detect actual unsaved changes
- **Documented**: Added deferred fixes section to PROJECT-FSM.md for ACF auto-reload and session update button features
- **Safe**: Auto-save only occurs when there are genuine unsaved changes, preventing unnecessary saves

## Version 2.2.62 - Fix Publish Button Conflicts
- **CRITICAL FIX**: Disabled multiple automatic save triggers that were causing publish conflicts
- Disabled auto-save after timer start/stop to prevent draft status override
- Disabled auto-save when adding session rows to prevent interference
- Disabled ACF auto-reload after save that was causing page refresh issues
- Disabled session update button auto-save trigger
- These changes should resolve the issue where posts appear to publish but revert to draft status

## Version 2.2.61 - Fix Publish Button Interception Issue
Released: 2025-08-27
- **Fixed**: Critical issue where SessionFSM was intercepting and preventing normal WordPress publish button clicks
- **Resolved**: New posts with titles were being saved as drafts instead of published due to overly aggressive unsaved changes detection
- **Enhanced**: SessionFSM.hasUnsavedChanges() now only intercepts when there are actual SessionFSM changes, allowing normal WordPress publish flow
- **Improved**: Posts can now be properly published without SessionFSM interference when only title/content changes exist

## Version 2.2.60 - SessionFSM Auto-Idle Fix
Released: 2025-08-27
- **Fixed**: Critical issue where SessionFSM got stuck in EDITING state, preventing new session creation
- **Enhanced**: Added auto-idle mechanism that transitions back to IDLE when no meaningful changes exist
- **Improved**: SessionFSM now automatically returns to IDLE state after 1 second of inactivity with no pending changes
- **Resolved**: "Session system is busy (EDITING). Please wait and try again" error when trying to add sessions

## Version 2.2.59 - SessionFSM Promise Handling Fix
Released: 2025-08-27
- **Fixed**: TypeError in SessionController when calling transition('VALIDATE_SESSION').then() - added proper Promise handling and state checking
- **Enhanced**: Save operation now properly handles FSM state transitions without Promise errors

## Version 2.2.58 - Auto-Save Detection Fix for New Posts
Released: 2025-08-27
- **Fixed**: Critical issue where new posts weren't being saved when starting timers
- **Enhanced**: SessionFSM.hasUnsavedChanges() now detects when main post fields (title, content) need saving
- **Improved**: Timer start now properly triggers auto-save for new posts with content, preventing "Auto Draft" issues
- **Resolved**: Posts with titles/descriptions are now fully saved when timer is started, not just session data

## Version 2.2.57 - Timer UI State Fix
Released: 2025-08-27
- **Fixed**: Critical timer UI bug where RUNNING state was immediately overwritten by IDLE logic
- **Enhanced**: Added proper return statement in updateTimerUI to prevent state conflicts
- **Improved**: Timer now correctly shows "Stop Timer" button when FSM is in RUNNING state
- **Resolved**: UI now properly reflects FSM state without interference from subsequent processing

## Version 2.2.56 - Timer Persistence Debug & Fix
Released: 2025-08-27
- **Fixed**: Timer persistence across page reloads - enhanced rehydration logic with proper error handling
- **Enhanced**: Added comprehensive debugging to rehydration process for troubleshooting timer state restoration
- **Improved**: FSM rehydration now properly updates UI state after successful restoration
- **Debug**: Added console logging to track rehydration flow and identify persistence issues

## Version 2.2.55 - FSM Timer UI Constraints
Released: 2025-08-27
- **Fixed**: FSM-centric session timer constraints to prevent multiple "Start Timer" buttons
- **Enhanced**: Only the next available session can show an active "Start Timer" button, preventing user confusion
- **Improved**: Sequential session logic - sessions must be completed in order with clear messaging
- **Refined**: Timer target selection uses consistent FSM logic between UI and startTimer method

## Version 2.2.54 - FSM-Centric Auto-Save Architecture
Released: 2025-08-27
- **Architecture**: Moved auto-save logic from Effects layer to FSM coordination
- **Enhanced**: Added AUTO_SAVING state to SessionFSM for proper state management
- **Improved**: TimerFSM now coordinates with SessionFSM for auto-save before timer start
- **FSM-Centric**: Auto-save is now a proper FSM transition, not just an effect
- **Coordination**: Bidirectional FSM communication between TimerFSM and SessionFSM
- **UX**: Better state management with "Auto-saving..." feedback during the process
- **Technical**: Cleaner separation of concerns - SessionFSM handles saves, TimerFSM handles timers

## Version 2.2.53 - Auto-Save on Start Timer
Released: 2025-08-27
- **Enhanced**: Start Timer button now auto-saves WordPress post before starting timer
- **Fixed**: Prevents timer start failures due to unsaved changes
- **Improved**: Better user feedback during auto-save process ("Saving & Starting...")
- **Enhanced**: More reliable timer start process with proper state management
- **Technical**: Added comprehensive auto-save detection for WordPress and ACF changes
- **UX**: Clear progress indication during the save and start process

## Version 2.2.52 - Fixed "Updating..." Button Stuck Bug
Released: 2025-08-27
- **Fixed**: "Updating..." button no longer gets stuck when validation fails
- **Fixed**: SessionFSM now properly transitions back to EDITING state on save failures
- **Enhanced**: Added 30-second timeout to prevent indefinite "Updating..." states
- **Added**: Emergency recovery function `PTT_SessionForceReset()` for stuck states
- **Improved**: Pre-save validation prevents FSM from entering SAVING state when validation will fail
- **Technical**: Added timeout management and proper state cleanup on save operations

## Version 2.2.51 - Enhanced Session Error Messages
Released: 2025-08-27
- **User Experience**: Session error messages are now much more descriptive and actionable
- **Error Messages**: Added specific validation for user permissions and timer states
- **User Feedback**: Error dialogs now explain exactly what's wrong and how to fix it
- **Examples**: "You already have a timer running. Stop the current timer before creating new sessions."
- **Examples**: "You are not the assignee of this task, so you cannot create sessions."
- **Examples**: "Cannot move session further up - already at the top"
- **Technical**: Enhanced SessionFSM validation with descriptive error messages
- **Technical**: Added user assignee validation for session operations
- **Fixed**: Session FSM error messages now display properly instead of "[object Object]"

## Version 2.2.50 - Phase 2: Bulk Session Operations Complete + Timer Fix
Released: 2025-08-27
- Implemented comprehensive bulk session operations with BULK_PROCESSING state
- Added selection checkboxes to all session rows for multi-select functionality
- Created bulk operations toolbar with Select All/None controls and action dropdown
- Bulk Delete: Select multiple sessions and delete them all at once with confirmation
- Bulk Export: Export selected sessions to timestamped CSV files with complete data
- Smart selection UI: Dynamic count display, indeterminate checkbox states, enabled/disabled controls
- Sequential deletion algorithm: Deletes from bottom up to preserve indices during operation
- CSV export with proper escaping: Headers, session data, duration calculations, manual overrides
- Protection against bulk operations while timer is running to prevent conflicts
- Complete FSM lifecycle: IDLE → BULK_PROCESSING → IDLE with comprehensive validation
- **Fixed**: Restored missing Start Timer button in task editor when FSM is enabled
- **Fixed**: Restored missing assignee filter dropdown on All Tasks admin page
- **Fixed**: Restored assignee column sorting (ascending/descending) on All Tasks admin page
- **Added**: `src/Admin/ListTable.php` class to handle All Tasks list table functionality
- **Added**: Plugin setting to control Bulk Actions visibility (default: OFF)
- **Added**: Bulk Actions can now be toggled via Settings → Enable Bulk Actions checkbox
- **Technical**: Fixed FSM timer controls initialization to ensure Start Timer button visibility
- **Technical**: Bulk Actions UI respects PTT_BULK_ACTIONS_ENABLED flag for better UX control
- **Fixed**: Session FSM error messages now display properly instead of "[object Object]"
- **Technical**: Improved SessionFSM error handling to extract message from error objects

## Version 2.2.49 - Phase 2: Session Reordering with Conflict Detection
Released: 2025-08-27
- Implemented session reordering FSM coordination with REORDERING state
- Added contextual reorder buttons (↑↓ arrows) to all session rows
- Smart conflict detection: warns users about potential time overlaps before reordering
- Protection against reordering while timer is running to prevent index mismatches
- DOM-based reordering with automatic ACF index updates
- Complete FSM lifecycle: IDLE → REORDERING → IDLE with proper validation
- Automatic post save after reordering to persist changes immediately
- Contextual button display: first row shows only ↓, last row shows only ↑, middle rows show both

## Version 2.2.48 - Phase 2: Session Duplication with FSM Validation
Released: 2025-08-27
- Implemented session duplication FSM coordination with DUPLICATING state
- Added duplicate buttons (⧉ icon) to all session rows for easy access
- Smart data copying: preserves title, notes, manual settings while excluding timer fields
- Auto-generated unique titles with "Copy of" prefix and timestamp
- Protection against duplication while timer is running to prevent confusion
- Complete FSM lifecycle: IDLE → DUPLICATING → IDLE with proper validation
- Automatic post save after duplication to persist changes immediately
- Enhanced UI with duplicate buttons automatically added to existing and new rows

## Version 2.2.47 - Phase 2: Session Deletion FSM Coordination
Released: 2025-08-27
- Implemented session deletion FSM coordination with DELETING state
- Added protection to prevent deleting sessions with active timers
- SessionFSM now intercepts ACF delete button clicks and validates before deletion
- Added confirmation dialog and automatic post save after session deletion
- Enhanced SessionFSM with canDeleteSession() helper method
- Session deletion now goes through complete FSM lifecycle for consistency
- Added comprehensive error handling and user feedback for deletion conflicts

## Version 2.2.46 - Protected Debug Panels & Documentation
Released: 2025-08-27
- Added critical protection comments to prevent accidental removal of debug panels
- Enhanced code comments with emoji warnings and explicit product owner approval requirements
- Updated PROJECT-FSM.md with comprehensive debug panel system documentation
- Documented future plan to convert debug panels to WP admin settings UI toggles
- Added debug panel positioning, theming, and API enhancement roadmap

## Version 2.2.45 - Unified FSM Debug Panel
Released: 2025-08-27
- Consolidated SessionFSM debug output into existing Timer FSM debug panel
- Debug panel header now shows both Timer and Session states: "IDLE | Session: IDLE"
- Session events are prefixed with "[Session]" in the unified log for easy identification
- Improved developer experience with single debug panel for all FSM activity

## Version 2.2.44 - Implement SessionFSM for CPT Task Editor
Released: 2025-08-27
- Added SessionFSM to manage session lifecycle with states: IDLE, CREATING, EDITING, VALIDATING, SAVING, ERROR
- Implemented SessionEffects for ACF session row operations and validation
- Added SessionController to coordinate between SessionFSM and TimerFSM
- Session creation now prevents conflicts when timer is running
- Real-time field validation and dirty state tracking for session forms
- Auto-generated session titles with timestamp for new sessions
- Added debug panel for SessionFSM state visualization
- All session operations now go through FSM for consistent state management

## Version 2.2.43 - Fix Editor Timer Rehydration
Released: 2025-08-27
- Fixed critical FSM rehydration issue in Editor where running timers were not properly restored on page refresh
- EditorEffects.rehydrate() now correctly includes taskId in response payload to match FSM expectations
- Editor page FSM now properly transitions to RUNNING state when active timer is detected
- Timer UI correctly displays running state and active timer controls after page reload

## Version 2.2.42 - Fix Timer Rehydration for Today Page
Released: 2025-08-27
- Fixed critical timer rehydration issue where running timers were lost on page refresh
- Added missing `ptt_rehydrate_timer` AJAX endpoint for Today page FSM
- Today page now properly restores running timer state after page reload
- FSM rehydration now works consistently across both Today and Editor pages

## Version 2.2.41 - Editor UX: hide Start/Timer on saved sessions; toggle Manual Duration
Released: 2025-08-27
- Editor: If a session already has time saved (manual duration > 0 or calculated duration > 0) the default 00:00:00 and Start button are hidden. Avoids confusing controls on completed entries.
- Editor: Manual Duration field is now hidden when Manual Override is unchecked and shown when checked, without modifying ACF field settings. Pure JS/DOM toggle.

## Version 2.2.40 - Editor: show timer value before start
Released: 2025-08-27
- Editor session controls now display the 00:00:00 timer value to the left of the Start Timer button even before a session has started. This improves discoverability and preserves layout.

## Version 2.2.39 - Editor timer badge CSS fix
Released: 2025-08-27
- Fixed malformed CSS block around .ptt-session-elapsed-time which prevented the red badge background from applying on the Task Editor. The rule now closes correctly and styles take effect.
- Bumped asset version to cache-bust (PTT_VERSION=2.2.39).

## Version 2.2.38 - Admin bar fix + guard comments
Released: 2025-08-27
- Fixed PHP error in Admin Bar indicator (string concatenation in inline style now uses proper concatenation and escaping).
- Added clear DO NOT EDIT guard comments around Active Timer display styles to avoid unintended refactors.

Released: 2025-08-27
- Restored Editor timer styling to match legacy look: seven-seg red badge and red Stop button; Start button styled green.
- If session title is blank at Start, auto-fills as "Session mm-dd-yy HH:mm" (client-side) and server enforces same default.
- Added Admin Bar item: "KISS Tasks – vX.Y.Z" with a green (pass) or red (fail) dot showing last self-test summary; clicking opens Self Test and auto-runs.

## Version 2.2.37 - UI polish + defaults + admin bar indicator

## Version 2.2.36 - Editor stop persists end/duration under FSM
Released: 2025-08-26
- On Stop, EditorEffects now writes stop_time and calculated_duration into the active session row and stops the live ticker.
- Triggers a WP Update click to persist totals so end/duration are saved immediately.

Released: 2025-08-26
- Fixed timer stuck at 00:00:00 on Editor when FSM is enabled.
- Reused existing manageLiveTimer/stopLiveTimer via window.PTT helpers and invoked from EditorEffects.updateTimerUI.
- Start input is set immediately from server UTC in FSM RUNNING state for consistent display.
- Note: If you still see an admin-ajax 400, please share the failing action name from Network tab; rehydrate/start should be ptt_get_active_session_for_user and ptt_start_session_timer respectively.

## Version 2.2.35 - Editor FSM live ticking wired to shared timer

## Version 2.2.34 - Editor timer: prevent legacy handler during FSM
Released: 2025-08-26
- Fixed an issue where clicking Start Timer in the Task Editor caused a full page reload and timer loss when FSM was enabled.
- Root cause: legacy jQuery handler also ran alongside FSM and triggered reload + Update click.
- Change: legacy .ptt-session-start/.ptt-session-stop handlers now no-op when PTT_FSM_ENABLED && PTT_FSM_EDITOR_ENABLED are true.
- No UI changes; behavior now aligns with FSM and persists immediately via AJAX without reloading.

## Version 2.2.33 - All Tasks: Assignee sorting/filter restored + self-test
Released: 2025-08-26
- Restored Assignee column sorting (ASC/DESC) on All Tasks admin list
- Added Assignee filter dropdown to narrow rows by user
- Added automated self-test to verify sortable column registration and dropdown rendering

## Version 2.2.32 - FSM-centric timer persistence + UI polish
Released: 2025-08-26
- Editor: Start Timer is now routed via FSM EditorEffects/Controller when FSM is enabled, ensuring a single authoritative state machine controls timers.
- Server: ptt_start_session_timer now creates the session row server-side if the requested index doesn't exist and returns the authoritative row_index.
- Editor: Start handler sends session_title; on mismatch row_index, the UI reloads to sync with the database.
- Editor: Added ptt_get_active_session_for_user endpoint and FSM rehydrate implementation to recover running session after reload.
- Editor: Disabled Start Timer when Manual Override is checked for the session.
- UI: Align hh:mm badge inline with Total Duration input; responsive layout on small screens.
- Safety: Added onbeforeunload guard for 2–3 seconds after Start to reduce accidental navigation before save completes.
- Version bump and changelog updated.
- Floating palette to help confirm the FSM is the single source of truth for the timer and to make troubleshooting quick without opening DevTools every time (e.g., seeing START/STOP/ERROR at a glance)


## Version 2.2.31 - Reinforce Update button visibility on Task Editor
- Added robust insertion of an "Update" button next to the Sessions repeater "Add Session" button.
- Uses MutationObserver + periodic retries to ensure the button is present even if ACF renders late.
- Styled as a primary button and placed inline with "Add Session" so it’s easy to find.
- Clicking it triggers the main Publish/Update action (#publish) with disabled state and feedback.

## Version 2.2.30 - Total Duration: add friendly hh:mm display
- **Added**: On the Task Editor, next to Total Duration (hrs), show a friendly hh:mm equivalent (read-only badge) that updates live as the value changes.
- **Note**: All calculations remain decimal hours; hh:mm is display-only for user friendliness.

## Version 2.2.29 - Preserve historical manual sessions; disable auto-timestamp on save
- **Changed**: Manual session entries without start/stop timestamps are no longer auto-stamped on save. This prevents older manual entries from being pulled into the current day when a new timer is stopped.
- **Updated**: Self-tests adjusted to verify that manual sessions without timestamps remain unchanged.

## Version 2.2.28 - PSR-4 hardening for Today helpers
- **Changed**: Split Today helpers into separate PSR‑4 files: EntryRenderer.php, DataProvider.php, PageManager.php
- **Removed**: Explicit requires for TodayHelpers.php in Plugin and SelfTests; Composer autoload now loads classes reliably
- **Kept**: today-helpers-compat.php wrapper so legacy procedural calls continue to work
- **Housekeeping**: Updated roadmap with PSR‑4 Hardening next steps

## Version 2.2.27 - Fix PSR-4 Today classes autoloading during Self-Tests
- **Fixed**: Explicitly include TodayHelpers.php where the PSR-4 Today classes (EntryRenderer, DataProvider, PageManager) are defined.
- **Reason**: These classes are defined in a single file; Composer PSR-4 expects one class per file, so the autoloader may not load them before Self-Tests run in some contexts.
- **Also**: Self-tests now ensure the file is required before referencing the classes, preventing fatal "Class ... DataProvider not found" errors.

## Version 2.2.26 - Comprehensive test post cleanup enhancement
- **Enhanced**: Self-test cleanup now removes ALL test post patterns, not just "CALC TEST POST".
- **Added**: Cleanup for "TODAY DATE INCLUSION TEST", "Today", "Convert Legacy Task Timer to New Sessions", and other test patterns.
- **Improved**: Double-layer cleanup - initial cleanup at start + final cleanup at end of tests.
- **Fixed**: Test posts no longer accumulate after multiple self-test runs.
- **Enhanced**: Cleanup verification now checks for all test patterns, not just one type.

## Version 2.2.25 - Fixed PSR-4 compatibility layer loading in self-tests
- **Fixed**: Self-tests now properly load PSR-4 compatibility layers before running tests.
- **Added**: Automatic compatibility layer loading for Today helpers and controller classes.
- **Enhanced**: Self-tests now use PSR-4 classes directly with fallback to procedural classes.
- **Resolved**: "Class DataProvider not found" error in self-tests.
- The enhanced error reporting from v2.2.24 successfully identified and helped resolve this PSR-4 migration issue.

## Version 2.2.24 - Enhanced self-test error reporting and debugging
- **Enhanced**: Self-test error handling with detailed debugging information.
- **Added**: Individual test error wrapping with try-catch blocks to isolate failing tests.
- **Added**: Debug information panel showing PHP version, memory limits, plugin version, etc.
- **Improved**: JavaScript error handling with detailed error messages and troubleshooting tips.
- **Added**: Support for "Error" status in addition to "Pass", "Fail", and "Skip".
- **Enhanced**: Network error handling with server response details and common causes.
- **Improved**: Error messages now include file names, line numbers, and stack traces.
- Self-tests now provide much better debugging information when issues occur.

## Version 2.2.23 - PSR-4 Migration: today.php
- **Migrated**: `today.php` to PSR-4 class `src/Presentation/Today/TodayController.php`.
- **Added**: Complete Today page controller with all 15 functions migrated to static methods.
- **Added**: Backward compatibility layer with procedural function wrappers in `src/Presentation/Today/today-compat.php`.
- **Updated**: Plugin class now uses PSR-4 TodayController instead of requiring procedural file.
- **Added**: Self-tests validation for PSR-4 TodayController class and key methods.
- **Maintained**: Full backward compatibility - all existing function calls and AJAX handlers continue to work.
- **Migrated Functions**: Menu registration, page rendering, and 11 AJAX handlers (tasks, sessions, timers, quick start).
- Fourth successful PSR-4 migration, completing the Today page presentation layer.

## Version 2.2.22 - PSR-4 Migration: today-helpers.php
- **Migrated**: `today-helpers.php` to PSR-4 classes in `src/Presentation/Today/TodayHelpers.php`.
- **Added**: Three new PSR-4 classes: `EntryRenderer`, `DataProvider`, and `PageManager`.
- **Added**: Backward compatibility layer with procedural class wrappers in `src/Presentation/Today/today-helpers-compat.php`.
- **Updated**: `today.php` now uses PSR-4 classes instead of requiring procedural file.
- **Added**: Self-tests validation for PSR-4 Today helper classes.
- **Maintained**: Full backward compatibility - all existing class calls continue to work.
- Third successful PSR-4 migration, establishing the pattern for presentation layer classes.

## Version 2.2.21 - PSR-4 Migration: helpers.php
- **Migrated**: `helpers.php` to PSR-4 class `src/Utilities/Helpers.php`.
- **Added**: Backward compatibility layer with procedural function wrappers in `src/Utilities/helpers-compat.php`.
- **Updated**: Plugin class now uses PSR-4 Helpers instead of requiring procedural file.
- **Added**: Self-tests validation for PSR-4 Helpers class and methods.
- **Maintained**: Full backward compatibility - all existing function calls continue to work.
- Second successful PSR-4 migration following the established pattern.

## Version 2.2.20 - Improved self-test cleanup and post deletion
- **Added**: Automatic cleanup of orphaned "CALC TEST POST" entries at the start of self-tests.
- **Added**: Final verification test to ensure all test posts are properly cleaned up.
- **Improved**: Test post cleanup now uses try-finally blocks to ensure cleanup even if tests fail.
- **Fixed**: Self-tests no longer leave behind multiple test posts in the task list.
- The self-tests now properly clean up after themselves, preventing accumulation of test data.

## Version 2.2.19 - Fixed PSR-4 Migration: time-functions.php
- **Fixed**: Fatal error caused by defining functions inside class methods (not allowed in PHP).
- **Added**: Separate compatibility file `src/Time/time-functions-compat.php` for procedural function wrappers.
- **Updated**: Plugin class now requires the compatibility file instead of calling a class method.
- **Maintained**: Full backward compatibility with all existing function calls.

## Version 2.2.18 - PSR-4 Migration: time-functions.php
- **Migrated**: `time-functions.php` to PSR-4 class `src/Time/TimeFunctions.php`.
- **Added**: Backward compatibility layer with procedural function wrappers.
- **Updated**: Plugin class now uses PSR-4 TimeFunctions instead of requiring procedural file.
- **Added**: Self-tests validation for PSR-4 TimeFunctions class and methods.
- **Maintained**: Full backward compatibility - all existing function calls continue to work.
- This is the first step in the PSR-4 migration strategy for better code organization.

## Version 2.2.17 - Added total duration display field
- **Added**: New "Total Duration (hrs)" read-only field in task edit screen that shows the sum of all session durations.
- **Updated**: Calculator class now populates the total_duration_display field when calculating durations.
- **Updated**: Reports and Kanban views now use the new total_duration_display field with fallback to on-demand calculation.
- **Updated**: ACF diagnostics and self-tests include validation for the new total duration display field.
- The total duration is now visible in the admin interface again, calculated from sessions only.

## Version 2.2.16 - Fixed fatal errors from removed calculated_duration field
- **Fixed**: Removed all references to calculated_duration field that was causing fatal errors in self-tests and other functions.
- **Fixed**: Calculator class no longer tries to update the removed calculated_duration field.
- **Updated**: Self-tests now use session-only approach for duration calculations and validation.
- **Updated**: Sample data validation tests now check session fields instead of removed parent-level fields.

## Version 2.2.15 - Fixed self-tests for session-only approach
- **Fixed**: "Calculate Total Time" self-tests now use session repeater data instead of removed parent-level timer fields.
- **Updated**: Self-tests create proper session entries with start/stop times for duration calculation testing.
- Self-tests should now pass correctly with the new session-only architecture.

## Version 2.2.14 - Single Source of Truth Migration
- **BREAKING**: Removed parent-level timer fields from ACF schema (start_time, stop_time, calculated_duration, manual_override, manual_duration).
- **Added**: Data migration tool accessible via Tasks → Data Migration to convert existing parent-level data to session format.
- **Disabled**: Legacy AJAX handlers for parent-level timer operations to prevent dual storage conflicts.
- **Updated**: All validation and detection functions to use session-only approach.
- **Updated**: Calculator class to use sessions-only for duration calculations (no parent-level fallback).
- Sessions repeater is now the single source of truth for all timer data.

### Migration Instructions (IMPORTANT)
**If you have existing timer data, you MUST run the migration tool:**

1. **Access Migration Tool**: Go to WordPress Admin → Tasks → Data Migration
2. **Review Preview**: The tool will show all tasks with parent-level timer data that need migration
3. **Run Dry Run** (Optional): Click "Dry Run" to preview what will happen without making changes
4. **Start Migration**: Click "Start Migration" to convert all parent-level data to session format
5. **Monitor Progress**: Watch the real-time progress bar and log for any issues
6. **Verify Results**: Check that your existing timer data appears correctly in the Sessions repeater

**What the migration does:**
- Converts parent-level timer data (start_time, stop_time, duration) to session repeater entries
- Preserves all timing data and manual overrides
- Creates backup of original data before migration
- Clears parent-level fields after successful migration
- Skips tasks that already have sessions to avoid duplicates

**After migration:**
- All timer functionality will use the Sessions repeater exclusively
- Parent-level timer fields no longer exist in the admin interface
- Existing workflows remain the same, but data is stored in session format
- Reports and calculations will use session data only

**Note**: This migration is one-way. Once completed, you cannot revert to parent-level timer fields without restoring from a database backup.

## Version 2.2.13 - Today page duplicate entries fix


## Version 2.2.33 - All Tasks: Assignee sorting/filter restored + self-test
Released: 2025-08-26
- Restored Assignee column sorting (ASC/DESC) on All Tasks admin list
- Added Assignee filter dropdown to narrow rows by user
- Added automated self-test to verify sortable column registration and dropdown rendering

## Version 2.2.32 - FSM-centric timer persistence + UI polish
Released: 2025-08-26
- Editor: Start Timer is now routed via FSM EditorEffects/Controller when FSM is enabled, ensuring a single authoritative state machine controls timers.
- Server: ptt_start_session_timer now creates the session row server-side if the requested index doesn't exist and returns the authoritative row_index.
- Editor: Start handler sends session_title; on mismatch row_index, the UI reloads to sync with the database.
- Editor: Added ptt_get_active_session_for_user endpoint and FSM rehydrate implementation to recover running session after reload.
- Editor: Disabled Start Timer when Manual Override is checked for the session.
- UI: Align hh:mm badge inline with Total Duration input; responsive layout on small screens.
- Safety: Added onbeforeunload guard for 2–3 seconds after Start to reduce accidental navigation before save completes.
- Version bump and changelog updated.

- Fixed Today page showing duplicate entries for Quick Start tasks (both "created" and session entries on same day).
- Added logic to suppress task-level "created" entries when session entries exist for the same date.

## Version 2.2.12 - Function redeclaration fix + timezone documentation
- Fixed fatal error: Cannot redeclare ptt_get_active_session_index_for_user() by adding function_exists() check in helpers.php.
- Added PROJECT-TIMEZONE.md documenting timezone handling architecture, potential issues, and recommendations.

## Version 2.2.11 - PSR-4: Reports Helpers
- Added KISS\\PTT\\Reports\\Helpers with formatTaskNotes() and getAssigneeName() read-only helpers.
- Wired reports.php to use the new helpers with back-compat wrappers; no behavior changes.

## Version 2.2.10 - Preserve legacy detailed self-test count
- SelfTests::run() now merges results from legacy ptt_test_data_structure_integrity() when available to keep the larger test count intact.


## Version 2.2.8 - PSR-4: Diagnostics SelfTests
- Extracted the self-test suite to KISS\\PTT\\Diagnostics\\SelfTests::run(); controller delegates to it. No behavior changes.

## Version 2.2.9 - PSR-4: Settings helper
- Added KISS\\PTT\\Admin\\Settings for FSM flags read/save; Assets and SelfTestController now use it. No behavior changes.


## Version 2.2.7 - Hide legacy ACF Schema card
- Hidden the large ACF Schema Status card on Settings page now that it’s part of the main self-tests summary.


## Version 2.2.6 - ACF Schema Status rolled into main self-tests
- The "ACF Schema Status" is now part of the main Self-Test group so the summary includes it.


## Version 2.2.5 - FSM debug panels (semi‑permanent)
- Added small on‑screen FSM debug panels for Today (bottom‑right) and Editor (bottom‑left) with Show toggle.
- Note: Do not remove these panels unless explicitly requested; comments added in code for future maintainers/LLMs.


## Version 2.2.4 - FSM flags in Settings (defaults ON)
- Added Settings toggles for FSM (Global, Today, Editor) defaulting to ON; flags now read from options.


## Version 2.2.3 - Enable Editor FSM for admins with ptt_debug=1
- Editor FSM now enabled alongside Today when `?ptt_debug=1` is present for admin users.


## Version 2.2.2 - Enable Today FSM for admins with ptt_debug=1
- Localized flags now turn on TimerFSM on Today page for admin users when `?ptt_debug=1` is present.
- Editor FSM remains disabled.


## Version 2.2.1 - FSM scaffolding (Editor)
- Added EditorEffects and EditorTimerController scaffolding (flags disabled by default; no behavior changes).
- Enqueued bundles for Editor; using the same TimerFSM core.


## Version 2.2.0 - FSM Phase 1a scaffolding (Today)
- Added TimerFSM core, TodayEffects, and TodayTimerController (feature flags disabled by default; no behavior change yet).
- Enqueued FSM bundles on Today and Editor screens via admin assets; localized flags for future rollout.
- Updated PROJECT-FSM.md with dual-context plan and scaffolding progress note.


## Version 2.1.9 - PSR-4 Sessions builder
- Delegated Today session entry construction to src/Presentation/Today/EntryBuilder::buildSessionEntriesForDate.
- Legacy provider now delegates; no UI changes.


## Version 2.1.8 - PSR-4 EntryBuilder (Today)
- Moved task-level Today entry construction to src/Presentation/Today/EntryBuilder and routed legacy code to use it.
- Maint: Continued incremental PSR‑4 migration for Today data-building.


## Version 2.1.7 - PSR-4 Today DateHelper
- Added src/Presentation/Today/DateHelper (isUtcOnLocalDate) and updated Today entry filtering to use it for centralized date logic.
- Maint: Continued PSR‑4 adoption around time/date utilities.


## Version 2.1.6 - UTC helpers adoption + Today self-test
- Reports: Replaced remaining session timestamp comparisons with centralized ACFAdapter::isUtcWithinLocalRange() for consistent timezone handling.
- Tests: Added self-test coverage to validate Today page session inclusion uses local date (mirrors JS) and won’t regress.


## Version 2.1.5 - Reports week buttons fix + debugging
- Reports: Core assets now load on the Reports page so week buttons are active.
- Reports: "This Week" and "Last Week" buttons now reliably set start/end date inputs and trigger change events.
- Dev: Added console.debug logs for view mode initialization and week button clicks to aid troubleshooting.


## Version 2.1.4 - Bugfix: Today + Reports
- Today: Suppress duplicate "created" task-level entry when a same-day session exists (affects Quick Start tasks that create a task and start a session immediately).
- Reports (Classic/Task Focused): Normalize session_start_time parsing to UTC when filtering by date range to fix missing items in ranges like "Last Week".

## Version 2.0.0 - PSR-4 bootstrap

## Version 2.1.0 - ACF Schema Status + FSM Planning

## Version 2.1.1 - FSM Phase 0 Scaffolding

## Version 2.1.3 - Phase 1 Finishing Touches
- Scripts: Rehydrate TimerFSM on Today page load (queries server for active session)
- Scripts: Live FSM debug panel updates under ptt_debug=1
- Scripts: Start validation via FSM effects (guards title/task for non-Quick Start)


## Version 2.1.2 - Hotfix: Duplicate function guard
- Fixed fatal error by guarding ptt_get_active_session_index_for_user() in today.php with function_exists to avoid redeclaration with helpers.php

- Scripts: Added non-breaking FSM scaffolding (feature flag, effects stubs, debug hook)
- Docs: Proceeding toward Phase 1 implementation behind feature flag
- Phase 1 (start): TimerFSM scaffold added behind feature flag; start/stop hooks routed through FSM when enabled (legacy preserved when disabled)


- Admin: New “ACF Schema Status” page under Tasks showing persistent schema diagnostics
- Admin: Copy diagnostics buttons (Text/JSON) and compact status widget on Settings/Self‑Test page
- Diagnostics: Extended ACF schema checks (keys, names, types, date formats) and allowed empty name for message fields
- Docs: PROJECT-FSM.md revised with actionable phased plan; ROADMAP updated with “NEXT MAJOR PROJECT: FSM” at the top
- Readme: Added developer tip to enable UI debugging with ?ptt_debug=1

- Major version bump to 2.0.0 to reflect PSR-4 architecture.
- Introduced Composer-based PSR-4 autoloader and plugin bootstrap class.
- Migrated time calculation helpers and task helpers to namespaced classes.
- Added ROADMAP for phased PSR-4 migration.


## Version 1.12.1 - Quick Start Reassignment + Self Test Summary Improvements
- Today page: Relaxed session reassignment rules for Quick Start entries to "same Client only" (Project not required)
- Today page: Added inline hint below Task Selector for Quick Start entries: "Quick Start: showing tasks for client (project not restricted)"
- Debug panel: Documented the new Quick Start reassignment behavior and clarified non-Quick Start rules
- Self Tests: Added summary at the top "Number of Tests: X out of Y Failed" with green "All tests have passed." when zero failures
- Self Tests: Added "Jump to first failed" link that anchors to the first failing test row

## Version 1.12.0 - Today Page Quick Start (Client-Required)

### Documentation
- Added other-docs/sessions-quickstart.md (Session Recordings Quick Start overview, history, and roadmap)
- Linked sessions-quickstart.md from readme.md

### Quick Start Features
- Added Client selector to the "What are you working on?" row on Today page
- Quick Start: Users can click Start after choosing a Client; plugin creates/uses a per-user daily Quick Start task under the "Quick Start" project (scoped by user and client)
- Auto session title format: "Started 3:42 PM - Aug. 11" (localized)
- Task dropdown now filters by selected Client (and Project if chosen)
- Minor UI consistency: muted labels and removed colons on time display

## Version 1.11.16
- **Collapisble Debug Panel on Today Page**:  Less noise on screen

## Version 1.11.15
- **Extensive Self Test**: A lot of data model/structure tests were added to help prevent code regression/modifications to the core functionality

## Version 1.11.0 - Today Page Workflow Enhancements & Parent-Level Timer Cleanup
*Release Date: 2025-01-11*

### Added
- **"Start Timer" Button for Tasks**: Added green "Start Timer" button for task-level entries without active sessions
  - Creates new session with auto-generated title "Session [time] AM/PM"
  - Automatically starts timer and updates main timer controls
  - Integrates seamlessly with existing timer system
- **"Add Another Session" Button**: Added blue "Add Another Session" button for completed session entries
  - Populates "What are you working on" area with task information
  - Auto-selects correct project and task in dropdowns
  - Generates session title with current time
  - Smooth scrolling to top for easy access
- **"Edit Task" Button**: Added "Edit Task" button for all entries
  - Opens WordPress post editor in new tab
  - Available for both task-level and session-level entries


### Enhanced
- **Today Page User Filtering**: Clarified that Today page only shows tasks assigned to current user (ptt_assignee)
  - Updated documentation to reflect assignee-only filtering
  - Enhanced debug information with clear filtering rules
  - Added note directing users to Reports or All Tasks for broader data
- **Responsive Design**: Added mobile-responsive styling for action buttons
  - Smaller buttons on mobile devices
  - Proper flex layout for three-column structure (details, duration, actions)
  - Improved touch targets for mobile users

### Hidden/Deprecated
- **Parent-Level Timer Fields**: Hidden parent-level time tracking fields from admin interface
  - Start Time, Stop Time, Manual Time Entry, and Manual Duration fields now hidden
  - Backend functionality preserved for existing data and fallback calculations
  - Added admin notice explaining the change and directing users to Sessions
  - Optional AJAX handler disabling available for additional security

### Technical Changes
- **New AJAX Handler**: `ptt_today_start_timer_callback()` for starting timers from Today page
- **Enhanced Entry Renderer**: Added `render_entry_actions()` method to `PTT_Today_Entry_Renderer`
- **Smart Project Selection**: JavaScript automatically selects correct project/task when adding sessions
- **CSS Improvements**: Comprehensive styling for action buttons with proper hover states
- **DRY Implementation**: Reused existing `ptt_today_start_new_session` AJAX handler for consistency

### Developer Notes
- All existing functionality remains backward compatible
- Parent-level timer fields can be re-enabled by removing CSS rules
- New workflow features integrate with existing timer system
- Action buttons use event delegation for dynamic content

---

## Version 1.10.9 - Enhanced Today Page Query Logic
*Release Date: 2025-01-11*

### Enhanced
- **Today Page Query Expansion**: The Today page now shows tasks based on three comprehensive scenarios:
  1. **Tasks Created/Published on Target Date**: Tasks that were created or published on the selected date now appear automatically
  2. **Parent-Level Time Tracking**: Tasks with parent-level time fields (`start_time`, `stop_time`, `manual_duration`) that match the target date
  3. **Session-Level Time Tracking**: Individual sessions within tasks that have `session_start_time` matching the target date (existing functionality)

### Added
- **Enhanced Debug Information**: Debug panel now shows detailed breakdown of entry types and explains all query rules
- **Entry Type Classification**: Entries are now categorized and labeled as:
  - "created: [Task Title]" for tasks published on the target date
  - "parent_time: [Task Title]" for tasks with parent-level time tracking
  - Session titles for individual session entries
- **Improved User Interface**: Task-level entries show "Task-level entry" instead of session controls since they represent the task itself
- **Better User Messaging**: Updated "No time entries recorded" to "No tasks or time entries found" to reflect expanded functionality

### Technical Changes
- **New Method**: `PTT_Today_Data_Provider::process_task_for_date()` - Comprehensive method that checks all three scenarios
- **Refactored**: `process_task_sessions()` method - Now a focused helper for session-specific processing
- **Enhanced**: Entry rendering logic to handle both task-level and session-level entries appropriately
- **Updated**: Debug output to show counts for each entry type and comprehensive query rules

### Developer Notes
- All existing functionality remains backward compatible
- New logic ensures tasks appear on Today page even without time tracking sessions
- Entry structure includes `entry_type` array for future filtering and display options
- Session index of -1 indicates task-level entries vs. session-level entries

## Version 1.10.8 - Auto-Timestamping Manual Sessions
Feature: Manual session entries that are missing a start time will now be automatically timestamped at the moment the task is saved. This improves data accuracy for reporting.

Dev: The self-test for manual sessions has been updated to validate the new auto-timestamping functionality.

## Version 1.10.7 - Regression Test
Dev: Added a new self-test to ensure that manual time sessions without a specific start date are correctly handled in reports.

## Version 1.10.6 - Reporting Fix for Manual Sessions
Fix: The "Single Day" report now correctly includes manual time sessions that do not have a specific start date by attributing them to the parent task's creation date.

## Version 1.10.5 - Reporting Calculation Fix
Fix: Corrected a logic error in the "Single Day" report view that was causing incorrect daily durations to be calculated and displayed. The report now accurately sums the durations of only the work sessions that occurred on the selected day.

## Version 1.10.4 - Session Move UI
- Add new Move Session Test to Self Test.
- Update Reports to account for manual time entry assuming on same date as task.

### Added
- Task name on Today page is now a dropdown of tasks within the same project and client.
- Move and Cancel buttons allow reassigning a session to a different task.

## Version 1.10.3 - Session Reassignment Logic

### Added
- Session reassignment functionality to move sessions between tasks
- Validation to ensure sessions can only be moved to tasks within the same project and client
- Automatic duration recalculation when sessions are moved

### Technical Changes
- New function `ptt_move_session()` for handling session transfers
- Enhanced session validation logic
- Updated calculation functions to handle moved sessions

## Version 1.10.2 - Today Page Enhancements

### Added
- New "Today" page for daily time tracking overview
- Real-time timer display with live updates
- Session management directly from Today page
- Date navigation for viewing different days
- Debug information panel for troubleshooting

### Enhanced
- Improved session timer controls
- Better visual feedback for active timers
- Responsive design for mobile devices

### Technical Changes
- New Today page template and helpers
- AJAX handlers for Today page functionality
- Enhanced session management system

## Version 1.10.1 - Session Timer Improvements

### Enhanced
- Improved session timer accuracy
- Better handling of timezone differences
- Enhanced timer state management

### Fixed
- Timer synchronization issues
- Session duration calculation edge cases

## Version 1.10.0 - Major Session System Overhaul

### Added
- Complete session-based time tracking system
- Individual session timers with start/stop functionality
- Session notes and titles for better organization
- Manual time entry for sessions
- Comprehensive session management interface

### Enhanced
- Improved time calculation accuracy
- Better data structure for time tracking
- Enhanced reporting capabilities

### Technical Changes
- New ACF field structure for sessions
- Refactored calculation functions
- Enhanced database schema for sessions

### Developer Notes
- Major version bump due to significant architectural changes
- All existing data preserved and migrated
- New session system provides foundation for future enhancements

## Version 1.9.0 - Initial Release
- Basic task and project management
- Simple time tracking functionality
- Client and project taxonomies
- Basic reporting features
