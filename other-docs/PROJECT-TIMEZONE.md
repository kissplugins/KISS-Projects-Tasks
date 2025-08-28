# Project Timezone Handling Documentation

## Overview

This document explains how the KISS Project & Task Time Tracker plugin handles time display and timezone conversion throughout the WordPress UI.

## Time Handling Architecture

### Storage Layer (Server-Side)
- **All timestamps stored in UTC** in the database (MySQL `Y-m-d H:i:s` format)
- ACF datetime fields store UTC values
- `ACFAdapter::nowUtc()` generates current UTC time for storage
- `TimerService` and `SessionRepository` work exclusively with UTC

### Display Layer (Server-Side PHP)
- **Uses WordPress site timezone** for display via `wp_date()` function
- **Key locations:**

```php
// Display times converted to WordPress site timezone
$start_num  = $entry['start_time'] ? wp_date( 'h:i:s', $entry['start_time'] ) : '--:--:--';
$start_ampm = $entry['start_time'] ? wp_date( 'A', $entry['start_time'] ) : '';
```

```php
// Duration display uses GMT (no timezone conversion needed)
$subtotal = gmdate( 'H:i:s', $entry['duration_seconds'] ?? 0 );
```

### Client-Side JavaScript
- **Uses browser's local timezone** for live timer calculations
- **Critical conversion logic:**

```javascript
// Convert UTC server time to browser local time for live timers
const startTime = new Date(startTimeStr.replace(' ', 'T') + 'Z');
const now = new Date(); // Browser local time
const diff = now - startTime; // Correct calculation
```

## Time Display Behavior

### 1. Displayed Time in WP UI vs Server Time
- **Static times** (start/end times): Use **WordPress site timezone** via `wp_date()`
- **Live running timers**: Use **browser's local timezone** via JavaScript `new Date()`
- **Duration calculations**: Always use UTC timestamps, display as duration (no timezone)

### 2. Is displayed time showing user's local time?
**It depends on the context:**

- **✅ YES for live timers**: JavaScript uses browser/OS local time
- **❌ NO for static times**: PHP uses WordPress site timezone setting (not user's browser timezone)
- **⚠️ POTENTIAL ISSUE**: If WordPress site timezone ≠ user's browser timezone, static times will appear "wrong" to the user

## Potential Problems

### 1. Timezone Mismatch
If a user in New York (EST) uses a WordPress site configured for Los Angeles (PST):
- **Live timer**: Shows correct local time (EST)
- **Static times**: Show PST times (3 hours behind)
- **User confusion**: "Why does my 2:00 PM session show as 11:00 AM?"

### 2. Multi-User Sites
WordPress has **one global timezone setting**, but users may be in different timezones:
- All users see times in the **site's timezone**, not their own
- No per-user timezone preferences

## Code Examples

### UTC Storage (ACFAdapter)
```php
/** Current UTC now in MySQL format. */
public function nowUtc(): string
{
    return (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
}

/** WordPress site timezone (falls back to UTC). */
public function wpTimezone(): DateTimeZone
{
    if (function_exists('wp_timezone')) {
        return wp_timezone();
    }
    $tz = get_option('timezone_string');
    if (!$tz || !@timezone_open($tz)) { $tz = 'UTC'; }
    return new DateTimeZone($tz);
}
```

### Display Conversion (Today Page)
```php
// Convert UTC timestamp to WordPress site timezone for display
$start_num  = $entry['start_time'] ? wp_date( 'h:i:s', $entry['start_time'] ) : '--:--:--';
$start_ampm = $entry['start_time'] ? wp_date( 'A', $entry['start_time'] ) : '';

$end_num    = $entry['stop_time'] ? wp_date( 'h:i:s', $entry['stop_time'] ) : '--:--:--';
$end_ampm   = $entry['stop_time'] ? wp_date( 'A', $entry['stop_time'] ) : '';
```

### Live Timer JavaScript
```javascript
function manageLiveTimer($container, startTimeStr) {
    // FIX: Treat the incoming time string as UTC by appending 'Z'
    const startTime = new Date(startTimeStr.replace(' ', 'T') + 'Z');
    const $timerDisplay = $container.find('.ptt-session-elapsed-time');

    const updateTimer = () => {
        const now = new Date(); // Browser local time
        const diff = now - startTime; // in milliseconds
        
        const hours = Math.floor(diff / 3600000);
        const minutes = Math.floor((diff % 3600000) / 60000);
        const seconds = Math.floor((diff % 60000) / 1000);
        
        const timeString = `${('0'+hours).slice(-2)}:${('0'+minutes).slice(-2)}:${('0'+seconds).slice(-2)}`;
        $timerDisplay.html(timeString);
    };
    
    updateTimer();
    setInterval(updateTimer, 1000);
}
```

## Recommendations

### Short-term (Current Architecture)
1. **Document the behavior** clearly in UI
2. **Add timezone indicator** to displayed times: "2:00 PM PST"
3. **Consider user education** about WordPress timezone settings
4. **Add timezone info to debug panels**

### Long-term (Enhancement)
1. **Add per-user timezone preferences**
2. **Use JavaScript for all time display** (respects user's browser timezone)
3. **Implement timezone-aware date pickers**
4. **Consider timezone selection in user profiles**

## Current Status

The current approach is **WordPress-standard** but may confuse users on multi-timezone teams. Most WordPress plugins follow this same pattern of using the site's timezone for display.

### Files Involved
- `src/Integration/ACF/ACFAdapter.php` - UTC storage and conversion helpers
- `today.php` - Time display using `wp_date()`
- `scripts.js` - Live timer JavaScript with timezone handling
- `src/Presentation/Today/DateHelper.php` - Date comparison utilities

### Key Functions
- `ACFAdapter::nowUtc()` - Generate UTC timestamps
- `ACFAdapter::wpTimezone()` - Get WordPress site timezone
- `wp_date()` - WordPress timezone-aware date formatting
- JavaScript `new Date()` - Browser local time for live timers
