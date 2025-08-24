# Today Page FSM Plan (Revised)

This document defines a pragmatic, low‑risk path to introduce a Finite State Machine (FSM) to the Today page. The goal is clearer state management, fewer race conditions, and better debugging without breaking existing behavior.

---

## Architecture overview

- Two small FSMs instead of one composite:
  - TimerFSM: IDLE → STARTING → RUNNING → STOPPING → ERROR
  - DataFSM: IDLE → LOADING → LOADED → ERROR
- Controller: minimal glue that forwards events between FSMs when needed
- Effects layer: DOM/network operations live here and are injected into FSMs; the FSMs stay pure and testable
- Server‑authoritative context: timer state rehydrated from the server on page load

Key benefits: simpler state spaces, better unit testing, and no “LOADING_WITH_TIMER” combinatorics.

---

## State definitions (authoritative)

TimerFSM events:
- START_TIMER, TIMER_STARTED(payload), START_FAILED(reason)
- STOP_TIMER, TIMER_STOPPED(payload), STOP_FAILED(reason)
- TIMER_ERROR(reason)

DataFSM events:
- LOAD_ENTRIES(date), ENTRIES_LOADED(entries, html, total), ENTRIES_FAILED(reason)

Effects to inject:
- effects.startTimer(taskId, title) → Promise<{startUtc, postId, sessionIndex}>
- effects.stopTimer(postId) → Promise<{stopUtc}>
- effects.loadEntries(date) → Promise<{entries, html, total}>
- effects.updateTimerUI(state, ctx), effects.toggleLoading(isOn), effects.renderEntries(html), effects.showError(msg)
- effects.rehydrate() → Promise<{running: boolean, startUtc?, postId?, sessionIndex?}>

---


## Dependencies and Sequencing with PSR‑4

- Pre‑requisites for FSM rollout (lightweight, do not block bug fixes):
  - [ ] PSR‑4 Phase 2: Add UTC/date helpers in ACFAdapter and route Today/Reports timestamp parsing through them
  - [ ] Ensure Plugin acts as stable service container (KISS\PTT\Plugin::$timer,::$sessions,::$acf)
  - [ ] Keep Today procedural handlers intact while FSM is behind feature flag

- Nice‑to‑have (post‑FSM acceptable):
  - [ ] PSR‑4 Phase 3: Introduce thin Services locator (optional)
  - [ ] Migrate Today data builder to src/Presentation/Today/ service after FSM Phase 2


- Progress note (v2.1.7): Introduced PSR‑4 Today\DateHelper::isUtcOnLocalDate and routed Today session-date checks through it. This reduces duplication and supports later DataFSM work without UI refactor.
- Progress note (v2.1.8): Introduced Today\EntryBuilder and delegated task-level entry construction to it; more helpers to follow.


- Not required for initial FSM pilot:
  - [x] Full PSR‑4 completion across all admin pages
  - [x] Session CPT or custom table (Roadmap Phase 4)

## Actionable checklist by phase

### Phase 0 – Preparation (non‑breaking)
- [ ] Create a small effects module interface (just types/stubs)
- [ ] Add a global feature flag window.PTT_FSM_ENABLED (default false)
- [ ] Add debug rendering hook: when ptt_debug=1, show FSM state+nextEvents in the existing debug panel

Acceptance: No behavior changes when the flag is false.

### Phase 1 – Pilot: TimerFSM only (keep current jQuery for everything else)
- [ ] Implement TimerFSM (pure JS class) with states: IDLE, STARTING, RUNNING, STOPPING, ERROR
- [ ] Wire “Start/Stop” buttons to TimerFSM when PTT_FSM_ENABLED=true; otherwise fall back to current handlers
- [ ] Implement effects.startTimer/stopTimer using existing AJAX endpoints; set context from server response (startUtc, postId, sessionIndex)
- [ ] Implement rehydrate on page load: effects.rehydrate() then TimerFSM → RUNNING or IDLE accordingly
- [ ] Guard rapid clicks and double requests; ignore events not allowed in current state
- [ ] Update Today timer display using effects.updateTimerUI on entry/exit of RUNNING
- [ ] Log transitions to console when ptt_debug=1

