# 📋 Changelog

All notable changes to the "KISS - Project & Task Time Tracker" plugin will be documented in this file.

---
### Version 1.7.38 (2025-08-01)
* **Fixed:** Implemented a more robust fix for the `ptt_format_task_notes` helper function by using the `make_clickable` core WordPress function. This resolves the "Truncation failed" and "URL conversion failed" self-test errors.

---
### Version 1.7.37 (2025-08-01)
* **Fixed:** Resolved a bug in the `ptt_format_task_notes` helper function that caused the "Truncation failed" self-test to fail. The function now correctly truncates notes after converting URLs to links.

---
### Version 1.7.36 (2025-08-01)
* **Changed:** Reverted the "Author & Assignee" metabox to be a standalone "Assignee" metabox to improve stability. The default WordPress "Author" metabox is now used for assigning authors.

---
### Version 1.7.35 (2025-08-01)
* **Fixed:** The "Author" field in the custom "Author & Assignee" metabox now saves correctly when changed.

---
### Version 1.7.34 (2025-08-01)
* **Fixed:** Resolved a server-specific issue where the Assignee dropdown would not populate. Changed user query from role-based to capability-based for better reliability across different WordPress environments (including Multisite).

---
### Version 1.7.33 (2025-08-01)
* **Fixed:** The "Assignee" dropdown now correctly appears in the "Author & Assignee" metabox on the task editor screen.

---
### Version 1.7.32 (2025-08-01)
* **Feature:** Added a Settings utility to synchronize Authors into the Assignee field.
* **Feature:** Display the Assignee alongside Author in the All Tasks list.

---
### Version 1.7.31 (2025-08-01)
* **Change:** Restored WordPress default "Author" label and added a new "Assignee" field.
* **Feature:** Reports now filter and display using the custom "Assignee" field.

---
### Version 1.7.30 (2025-07-30)
* **Feature**: Added in-dashboard changelog viewer and reorganized Tasks menu order.
* **Change**: Moved dynamic version number display from Settings to Changelog menu item.

---
### Version 1.7.29 (2025-07-30)
* **Dev**: Improved date range self-test to limit scope to test posts and show missing or unexpected tasks with actionable messages.

---
### Version 1.7.28 (2025-07-30)
* **Dev**: Added self-test to verify report date range filtering works as expected.
* **Dev**: Added maintainer notice comment to date search logic to prevent unintended refactors.

---
### Version 1.7.27 (2025-07-30)
* **Feature**: Added an "Assignee" column to all report views (Classic, Task Focused, and Single Day) for better visibility on multi-user reports.

---
### Version 1.7.26 (2025-07-30)
* **Feature**: Added a notice to the top of the Reports page explaining how the date filter works for each view mode.

---
### Version 1.7.25 (2025-07-30)
* **Improved**: "Classic" and "Task Focused" reports now include tasks based on their session entry dates, not just creation dates, providing a more accurate view of work within a period.
* **Feature**: Added "Creation Date" and "Last Entry Date" columns to the "Classic" and "Task Focused" report views for improved clarity.

---
### Version 1.7.24 (2025-07-24)
* **Improved:** Author labels now read "Assignee" in the Tasks admin screens and reports.

### Version 1.7.23 (2025-07-23)
* **Feature:** Added "Slack Username" and "Slack User ID" fields to user profiles under a new "KISS PTT - Sleuth Integration" section.
* **Improved:** Reports in "Classic" view now display the user's Slack information next to their name (e.g., Noel Saw (@noelsaw - U1234567890)).

### Version 1.7.22 (2025-07-23)
* **Fixed:** The "Show sorting & query logic" debug option now works correctly for the "Single Day" report view.
* **Dev:** Consolidated changelogs from all plugin files into this central `changelog.md` file.

### Version 1.7.21 (2025-07-23)
* **Improved:** In Single Day view, durations for "Break" or "Personal Time" tasks now have a strikethrough to indicate they are excluded from the net total.

### Version 1.7.20 (2025-07-23)
* **Fixed:** Single Day report now correctly calculates and displays durations for tasks using the legacy/single manual time entry fields.

