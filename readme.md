# 🚀 KISS Project & Task Time Tracker

A robust WordPress plugin for tracking time spent on client projects and individual tasks. Requires ACF Pro.

***

## Requirements

* **WordPress:** Version 5.0 or higher
* **PHP:** Version 7.4 or higher
* **Required Plugin:** [Advanced Custom Fields (ACF) Pro](https://advancedcustomfields.com/pro/) must be installed and activated.

***

## Installation

1. Upload the `kiss-project-task-time-tracker` folder to your `/wp-content/plugins/` directory.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Configure your ACF fields for tasks and projects as outlined below.

## Usage

### 1. Creating Tasks & Projects
1. Navigate to **Tasks → Add New Task** to create individual tasks.  
2. Assign each task to a **Client** and a **Project**, setting the specific **Maximum Budget** and **Deadline** for that task.

### 2. Tracking Time (Front-End)
1. Add the shortcode `[task-enter]` to any page.  
2. A logged-in user (Editor, Author, Admin) can visit this page.  
3. They will select a Client, then a Project.  
4. The “Select Task” dropdown will populate with all available tasks for that project.  
5. Upon selecting a task, they’ll see the budget and can click **Start Timer**.  
6. To stop the timer, they can revisit the page, where an “Active Task” module will appear with a **Stop Timer** button.

### 3. Tracking Time (Back-End)
1. Navigate to **Tasks → All Tasks** and edit the desired task.  
2. In the “Publish” meta box on the right, click the green **Start Timer** button.  
3. When finished, edit the task again and click the red **Stop Timer** button.

### 4. Viewing Reports
1. Navigate to **Reports** from the main admin menu.  
2. Select a user and/or date range.  
3. Click **Run Report** to see a detailed breakdown of time tracked.

***

## 📋 Changelog
### Version 1.6.5 (2025-07-20)
**Bug Fixes**
* **Fixed:** Reports now include manually entered task hours when manual override is enabled

### Version 1.6.4 (2025-07-20)
**Bug Fixes**
* **Fixed:** Reports page reloaded to task list after submitting query

### Version 1.6.3 (2025-07-20)
**Sharable Reports**
* **Added:** URL parameters for reports (user, client, project, dates)
* **Feature:** Reports auto-load based on incoming URL parameters

### Version 1.6.2 (2025-07-20)
**Budget Display in Reports**
* **Added:** “Orig. Budget” column to reports showing allocated hours
* **Feature:** Displays task-specific budget when available
* **Feature:** Falls back to project budget if no task budget is set
* **Display:** Shows “(Task)” or “(Project)” label to indicate budget source
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
* **Added:** Session recovery using localStorage – tasks persist across page refreshes
* **Added:** Auto-detection of active tasks on page load
* **Added:** Visual timer display showing elapsed time (HH:MM format)
* **Improved:** Error messages now show specific causes and recovery options
* **Enhanced:** Stop button validation to prevent invalid operations
* **Security:** Added user ownership verification for timer operations

### Version 1.4.7
* Previous stable release