Acceptance:
- [ ] Cannot start if another task is running (server enforces; UI reflects ERROR with reason "conflict_active_elsewhere")
- [ ] Timer survives page refresh (rehydrates)
- [ ] Start/Stop are idempotent; no duplicate sessions
- [ ] No regressions when feature flag is off

### Phase 2 – DataFSM migration (loading/refresh)
- [ ] Implement DataFSM with IDLE, LOADING, LOADED, ERROR
- [ ] Replace Today page loading with effects.loadEntries(date)
- [ ] Handle stale responses: if selectedDate changed during load, discard old result
- [ ] While TimerFSM is RUNNING, DataFSM loads do not disrupt the timer UI
- [ ] Update UI via effects.toggleLoading and effects.renderEntries

Acceptance:
- [ ] Spinner shows/hides correctly during loads
- [ ] Race conditions avoided (no flicker or stale data after rapid date changes)

### Phase 3 – Consolidation and cleanup
- [ ] Remove legacy jQuery handlers replaced by FSM when flag ships to 100%
- [ ] Keep effects layer as the only place with DOM and AJAX code
- [ ] Add a single TodayController that constructs TimerFSM, DataFSM, and wires events
- [ ] Unit smoke tests for both FSMs (transition tables for happy paths and common errors)

Acceptance:
- [ ] No duplicate event handlers remain
- [ ] FSMs are pure and testable; effects mocked in tests

### Phase 4 – Optional enhancements
- [ ] Local state persistence: store minimal FSM context in localStorage on transitions; restore when rehydration fails
- [ ] Error taxonomy: network_error, permission_error, validation_error, conflict_active_elsewhere
- [ ] Visualization: add simple diagram JSON and render in debug when ptt_debug=1 (or use XState Diagram later)
- [ ] Replace hand‑rolled FSMs with XState if/when hierarchical states are needed

---

## Event and effect contracts (concrete)

- START_TIMER payload: { taskId: number, title: string }
- TIMER_STARTED payload from server: { postId: number, sessionIndex: number, startUtc: "YYYY-MM-DD HH:mm:ss" }
- STOP_TIMER payload: none (TimerFSM uses postId from context)
- TIMER_STOPPED payload from server: { stopUtc: string }
- LOAD_ENTRIES payload: { date: "YYYY-MM-DD" }
- ENTRIES_LOADED payload: { entries: any[], html: string, total: string }

All times are UTC strings. Client‑side elapsed display should treat them as UTC (append 'Z' when creating Date objects).

---

## Rollout plan

- Ship Phase 1 behind window.PTT_FSM_ENABLED and ptt_debug=1 defaulting it on for admins only
- Observe logs and user feedback; fix issues
- Enable for all admins → then for editors → then for all users
- Proceed to Phase 2 once Start/Stop stability is confirmed

---

## Notes

- Keep server authoritative for collisions and invariants (one active session per user). Client FSM reflects, not dictates, truth.
- Keep Today page durations and all persisted timestamps in UTC; UI formatting stays local.
- If we later adopt XState, we can lift these pure FSMs into XState models with minimal code churn.


/**
 * Finite State Manager for PTT Today Page
 * Manages timer states, data loading, and UI interactions
 */

class TodayPageFSM {
    constructor() {
        this.currentState = 'IDLE';
        this.context = {
            activeTaskId: null,
            activeSessionIndex: null,
            timerStartTime: null,
            selectedDate: new Date().toISOString().split('T')[0],
            entries: [],
            isLoading: false,
            error: null
        };

        this.listeners = new Set();
        this.timerInterval = null;

        // Bind methods
        this.transition = this.transition.bind(this);
        this.send = this.send.bind(this);
        this.subscribe = this.subscribe.bind(this);
    }