### Version 1.7.19 (2025-07-23)
* **Feature:** Added date navigation arrows to the Single Day report view.
* **Feature:** Single Day view now calculates a net total, deducting time for "Break" or "Personal Time" tasks/projects.
* **Improved:** "This Week" and "Last Week" buttons are now hidden in Single Day view.

### Version 1.7.18 (2025-07-23)
* **Feature:** Added "Single Day" view mode to reports to show all tasks created or modified on a specific day.
* **Feature:** In Single Day view, the report only calculates and displays session durations for the selected day.
* **Improved:** The date picker on the reports page now switches to a single date selector when "Single Day" view is active.

### Version 1.7.17 (2025-07-23)
* **Feature:** Added "Task Focused" list view mode to reports, with a custom toggle switch UI.
* **Feature:** In Task Focused view, tasks with multiple statuses appear as a line item for each status.

### Version 1.7.16 (2025-07-23)
* **Fixed:** A PHP syntax error on the reports page caused by a missing '$' in an unset() call.

### Version 1.7.15 (2025-07-23)
* **Feature:** Added a debug toggle to the reports screen to show query and sorting logic.
* **Improved:** "Sort by Status" now correctly re-sorts all tasks based on the selection.
* **Improved:** Clients and Projects within the report are now sorted alphabetically.

### Version 1.7.14 (2025-07-24)
* **Improved:** Default status sorting now includes "Deferred" before "Completed".

### Version 1.7.13 (2025-07-24)
* **Fixed:** Sorting preference cookie is now set before any output to prevent header warnings.

### Version 1.7.12 (2025-07-24)
* **Feature:** Added a "Sort by Status" option on the Reports page with an optional cookie to remember user preference.

### Version 1.7.11 (2025-07-22)
* **Fixed:** The 'Maximum Budget' fields now correctly accept decimal values like 0.25.
* **Fixed:** Self-test 'STATUS TEST' posts are now correctly deleted after a test run.
* **Dev:** Added a new self-test to verify multi-session duration calculations.

### Version 1.7.10 (2025-07-23)
* **Dev:** Refined self-test module to cover status updates and reporting logic.

### Version 1.7.8 (2025-07-22)
* **Feature:** The Status column on the Reports page is now an editable dropdown menu, allowing for instant, auto-saving updates to a task's status.

### Version 1.7.7 (2025-07-22)
* **Feature:** New tasks now default to the "Not Started" status in the admin editor.
* **Improved:** Added "Not Started" to the list of default statuses created on plugin activation.

### Version 1.7.6 (2025-07-22)
* **Fix:** Reports page now correctly displays all tasks that match the date filter, including those not yet started.
* **Improved:** Reports are now more reliable by using the task's publish date for filtering.
* **Improved:** Tasks within each project on the report are now sorted chronologically.

### Version 1.7.4 (2025-07-21)
* **Docs:** Added detailed user instructions and an FAQ section to the Readme file.
* **Feature:** On screen debugger code for timer in scripts.js - comment out later

### Version 1.7.3 (2025-07-21)
* **Feature:** Session rows in the admin editor now have a live, one-click timer.
* **Feature:** Starting and stopping a session timer no longer requires a page reload.
* **Improved:** Re-used front-end timer logic for back-end sessions to adhere to DRY principles.
* **Fixed:** Session validation now correctly checks for running timers before allowing a new session to be added.

### Version 1.7.2 (2025-07-21)
* **Feature:** Orig. Budget amount in reports now appears in red if the task duration exceeds the budget.
* **Feature:** Added a "Use Today's Date" button under the post title field on the Add/Edit Task screen to quickly prepend the current date.

### Version 1.7.1 (2025-07-21)
* **Sessions Save:** Adding a new session now automatically saves the task.
* **Totals:** Task duration is now calculated from the sum of all sessions.
* **Validation:** New-sessions cannot be created while previous ones are incomplete.

### Version 1.7.0 (2025-07-21)
* **Sessions:** Tasks now support multiple work sessions via a repeater in the admin editor.
* **Automatic Stop:** Starting a new session automatically stops any prior running session for that task.