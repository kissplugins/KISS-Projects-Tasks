# 📋 Changelog

All notable changes to the "KISS - Project & Task Time Tracker" plugin will be documented in this file.

## 1.9.2 - 2025-08-09

* **Refactor**: Today page now returns structured entry data for flexible AJAX rendering.

## 1.9.1 - 2025-08-08

* **Fix**: Updated the "User Data Isolation" self-test to align with the new rule that only shows tasks to the assignee.
* **Chore**: Removed the inline changelog from the main plugin file (`project-task-tracker.php`) to rely solely on `changelog.md`.

## 1.9.0 - 2025-08-08

* **Feature**: The "Today" page is now user-specific, showing only tasks assigned to or authored by the current user.
* **Feature**: Added a new `helpers.php` file for common utility functions, improving code organization.
* **Fix**: Hardened the daily time entry logic to prevent potential errors from invalid date strings.
* **Dev**: Added a new self-test (`Test 10`) to verify user data isolation on the Today page and prevent future regressions.

* ## Version 1.8.9
* - **Feature**: Added new "Today" admin page for a daily dashboard view.
* - **Feature**: The "Today" page includes a quick-start timer to create new sessions for existing tasks.
* - **Feature**: Task dropdown on the "Today" page is filtered for "Not Started" or "In Progress" tasks and sorted by most recently modified (LIFO).
* - **Feature**: View a chronological list of time entries for the last 10 days with a running daily total.
* - **Enhancement**: The system now stops any other running timer for a user before a new one is started from the "Today" page.


* ## Version 1.8.10
* - Kanban vertical position for cards
Position Storage: Each task stores its position per status using meta keys like:

ptt_kanban_position_1 (for status ID 1)
ptt_kanban_position_2 

* ## Version 1.8.00
 * - Feature: Added comprehensive Kanban board view for visual task management
 * - Feature: Drag and drop functionality to update task status
 * - Feature: Advanced filtering by assignee, activity period, client, and project
 * - Feature: Filter preferences saved in cookies for persistent user experience
 * - Feature: Responsive design with mobile-friendly task management
 * - Feature: Keyboard navigation support for accessibility
 * - Feature: Visual indicators for active timers and over-budget tasks
* - Feature: Auto-save functionality with optimistic UI updates
* - Feature: Loading states and error handling for all interactions
* - Enhancement: Added /kanban rewrite rule for direct access
* - Enhancement: Integrated with existing AJAX handlers and security practices

### Version 1.7.42
* **Fixed:** Kanban filters no longer stall due to missing jQuery UI dependencies.
* **Added:** On-screen debug panel now displays AJAX activity on the Kanban board.

### Version 1.7.41
* **Added:** Confirmation dialog with "No" as the default before running "Synchronize Authors → Assignee" on the Self Test page.

### Version 1.7.40
* **Fixed:** Assignee dropdown in reports now queries by capability to reliably list Administrators, Editors, and Authors.

### Version 1.7.39
Fix: Corrected taxonomy registration to properly display Client, Project, and Status menus under the "Tasks" CPT menu.

Fix: Associated 'task_status' taxonomy with the 'project_task' CPT in its registration arguments.

Note: This fix requires the removal of the ptt_reorder_tasks_menu() function from self-test.php to prevent conflicts.

---
### Version 1.7.38 (2025-08-05)
* **Added:** Self-test ensuring taxonomy menu items remain visible under the Tasks menu.
* **Changed:** Render changelog using `kiss_mdv_render_file` when available.

---
### Version 1.7.37 (2025-08-04)
* **Fixed:** Added `show_in_menu` to taxonomy registration so taxonomy management pages appear under the Tasks menu.

---
### Version 1.7.36 (2025-08-01)
* **Fixed:** Simplified "Assignee" metabox by removing Author.

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
