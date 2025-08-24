# 🚀 KISS Project & Task Time Tracker

A robust WordPress plugin for tracking time spent on client projects and individual tasks. It integrates seamlessly with the WordPress admin area and provides a flexible front-end interface for users.

***

## Requirements

* **WordPress:** Version 5.0 or higher
* **PHP:** Version 7.4 or higher
* **Required Plugin:** [Advanced Custom Fields (ACF) Pro](https://www.advancedcustomfields.com/pro/) must be installed and activated.

***

## ⚙️ Installation

1.  Download the plugin folder `project-task-tracker`.
2.  Upload the entire `project-task-tracker` folder to the `/wp-content/plugins/` directory on your server.
3.  Activate the plugin through the 'Plugins' menu in your WordPress admin dashboard.
4.  Ensure ACF Pro is also installed and activated. The necessary custom fields will be created automatically.

***

### Quick Start Documentation
- See “Session Recordings Quick Start” for a feature overview and change history:
  - other-docs/sessions-quickstart.md


## ✨ Features

### Project & Task Management
* **Custom Post Type:** A dedicated "Tasks" CPT (`project_task`) to manage all work items.
* **Custom Taxonomies:** Organize tasks by "Clients" and "Projects".
* **Multi-Session Tracking:** Each task can have multiple work sessions, allowing for detailed time logs.
* **Budgeting & Deadlines:** Set a maximum hour budget and a deadline for both entire projects and individual tasks.
* **Dated Titles:** A "Use Today's Date" button in the task editor prepends the current date to the title for quick entry.

### Time Tracking
* **Live Session Timers:** Simple one-click Start/Stop buttons in each session row in the task editor, with a live updating timer.
* **Automatic Calculation:** Duration is automatically calculated in decimal format (e.g., 1.75 hours) and rounded up to two decimal places.
* **Timezone Support:** All time entries respect the timezone set in your WordPress settings.
* **Concurrent Task Prevention:** A user is prevented from starting a new task if another one is already running.

### Front-End Interface
* **`[task-enter]` Shortcode:** A powerful shortcode to place a time-tracking module on any page or post.
* **Dynamic Task Loading:** Users can select from pre-defined, un-started tasks or create a new one on the fly.
* **Budget Display:** The maximum allocated budget for a selected task is clearly displayed to the user.

### Daily Activity & Reporting
* **Today Page:** A comprehensive daily view showing all task-related activities for any selected date:
    * **Smart Query Logic:** Automatically shows tasks created on the date, tasks with time tracking, and individual work sessions
    * **Multiple Entry Types:** Displays task creation, parent-level time tracking, and session-level time tracking
    * **Date Navigation:** Easy navigation between days with descriptive labels and arrow controls
    * **Debug Information:** Detailed breakdown of query results and entry types for transparency
* **Reports Dashboard:** A dedicated "Reports" page in the admin area.
* **Filterable Data:** Filter reports by user, client, project, status, and custom date ranges.
* **Multiple View Modes:** Use the toggle switch to select a view:
    * **Classic:** Data is logically grouped by User, Client, and Project, with subtotals for each.
    * **Task Focused:** A flat list of all tasks.
    * **Single Day:** A chronological daily log with clickable navigation to jump between days. This view shows all tasks created or worked on during the selected day and calculates a net total, excluding breaks.
* **Over-Budget Highlighting:** The "Orig. Budget" column automatically highlights entries in red if the tracked time exceeds the allocated budget.
* **Developer Self-Test:** A built-in testing module to verify core functionality, including multi-session calculations and reporting logic.

***

## 📖 How to Use

The workflow is designed to be flexible. You can create tasks first and track time later, or create them on-the-fly.

### 1. How to Add a Task

1.  Navigate to **Tasks → Add New Task**.
2.  **(Optional)** Click the **"Use Today's Date"** button located under the title field to automatically prepend today's date to your task title. This is useful for daily logs.
3.  Enter a descriptive **Title** for the task (e.g., "Design homepage mockup").
4.  Use the main content editor to add any notes, descriptions, or links related to the task. This content will appear in the "Notes" column of the reports.
5.  On the right-hand side, assign the task to a **Client** and a **Project**.
6.  In the "Task Details" section below the content editor, you can set a **Maximum Budget (Hours)** and a **Task Deadline**.

### 2. How to Track Time

You can track time for a task in two ways: using the live timer or entering it manually. This is done via **Sessions**. Each task can have multiple sessions.

#### Adding a New Session

1.  Edit an existing task.
2.  Scroll down to the "Sessions" panel.
3.  Click the **"Add Session"** button.
    * **Note:** You must stop any currently running timers in other sessions before you can add a new one. The plugin will prompt you if you need to complete an existing session.
4.  A new, empty session row will appear. Enter a **Session Title** (e.g., "Initial research") and any **Notes** for that specific work block.

#### Using the Live Timer (Recommended)

1.  In a new or existing session row, find the **Timer** column.
2.  Click the blue **"Start Timer"** button.
3.  The button will be replaced by a live, ticking timer showing elapsed hours, minutes, and seconds, along with a red **"Stop Timer"** button. You can leave the page; the start time is saved.
4.  When you are finished working, return to the task editor and click the **"Stop Timer"** button.
5.  The session will be saved, the final duration will be calculated, and the post will be updated automatically.

#### Manually Adding Time

This is useful if you forgot to start a timer and need to log time after the fact.

1.  In a session row, check the **"Manual Time Entry"** toggle.
2.  The timer controls will be replaced with a **"Manual Duration (Hours)"** field.
3.  Enter the time spent in decimal format (e.g., `1.5` for 1 hour and 30 minutes, `0.25` for 15 minutes).
4.  Save the task by clicking **"Update"**. The manually entered duration will be added to the task's total.

### 3. Using the Today Page

The **Today** page provides a comprehensive daily view of your tasks and time tracking activities. It's designed to show you everything relevant for a specific date, whether you're tracking time or just want to see what tasks were created.

#### Accessing the Today Page

1.  Navigate to **Tasks → Today** from the admin menu.
2.  The page will load showing today's activities by default.

#### What Appears on the Today Page

The Today page uses intelligent query logic to show tasks based on **three scenarios**:

1. **Tasks Created/Published on the Selected Date**
   - Any task that was created or published on the target date will appear
   - Shows as "created: [Task Title]" entries
   - Useful for seeing what new work was assigned on a specific day
   - No time tracking required - the task just needs to exist

2. **Parent-Level Time Tracking**
   - Tasks where you used the main task timer (the older single-timer system)
   - Tasks with `start_time`, `stop_time`, or `manual_duration` fields matching the target date
   - Shows as "parent_time: [Task Title]" entries
   - Includes both timer-based and manually entered time at the task level

3. **Session-Level Time Tracking**
   - Individual work sessions within tasks that occurred on the target date
   - Shows with the actual session title you entered (e.g., "Initial research", "Bug fixes")
   - This is the most common type when using the modern multi-session system

#### Using the Date Selector

- **Date Dropdown**: Select from the last 10 days using descriptive labels ("Today", "Yesterday", or day names)
- **Navigation Arrows**: Use the left/right arrows to quickly move between days
- **Automatic Loading**: The page updates automatically when you change the date

#### Understanding Entry Types

- **Task-Level Entries**: Show "Task-level entry" and represent the task itself (cannot be moved between tasks)
- **Session Entries**: Show a task selector dropdown and can be moved between tasks if needed
- **Time Display**: Shows start time, end time (if applicable), and duration for each entry
- **Manual Entries**: Manual time entries show only start time and duration (no end time)

#### Query Rules Summary

The Today page will show a task if **any** of these conditions are met:
- The task was created/published on the selected date
- The task has parent-level time tracking (`start_time`) on the selected date
- The task has any session with `session_start_time` on the selected date
- The current user is the assigned user (`ptt_assignee`) - tasks you created for others are not shown

#### Debug Information

The debug panel at the bottom shows:
- Total number of tasks and entries found
- Breakdown by entry type (created, parent_time, session)
- Complete query rules explanation
- Raw data for troubleshooting

Developer tip: Enable on‑screen timer UI debugging by appending `?ptt_debug=1` to the admin edit URL (or run `localStorage.setItem('PTT_DEBUG','1'); location.reload();`).

### 4. Viewing Reports

1.  Navigate to **Tasks → Reports** from the admin menu.
2.  Use the view mode toggle to select between "Classic", "Task Focused", or "Single Day".
3.  Use the filters at the top to select a user, client, project, or status. For "Classic" and "Task Focused" views, select a date range. For the "Single Day" view, select a single day and use the `<` and `>` arrows next to the date to quickly navigate between days.
4.  Click **"Run Report"**.
5.  The results will be displayed based on your selections. Note any items in red under the "Orig. Budget" column, as these have exceeded their budget.

***

## ❔ Frequently Asked Questions (FAQ)

**Q: Why do I need ACF Pro?**
A: This plugin is built on Advanced Custom Fields Pro. It handles all the custom fields for start/stop times, budgets, deadlines, and the session repeater. The plugin will not function without it.

**Q: Can I run two timers at once?**
A: No. To ensure data integrity, the plugin prevents a user from starting a timer if another task is already running for them. You must stop your active task before starting a new one.

**Q: How does the date input field work on the Reports page?**
A: The behavior of the date filter changes based on the selected "View Mode":
* **Classic & Task Focused Views:** These modes use a **date range** (start and end date). The report will only show tasks that were **created** within that specific range.
* **Single Day View:** This mode uses a **single date picker**. It shows all tasks that were either **created** on that day OR had a **work session** that started on that day, making it a true daily log.

**Q: Why can't I add a new session?**
A: You must complete any open sessions first. Make sure that every existing session row has either been stopped (with a start and stop time) or has a manual duration entered. You cannot add a new session if a previous one is still running.

**Q: Where do the notes in the reports come from?**
A: The main notes for a task are pulled from the large content editor of the "Task" post type (the same area where you write a blog post). Notes for individual sessions are not yet included in reports.

**Q: What does "Over Budget" in the reports mean?**
A: The total time tracked for the task has exceeded the hours you set in the "Maximum Budget" field. The system first checks for a task-specific budget. If one is not set, it falls back to the budget of the parent project. If the text is red, you've gone over the allocated time.

**Q: What's the difference between the Today page and the Single Day report?**
A: While both show daily information, they serve different purposes:
* **Today Page:** Shows all task-related activities for a date, including tasks that were simply created/published that day (even without time tracking). It's designed for daily workflow management.
* **Single Day Report:** Focuses specifically on time tracking data and calculations for reporting purposes. It shows tasks that had actual work sessions on the selected day.

**Q: Why do I see "created: Task Name" entries on the Today page?**
A: These entries appear when a task was created or published on the selected date, even if no time has been tracked yet. This helps you see what new work was assigned or created on a specific day. You can start tracking time on these tasks by editing them and adding sessions.

**Q: What does "Task-level entry" mean on the Today page?**
A: This indicates an entry that represents the task itself (either because it was created on that date or has parent-level time tracking). Unlike session entries, these cannot be moved between tasks since they represent the task as a whole rather than a specific work session.

***

## View Changelog

For a detailed history of all versions and changes, please see the `changelog.md` file.