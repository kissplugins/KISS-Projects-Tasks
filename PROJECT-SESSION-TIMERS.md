# Project Session Timers Documentation

## Overview

The KISS Project & Task Time Tracker uses a dual-timer system to track work sessions. This document provides a comprehensive analysis of the timer architecture, concurrent session prevention mechanisms, and system behavior.

## Timer System Architecture

### 1. New Session System (Primary)
- **Storage**: ACF repeater field `sessions` with individual session records
- **Fields**: `session_start_time`, `session_stop_time`, `session_title`, `session_notes`
- **Behavior**: Auto-stops existing sessions when starting new ones
- **Used in**: Today page, Quick Start functionality, session management

### 2. Legacy Timer System (Secondary)
- **Storage**: Direct ACF fields `start_time`, `stop_time` on task posts
- **Behavior**: Blocks new timers if one already exists
- **Status**: Mostly disabled, maintained for backward compatibility
- **Used in**: Legacy timer functions (can be disabled via code)

## Concurrent Session Prevention

### Server-Side Protection (Strong)

#### New Session System
```php
// Function: ptt_get_active_session_index_for_user()
// Checks all tasks assigned to user for active sessions
$active_session = ptt_get_active_session_index_for_user( get_current_user_id() );
if ( $active_session ) {
    ptt_stop_session( $active_session['post_id'], $active_session['index'] );
}
```

#### Legacy Timer System
```php
// Function: ptt_has_active_task()
// Checks for active legacy timers
$active_task_id = ptt_has_active_task( $user_id, $post_id );
if ( $active_task_id > 0 ) {
    wp_send_json_error( [
        'message' => 'You have another task running. Please stop it before starting a new one.'
    ] );
}
```

### Client-Side Protection (Good)

#### JavaScript Checks
```javascript
// Check if timer is already running
if ($startStopBtn.hasClass('running')) {
    alert('You have an active timer running. Please stop it before starting a new session.');
    return;
}
```

#### UI State Management
- Timer buttons disabled when active
- Form fields locked during active sessions
- Visual indicators for running timers

## Key AJAX Endpoints

### Session Management
- `ptt_today_start_new_session` - Start new session (Today page)
- `ptt_start_session_timer` - Start session timer (Admin)
- `ptt_stop_session_timer` - Stop session timer
- `ptt_today_start_timer` - Quick Start timer functionality

### Legacy Timer (Mostly Disabled)
- `ptt_start_timer` - Legacy timer start
- `ptt_stop_timer` - Legacy timer stop
- `ptt_force_stop_timer` - Force stop legacy timer

## Core Functions

### Session Detection
- `ptt_get_active_session_index()` - Find active session in specific task
- `ptt_get_active_session_index_for_user()` - Find any active session for user
- `ptt_has_active_task()` - Check for legacy active timers

### Session Management
- `ptt_stop_session()` - Stop session and calculate duration
- `ptt_calculate_session_duration()` - Calculate session duration
- `ptt_calculate_and_save_duration()` - Update total task duration

## System Behavior Analysis

### Starting New Sessions

1. **Today Page Flow**:
   - Check for existing active sessions
   - Auto-stop any found active sessions
   - Create new session record
   - Start timer display

2. **Quick Start Flow**:
   - Same auto-stop behavior
   - Creates placeholder task if needed
   - Generates auto-titled session

3. **Admin Session Flow**:
   - Stops other sessions in same task
   - Allows session-specific timer control

### Concurrent Session Prevention Strategy

**Philosophy**: **Auto-Stop & Continue** rather than **Block & Error**

**Benefits**:
- Seamless user experience
- No interruption to workflow
- Maintains continuous time tracking
- Prevents user frustration

**Implementation**:
- New sessions automatically stop previous ones
- Legacy system blocks concurrent timers
- Client-side validation provides immediate feedback

## Security & Data Integrity

### Authorization Checks
- All timer endpoints require `edit_posts` capability
- Nonce verification on all AJAX requests
- User can only control their own assigned tasks

### Data Validation
- Post ID validation
- Session index bounds checking
- User assignment verification

## Potential Issues & Mitigations

### 1. Mixed System Interaction
**Issue**: Legacy and new systems operate independently
**Risk**: User could have legacy timer + new session running
**Mitigation**: Legacy system mostly disabled; new system is primary

### 2. Race Conditions
**Issue**: Small window between check and start operations
**Risk**: Multiple rapid clicks could bypass checks
**Mitigation**: Client-side button disabling + server-side auto-stop

### 3. Session Orphaning
**Issue**: Sessions without stop times if browser crashes
**Risk**: Phantom active sessions
**Mitigation**: Active session detection handles incomplete sessions

## Best Practices

### For Developers
1. Always use the new session system for new features
2. Check for active sessions before starting new ones
3. Implement proper error handling for timer operations
4. Use auto-stop behavior rather than blocking

### For Users
1. Use Today page for optimal timer experience
2. Allow auto-stop behavior to manage session transitions
3. Manually stop timers before closing browser for accuracy

## Testing Scenarios

### Concurrent Session Tests
1. Start timer on Today page, try to start another → Should auto-stop first
2. Start session in admin, start timer on Today page → Should auto-stop admin session
3. Multiple browser tabs with same user → Should sync timer state
4. Quick Start while other timer running → Should auto-stop and continue

### Edge Cases
1. Browser crash during active session → Should detect as active on reload
2. Network interruption during timer start → Should handle gracefully
3. Multiple users on same task → Should isolate by user assignment

## Conclusion

The KISS PTT timer system provides robust concurrent session prevention through a combination of server-side auto-stop behavior and client-side validation. The dual-system architecture maintains backward compatibility while prioritizing user experience through seamless session transitions.

**Current Status**: ✅ **Well Protected** - Users cannot run multiple sessions simultaneously. The auto-stop behavior ensures continuous time tracking without user frustration.

The system successfully balances data integrity, user experience, and technical robustness in its timer management approach.
