# 15 Minute End-to-End Test

**Purpose**: Quick manual validation of complete user workflow to complement automated tests.

**Time Required**: ~15 minutes

**Opens in**: Separate browser tab for testing

---

## **Test Scenario: Complete User Journey**

### **Setup (2 minutes)**
1. **Open WordPress Admin**: `/wp-admin/`
2. **Verify Plugin Active**: Check that K.I.S.S. Project & Task Time Tracker is active
3. **Check FSM Status**: Go to Settings → PTT Settings and note if EditorFSM is enabled

### **Step 1: Create Client (2 minutes)**
1. **Navigate**: Go to `Tasks → Clients`
2. **Add New Client**: 
   - Name: `E2E Test Client [Current Date]`
   - Description: `Manual end-to-end test client`
3. **Verify**: Client appears in list

### **Step 2: Create Project (2 minutes)**
1. **Navigate**: Go to `Tasks → Projects`
2. **Add New Project**:
   - Name: `E2E Test Project [Current Date]`
   - Description: `Manual end-to-end test project`
3. **Verify**: Project appears in list

### **Step 3: Create Task (3 minutes)**
1. **Navigate**: Go to `Tasks → All Tasks`
2. **Add New Task**:
   - Title: `E2E Test Task [Current Date]`
   - Assign to: Current user
   - Client: Select the test client created above
   - Project: Select the test project created above
   - Status: `In Progress`
3. **Verify**: Task appears in All Tasks list with correct assignments

### **Step 4: Log Multiple Sessions (4 minutes)**

#### **Session 1: Timer-Based Session**
1. **Open Task Editor**: Click on the test task
2. **Add Session Row**: Click "Add Row" in Sessions section
3. **Fill Session 1**:
   - Title: `Timer Session Test`
   - Notes: `Testing timer functionality`
   - Start Time: `[Today] 09:00:00`
   - Stop Time: `[Today] 10:30:00`
   - Manual Override: `Unchecked`
4. **Save**: Update the task

#### **Session 2: Manual Duration Session**
1. **Add Session Row**: Click "Add Row" again
2. **Fill Session 2**:
   - Title: `Manual Session Test`
   - Notes: `Testing manual duration entry`
   - Manual Override: `Checked`
   - Manual Duration: `2.5`
3. **Save**: Update the task

#### **Session 3: FSM Timer Test (if enabled)**
1. **Add Session Row**: Click "Add Row" again
2. **Fill Session 3**:
   - Title: `FSM Timer Test`
   - Notes: `Testing FSM timer integration`
3. **Start Timer**: Click the "Start Timer" button (if FSM enabled)
4. **Wait 30 seconds**
5. **Stop Timer**: Click the "Stop Timer" button
6. **Verify**: Start/stop times are populated automatically

### **Step 5: Verification (2 minutes)**

#### **Data Integrity Check**
1. **Refresh Task**: Reload the task editor page
2. **Verify Sessions**: All 3 sessions should be preserved with correct data
3. **Check Calculations**: Total duration should be calculated correctly
4. **Check Relationships**: Client and Project assignments should be intact

#### **Reports Check**
1. **Navigate**: Go to `Tasks → Reports`
2. **Run Report**: Generate a report for the current date range
3. **Verify**: Test task and sessions appear in report data

#### **FSM Debug Panel (if enabled)**
1. **Open Browser Console**: F12 → Console tab
2. **Check FSM State**: Look for FSM debug messages
3. **Verify**: FSM state transitions logged correctly

---

## **Expected Results**

### **✅ Success Criteria**
- [ ] Client created and visible in list
- [ ] Project created and visible in list  
- [ ] Task created with proper client/project assignments
- [ ] Timer session calculates duration correctly (1.5 hours)
- [ ] Manual session accepts manual duration (2.5 hours)
- [ ] FSM timer session works (if enabled)
- [ ] Total duration calculated correctly (~4+ hours)
- [ ] All data persists after page refresh
- [ ] Task appears in reports
- [ ] No JavaScript errors in console

### **❌ Failure Indicators**
- Client/Project/Task creation fails
- Sessions don't save or calculate incorrectly
- FSM timer doesn't work (when enabled)
- Data lost after page refresh
- JavaScript errors in console
- Reports don't show test data

---

## **Cleanup (1 minute)**
1. **Delete Test Task**: Move to trash and delete permanently
2. **Delete Test Project**: Remove from Projects list
3. **Delete Test Client**: Remove from Clients list

---

## **Notes**
- This test complements the automated EndToEndWorkflowTest
- Run this test after major changes to verify user experience
- If FSM is disabled, skip FSM-specific steps
- Document any issues found for development team
- Compare results with automated test results for consistency

---

## **Quick Links for Testing**
- **All Tasks**: `/wp-admin/edit.php?post_type=project_task`
- **Add Task**: `/wp-admin/post-new.php?post_type=project_task`
- **Clients**: `/wp-admin/edit-tags.php?taxonomy=client&post_type=project_task`
- **Projects**: `/wp-admin/edit-tags.php?taxonomy=project&post_type=project_task`
- **Reports**: `/wp-admin/admin.php?page=ptt-reports`
- **Settings**: `/wp-admin/admin.php?page=ptt-settings`