    // Define all possible states and their allowed transitions
    get machine() {
        return {
            IDLE: {
                on: {
                    LOAD_ENTRIES: 'LOADING',
                    START_TIMER: 'STARTING_TIMER',
                    START_QUICK: 'STARTING_QUICK_START'
                },
                entry: () => this.stopTimerDisplay()
            },

            LOADING: {
                on: {
                    ENTRIES_LOADED: 'IDLE',
                    ENTRIES_FAILED: 'ERROR'
                },
                entry: () => this.showLoading(),
                exit: () => this.hideLoading()
            },

            STARTING_TIMER: {
                on: {
                    TIMER_STARTED: 'TIMER_RUNNING',
                    START_FAILED: 'IDLE'
                }
            },

            STARTING_QUICK_START: {
                on: {
                    QUICK_START_CREATED: 'TIMER_RUNNING',
                    QUICK_START_FAILED: 'IDLE'
                }
            },

            TIMER_RUNNING: {
                on: {
                    STOP_TIMER: 'STOPPING_TIMER',
                    TIMER_ERROR: 'ERROR',
                    LOAD_ENTRIES: 'LOADING_WITH_TIMER' // Special state for refreshing while timer runs
                },
                entry: () => this.startTimerDisplay(),
                exit: () => this.stopTimerDisplay()
            },

            LOADING_WITH_TIMER: {
                on: {
                    ENTRIES_LOADED: 'TIMER_RUNNING',
                    ENTRIES_FAILED: 'TIMER_RUNNING' // Keep timer running even if load fails
                },
                entry: () => this.showLoading()
            },

            STOPPING_TIMER: {
                on: {
                    TIMER_STOPPED: 'IDLE',
                    STOP_FAILED: 'TIMER_RUNNING'
                }
            },

            ERROR: {
                on: {
                    RETRY: 'IDLE',
                    FORCE_RESET: 'IDLE'
                },
                entry: (error) => this.showError(error)
            }
        };
    }

    // Transition between states
    transition(event, payload = {}) {
        const currentStateConfig = this.machine[this.currentState];
        if (!currentStateConfig || !currentStateConfig.on || !currentStateConfig.on[event]) {
            console.warn(`Invalid transition: ${event} from state ${this.currentState}`);
            return false;
        }

        const nextState = currentStateConfig.on[event];
        const prevState = this.currentState;

        // Exit current state
        if (currentStateConfig.exit) {
            currentStateConfig.exit(payload);
        }

        // Update state
        this.currentState = nextState;
        this.context = { ...this.context, ...payload };

        // Enter new state
        const nextStateConfig = this.machine[nextState];
        if (nextStateConfig && nextStateConfig.entry) {
            nextStateConfig.entry(payload);
        }

        // Notify listeners
        this.notifyListeners(prevState, nextState, event, payload);

        console.log(`FSM: ${prevState} → ${nextState} (${event})`);
        return true;
    }

    // Public method to send events
    send(event, payload = {}) {
        return this.transition(event, payload);
    }

    // Subscribe to state changes
    subscribe(callback) {
        this.listeners.add(callback);
        return () => this.listeners.delete(callback);
    }

    // Notify all listeners of state changes
    notifyListeners(prevState, nextState, event, payload) {
        this.listeners.forEach(callback => {
            try {
                callback({
                    prevState,
                    nextState,
                    event,
                    payload,
                    context: this.context
                });
            } catch (error) {
                console.error('FSM listener error:', error);
            }
        });
    }

    // State entry/exit actions
    showLoading() {
        this.context.isLoading = true;
        document.querySelector('#ptt-today-entries-list .ptt-ajax-spinner')?.style.setProperty('display', 'block');
    }

    hideLoading() {
        this.context.isLoading = false;
        document.querySelector('#ptt-today-entries-list .ptt-ajax-spinner')?.style.setProperty('display', 'none');
    }

