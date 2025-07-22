<canvas>

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
3.  The folder should contain the following three files in its root:
    * `project-task-tracker.php`
    * `styles.css`
    * `scripts.js`
4.  Activate the plugin through the 'Plugins' menu in your WordPress admin dashboard.
5.  ACF Pro (Premium/paid) plugin is required and needs to be activated. The necessary custom fields will be created automatically.

***

## ✨ Features

### Project & Task Management
* **Custom Post Type:** A dedicated "Tasks" CPT (`project_task`) to manage all work items.
* **Custom Taxonomies:** Organize tasks by "Clients" and "Projects".
* **Budgeting & Deadlines:** Set a maximum hour budget and a deadline for both entire projects and individual tasks.

### Time Tracking
* **Start/Stop Timer:** Simple one-click Start/Stop buttons in the task editor.
* **Automatic Calculation:** Duration is automatically calculated in decimal format (e.g., 1.75 hours) and rounded up to two decimal places.
* **Timezone Support:** All time entries respect the timezone set in your WordPress settings.
* **Concurrent Task Prevention:** A user is prevented from starting a new task if another one is already running.

### Front-End Interface
* **`[task-enter]` Shortcode:** A powerful shortcode to place a time-tracking module on any page or post.
* **Dynamic Task Loading:** Users can select from pre-defined, un-started tasks or create a new one on the fly.
* **Budget Display:** The maximum allocated budget for a selected task is clearly displayed to the user.

### Reporting & Admin Tools
* **Reports Dashboard:** A dedicated "Reports" page in the admin area.
* **Filterable Data:** Filter reports by user and custom date ranges.
* **Grouped Results:** Data is logically grouped by User, Client, and Project, with subtotals for each.
* **Developer Self-Test:** A built-in testing module to verify core functionality.

***

## 📖 How to Use

### 1. Planning Projects and Tasks
1.  Navigate to **Tasks → Projects** to create your projects. Here you can set a **Maximum Budget** and **Deadline** for the entire project.
2.  Navigate to **Tasks → Clients** to create your clients.
3.  Navigate to **Tasks → Add New Task** to create individual tasks for your team. Assign them to a Client and Project, and set the specific **Maximum Budget** and **Deadline** for that task.

### 2. Tracking Time (Front-End)
1.  Add the shortcode `[task-enter]` to any page.
2.  A logged-in user (Editor, Author, Admin) can visit this page.
3.  They will select a Client, then a Project.
4.  The "Select Task" dropdown will populate with all available tasks for that project.
5.  Upon selecting a task, they'll see the budget and can click **Start Timer**.
6.  To stop the timer, they can revisit the page, where an "Active Task" module will appear with a **Stop Timer** button.

### 3. Tracking Time (Back-End)
1.  Navigate to **Tasks → All Tasks** and edit the desired task.
2.  In the "Publish" meta box on the right, click the green **Start Timer** button.
3.  When finished, edit the task again and click the red **Stop Timer** button.

### 4. Viewing Reports
1.  Navigate to **Reports** from the main admin menu.
2.  Select a user and/or date range.
3.  Click **Run Report** to see a detailed breakdown of time tracked.

***

## 📋 Changelog
### Version 1.7.0 (2025-07-22)
* **Sessions:** Tasks now support multiple work sessions via a repeater in the admin editor.
* **Automatic Stop:** Starting a new session automatically stops any prior running session for that task.
### Version 1.6.7 (2025-07-21)
* **Status Taxonomy:** Tasks now include a Task Status with default terms.
* **Reports/Tasks:** New Status column and filtering options.

### Version 1.6.6 (2025-07-21)
* **Permalinks:** Task links now include the post ID for easier reference.
* **Template:** Added front-end task view with timer and manual entry (authors+ only).
* **Shortcodes:** New `[daily-planner]` and `[weekly-planner]` shortcodes list upcoming tasks.
### Version 1.6.4 (2025-07-20)
**Bug Fixes**
* **Fixed:** Reports page reloaded to task list after submitting query

### Version 1.6.3 (2025-07-20)
**Sharable Reports**
* **Added:** URL parameters for reports (user, client, project, dates)
* **Feature:** Reports auto-load based on incoming URL parameters


### Version 1.6.2 (2025-07-20)
**Budget Display in Reports**
* **Added:** "Orig. Budget" column to reports showing allocated hours
* **Feature:** Displays task-specific budget when available
* **Feature:** Falls back to project budget if no task budget is set
* **Display:** Shows "(Task)" or "(Project)" label to indicate budget source
* **Improved:** Column width adjustments for better readability

### Version 1.6.1 (2025-07-20)
**Reports Enhancement**
* **Added:** Notes column to reports table showing task content/body
* **Added:** Automatic URL detection and rendering as clickable links in Notes
* **Added:** Text truncation at 200 characters with ellipses for long notes
* **Improved:** Report table layout with proper column widths
* **Feature:** URLs anywhere in notes are automatically converted to clickable links

### Version 1.6.0 (2025-07-20)
**Manual Time Entry Feature**
* **Added:** Manual time entry option in both admin post editor and frontend shortcode
* **Added:** Manual override checkbox in ACF fields to switch between timer and manual modes
* **Added:** Decimal hours input with helpful examples (1.5 = 1h 30m)
* **Added:** Manual Entry button on frontend form for quick time logging
* **Added:** Validation for manual time entries (must be positive, max 24 hours)
* **Improved:** Time calculation logic now supports both timer-based and manual entries
* **Feature:** Users can now log time retrospectively if they forgot to start the timer

### Version 1.5.0 (2025-07-20)
**Improved Error Handling & Recovery**
* **Fixed:** Stop button lockout issue with comprehensive error recovery
* **Added:** Force Stop button for emergency timer recovery
* **Added:** Session recovery using localStorage - tasks persist across page refreshes
* **Added:** Auto-detection of active tasks on page load
* **Added:** Visual timer display showing elapsed time (HH:MM format)
* **Improved:** Error messages now show specific causes and recovery options
* **Improved:** Better handling of concurrent tasks with clear user guidance
* **Enhanced:** Stop button validation to prevent invalid operations
* **Security:** Added user ownership verification for timer operations

### Version 1.4.7
* Previous stable release

</canvas>
