# Changelog



## Version 1.12.5 - Updated Reports debug messages

- Reports
  - Updated debug mode messages in all three views (Classic, Task Focused, Single Day) to accurately reflect the new date-range filtering functionality
  - Debug messages now clearly explain that session totals are calculated only for sessions within the selected date range using ptt_sum_sessions_in_range()
  - Added view-specific titles to debug sections for better clarity
  - Updated debug checkbox description to mention date range filtering logic

## Version 1.12.4 - Accurate date-range totals in reports

- Reports
  - Classic and Task Focused views now sum only sessions within the selected date range and show per-period totals

## Version 1.12.3 - Today Security + Performance hardening

- Security
  - Added assignee-based authorization helper ptt_validate_task_access() and enforced it across Today AJAX handlers (start timer, move session, update duration/field, delete session)
  - Centralized input validation helpers and refactored handlers to use them: ptt_validate_id(), ptt_validate_date(), ptt_validate_session_index(), ptt_validate_duration()
  - Added repeater bounds checks for session_index in update/delete/duration handlers to prevent out-of-range edits
- Performance
  - Today entries query: capped results (200), ordered by modified; added no_found_rows, suppress_filters; disabled term/meta cache updates
  - Added 60s transient cache for daily entries with coarse invalidation on acf/save_post
  - Tasks dropdown endpoint: use 'fields' => 'ids', disabled cache updates, avoided global post usage; retained no_found_rows and suppress_filters
  - Eliminated N+1 task selector queries by populating per-row selectors from a single request in JS
- Stability
  - Fixed a fatal parse error by moving an add_action hook out of a class scope in today-helpers.php
- QA
  - Added self-tests: authorization checks for assignee-only rules and a SQL hardening regression test to detect unprepared $wpdb calls

## Version 1.12.2 - Secure Quick Start task creation
- Today page: Validate client input and sanitize Quick Start task search to prevent SQL injection
- Today page: Ensure unique placeholder tasks and clean up old Quick Start tasks daily
- Today page: Cache Quick Start project to reduce database queries

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