    startTimerDisplay() {
        const { activeTaskId, timerStartTime } = this.context;
        if (!timerStartTime) return;

        const timerDisplay = document.querySelector('.ptt-today-timer-display');
        const startBtn = document.querySelector('#ptt-today-start-stop-btn');

        if (startBtn) {
            startBtn.textContent = 'Stop';
            startBtn.classList.remove('button-primary');
            startBtn.classList.add('button-secondary', 'running');
        }

        // Start live timer update
        this.timerInterval = setInterval(() => {
            if (timerDisplay && this.currentState === 'TIMER_RUNNING') {
                const now = new Date();
                const start = new Date(timerStartTime + 'Z'); // Treat as UTC
                const diff = now - start;

                const hours = Math.floor(diff / 3600000);
                const minutes = Math.floor((diff % 3600000) / 60000);
                const seconds = Math.floor((diff % 60000) / 1000);

                timerDisplay.textContent =
                    `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
            }
        }, 1000);
    }

    stopTimerDisplay() {
        if (this.timerInterval) {
            clearInterval(this.timerInterval);
            this.timerInterval = null;
        }

        const timerDisplay = document.querySelector('.ptt-today-timer-display');
        const startBtn = document.querySelector('#ptt-today-start-stop-btn');

        if (timerDisplay) {
            timerDisplay.textContent = '00:00:00';
        }

        if (startBtn) {
            startBtn.textContent = 'Start';
            startBtn.classList.add('button-primary');
            startBtn.classList.remove('button-secondary', 'running');
        }

        // Clear context
        this.context.activeTaskId = null;
        this.context.activeSessionIndex = null;
        this.context.timerStartTime = null;
    }

    showError(error) {
        this.context.error = error;
        console.error('Today Page Error:', error);
        // Could show toast notification or error message
    }

    // Helper methods for common state checks
    get isTimerRunning() {
        return this.currentState === 'TIMER_RUNNING';
    }

    get isLoading() {
        return this.currentState === 'LOADING' || this.currentState === 'LOADING_WITH_TIMER';
    }

    get canStartTimer() {
        return this.currentState === 'IDLE';
    }

    get canStopTimer() {
        return this.currentState === 'TIMER_RUNNING';
    }

    // Get valid next events for current state
    get nextEvents() {
        const stateConfig = this.machine[this.currentState];
        return stateConfig && stateConfig.on ? Object.keys(stateConfig.on) : [];
    }

    // Debug helper
    getDebugInfo() {
        return {
            currentState: this.currentState,
            context: { ...this.context },
            nextEvents: this.nextEvents,
            isTimerRunning: this.isTimerRunning,
            isLoading: this.isLoading
        };
    }
}

// Usage with existing Today page code
const todayPageFSM = new TodayPageFSM();

// Example integration with existing AJAX handlers
function loadDailyEntries() {
    if (!todayPageFSM.send('LOAD_ENTRIES')) {
        console.warn('Cannot load entries in current state:', todayPageFSM.currentState);
        return;
    }

    const selectedDate = document.querySelector('#ptt-today-date-select')?.value || new Date().toISOString().split('T')[0];

    $.post(ptt_ajax_object.ajax_url, {
        action: 'ptt_get_daily_entries',
        nonce: ptt_ajax_object.nonce,
        date: selectedDate
    }).done(function(response) {
        if (response.success) {
            todayPageFSM.send('ENTRIES_LOADED', {
                entries: response.data.entries || [],
                selectedDate: selectedDate
            });

            // Update UI
            document.querySelector('#ptt-today-entries-list').innerHTML = response.data.html;
            document.querySelector('#ptt-today-total strong').textContent = response.data.total;
        } else {
            todayPageFSM.send('ENTRIES_FAILED', { error: response.data?.message });
        }
    }).fail(function(xhr, status, error) {
        todayPageFSM.send('ENTRIES_FAILED', { error: `${status}: ${error}` });
    });
}

// Example timer start integration
function startTimer() {
    const taskId = document.querySelector('#ptt-today-task-select')?.value;
    const sessionTitle = document.querySelector('#ptt-today-session-title')?.value || 'New Session';
    const clientId = document.querySelector('#ptt-today-client-filter')?.value;

    // Validate based on state machine
    if (!todayPageFSM.canStartTimer) {
        console.warn('Cannot start timer in current state:', todayPageFSM.currentState);
        return;
    }

    if (taskId) {
        todayPageFSM.send('START_TIMER', { taskId, sessionTitle });
        // Make AJAX call...
    } else if (clientId) {
        todayPageFSM.send('START_QUICK', { clientId, sessionTitle });
        // Make Quick Start AJAX call...
    }
}

// Subscribe to state changes for UI updates
todayPageFSM.subscribe(({ nextState, context, event }) => {
    console.log('State changed:', { nextState, context, event });

    // Enable/disable controls based on state
    const controls = document.querySelectorAll('#ptt-today-start-stop-btn, #ptt-today-task-select, #ptt-today-project-filter');
    controls.forEach(control => {
        control.disabled = todayPageFSM.isLoading;
    });

    // Update debug panel if exists
    const debugPanel = document.querySelector('#ptt-debug-content');
    if (debugPanel) {
        const debugInfo = todayPageFSM.getDebugInfo();
        debugPanel.innerHTML = `<pre>${JSON.stringify(debugInfo, null, 2)}</pre>`;
    }
});

// Export for global access
window.todayPageFSM = todayPageFSM;