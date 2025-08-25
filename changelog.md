# Changelog



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
