# Changelog

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
- Backend function and AJAX handler to move a work session between tasks.
- Self-test coverage for session reassignment.

## Version 1.10.2 - Today Page Refinements
*Release Date: TBD*

### Changed
- Removed UTC offset from Today page timestamp displays.
- Hid edit/delete action buttons pending future functionality.
- Added client name after project in session metadata.
- Ensured Silkscreen font loads via existing admin stylesheet.

## Version 1.10.1 - Today Page Timestamps
*Release Date: TBD*

### Added
- Start and end timestamps with UTC offset on Today page session rows.
- Silkscreen Google Font applied to numeric time displays.

### Changed
- Session row layout now shows "Start", "End" and "Sub-total" values.

## Version 1.10.0 - Today Page Refactoring

### Changed
- **Major Refactoring of Today Page** via CLAUDE: Complete architectural overhaul for improved modularity and extensibility
  - Created `today-helpers.php` with three new classes for better code organization:
    - `PTT_Today_Entry_Renderer`: Handles flexible rendering of time entries
    - `PTT_Today_Data_Provider`: Manages data fetching and processing
    - `PTT_Today_Page_Manager`: Coordinates the overall page functionality
  - Enhanced HTML structure with data attributes for easier JavaScript manipulation
  - Added template-based entry rendering for consistency

### Added
- **New AJAX Handlers for Future Live Editing**:
  - `ptt_update_session_duration`: Allows inline editing of session durations
  - `ptt_update_session_field`: Enables editing of session titles and notes
  - `ptt_delete_session`: Supports deletion of individual sessions
- **Enhanced Data Structure**: Entry data now includes more metadata for richer display options
- **Improved Filtering Support**: Foundation laid for client filtering and other view options
- **Better Debug Information**: More structured debug output for troubleshooting

### Improved
- **Code Organization**: Separated concerns between data fetching, rendering, and user interaction
- **Extensibility**: Modular architecture makes it easier to add new features
- **Performance**: Optimized data queries and rendering processes
- **Accessibility**: Added proper ARIA attributes and keyboard navigation support
- **Responsive Design**: Better mobile experience with adapted layouts

### Developer Notes
- All existing functionality remains intact and backward compatible
- New helper classes provide clean APIs for future enhancements
- Data attributes throughout HTML enable easier DOM manipulation
- Foundation laid for features like:
  - Inline editing of time entries
  - Advanced filtering by client
  - Task search and selection
  - Multiple view modes (compact/detailed)

### Files Modified
- `today.php`: Restructured with cleaner HTML and enhanced AJAX handlers
- `today-helpers.php`: New file containing modular helper classes
- `project-task-tracker.php`: Updated version number and added helper file inclusion
- `styles.css`: Added enhanced styles for future live editing features

### Testing Notes
- All existing time tracking functionality continues to work as before
- New AJAX endpoints are ready but not yet connected to UI
- Debug panel provides detailed query information for troubleshooting
