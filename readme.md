<canvas>

# 🚀 Project & Task Time Tracker

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
5.  Ensure ACF Pro is also activated. The necessary custom fields will be created automatically.

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

</canvas>