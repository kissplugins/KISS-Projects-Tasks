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

### Reporting & Admin Tools
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

### 3. Viewing Reports

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

**Q: How does the "Single Day" report view work?**
A: This view provides a detailed daily log. It lists, in chronological order, all tasks that were either created or had time tracked on the selected day. The duration shown for each task is only the time logged on that specific day. The total at the bottom excludes time from any task or project named "Break" or "Personal Time", and those durations will appear with a strikethrough in the list.

**Q: Why can't I add a new session?**
A: You must complete any open sessions first. Make sure that every existing session row has either been stopped (with a start and stop time) or has a manual duration entered. You cannot add a new session if a previous one is still running.

**Q: Where do the notes in the reports come from?**
A: The main notes for a task are pulled from the large content editor of the "Task" post type (the same area where you write a blog post). Notes for individual sessions are not yet included in reports.

**Q: What does "Over Budget" in the reports mean?**
A: The total time tracked for the task has exceeded the hours you set in the "Maximum Budget" field. The system first checks for a task-specific budget. If one is not set, it falls back to the budget of the parent project. If the text is red, you've gone over the allocated time.

***

## View Changelog

For a detailed history of all versions and changes, please see the `changelog.md` file.