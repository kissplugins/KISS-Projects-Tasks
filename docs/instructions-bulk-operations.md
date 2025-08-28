# Bulk Operations for Session Management

## Overview

The Bulk Operations feature allows you to efficiently manage multiple time tracking sessions at once. Instead of editing or deleting sessions one by one, you can select multiple sessions and perform actions on them simultaneously.

**Note**: This feature is disabled by default and must be enabled in Settings before use.

---

## Getting Started

### Enabling Bulk Operations

1. Navigate to **Tasks → Settings** in your WordPress admin
2. Scroll to the **UI Features** section
3. Check the box for **"Enable Bulk Actions (Session Operations)"**
4. Click **Save Flags**
5. The bulk operations toolbar will now appear in your task editors

### Basic Workflow

1. **Open a task** that contains multiple time tracking sessions
2. **Select sessions** using the checkboxes that appear next to each session
3. **Choose an action** from the bulk operations dropdown
4. **Execute** the action using the Execute button

---

## Use Case Examples

### Use Case 1: Cleaning Up Test Data
**Scenario**: You've been testing the time tracker and created several test sessions that need to be removed.

**Steps**:
1. Open the task containing test sessions
2. Use checkboxes to select all test sessions (or use "Select All")
3. Choose "Delete Selected" from the dropdown
4. Click "Execute" and confirm the deletion
5. All selected sessions are removed at once

**Benefits**: Saves time compared to deleting each session individually.

### Use Case 2: Exporting Weekly Reports
**Scenario**: You need to export specific sessions from a project for client billing or reporting.

**Steps**:
1. Open the project task
2. Select only the sessions from the desired time period
3. Choose "Export Selected" from the dropdown
4. Click "Execute"
5. A CSV file downloads with only the selected sessions

**Benefits**: Creates focused reports without manual filtering.

### Use Case 3: Archiving Completed Sprints
**Scenario**: Your development team completes a sprint and needs to archive those specific sessions.

**Steps**:
1. Open the project task
2. Select all sessions related to the completed sprint
3. Export them first for records (Export Selected)
4. Then delete them to clean up the active task (Delete Selected)

**Benefits**: Maintains clean, current task views while preserving historical data.

### Use Case 4: Quality Control Review
**Scenario**: A project manager needs to review and potentially remove sessions that don't meet quality standards.

**Steps**:
1. Review sessions in the task
2. Select sessions that need to be removed (incomplete, duplicate, or incorrect entries)
3. Use bulk delete to remove them efficiently
4. Keep the task focused on valid, billable time

**Benefits**: Streamlines quality control processes.

---

## Frequently Asked Questions (FAQs)

### Q: Why don't I see the bulk operations toolbar?
**A**: The bulk operations feature is disabled by default. Go to Tasks → Settings and enable "Bulk Actions (Session Operations)" in the UI Features section.

### Q: Can I undo a bulk delete operation?
**A**: No, bulk deletions are permanent and cannot be undone. Always double-check your selections before executing a delete operation. The system will show you a confirmation dialog listing all sessions to be deleted.

### Q: What happens if I try to delete sessions while a timer is running?
**A**: The system will prevent bulk operations if any selected session has an active timer. Stop all timers before performing bulk operations.

### Q: What format is the exported CSV file?
**A**: The CSV includes columns for session title, start time, end time, duration, manual override status, and other session metadata. Files are timestamped for easy organization.

### Q: Can I select sessions across multiple tasks?
**A**: No, bulk operations work within a single task at a time. You'll need to perform operations on each task separately.

### Q: Is there a limit to how many sessions I can select?
**A**: There's no hard limit, but very large selections (100+ sessions) may take longer to process. The system processes deletions sequentially to maintain data integrity.

### Q: What happens if some sessions fail to delete during a bulk operation?
**A**: The system will report which sessions failed and continue with the others. Failed deletions are typically due to permission issues or data conflicts.

### Q: Can I cancel a bulk operation once it's started?
**A**: Bulk operations execute quickly and cannot be cancelled once started. However, you can cancel at the confirmation dialog before execution begins.

### Q: Do bulk operations affect the task's total time calculations?
**A**: Yes, deleting sessions will automatically recalculate the task's total time. Exported sessions don't affect the original task data.

### Q: Can other users see my bulk operations?
**A**: Bulk operations follow the same permission model as individual session edits. Users can only perform bulk operations on tasks they have edit permissions for.

---

## Technical Developer Overview

### Architecture

The bulk operations feature is built on the plugin's Finite State Machine (FSM) architecture and integrates seamlessly with the existing session management system.

### Key Components

**1. Settings Integration**
- `Settings::OPTION_BULK_ACTIONS_ENABLED` - Controls feature visibility
- Default: disabled (`'0'`) for gradual rollout
- Configurable via WordPress admin settings page

**2. JavaScript Implementation**
- `SessionEffects.js` - Core bulk operations logic
- `PTT_BULK_ACTIONS_ENABLED` flag controls UI rendering
- FSM state `BULK_PROCESSING` prevents conflicts during operations

**3. UI Components**
- Selection checkboxes on each session row
- Bulk operations toolbar with Select All/None controls
- Action dropdown (Delete Selected, Export Selected)
- Dynamic selection counter and execute button

### FSM Integration

**States**:
- `IDLE` - Normal state, bulk operations available
- `BULK_PROCESSING` - During bulk operation, other actions disabled
- Timer states prevent bulk operations to avoid conflicts

**Transitions**:
- `performBulkOperation` - Initiates bulk processing
- Automatic return to `IDLE` after completion

### Data Flow

**Bulk Delete**:
1. Validate no active timers in selected sessions
2. Show confirmation dialog with session list
3. Delete sessions sequentially (bottom-up to preserve indices)
4. Trigger post save to persist changes
5. Update UI and return to IDLE state

**Bulk Export**:
1. Extract session data from selected rows
2. Format as CSV with proper escaping
3. Generate timestamped filename
4. Trigger browser download
5. Return to IDLE state (no data modification)

### Security Considerations

- Respects WordPress user permissions
- Validates session ownership before operations
- Prevents operations on sessions with active timers
- Confirmation dialogs prevent accidental deletions

### Performance Notes

- Sequential deletion preserves ACF field indices
- Large operations (100+ sessions) may take several seconds
- CSV generation is client-side for better performance
- UI updates are batched to prevent flickering

### Extension Points

The bulk operations system can be extended with additional operations by:
1. Adding new options to the action dropdown
2. Implementing handlers in `performBulkOperation`
3. Following the FSM pattern for state management

### Debugging

- FSM debug panels show current state during operations
- Browser console logs operation progress
- Self-tests verify bulk actions setting functionality
- Error handling provides detailed failure information

### Dependencies

- jQuery for DOM manipulation
- ACF Pro for field management
- WordPress AJAX for server communication
- FSM architecture for state management
