## Changelog:

- 08-24-2025 - Initial draft proposing a two-phase Finite State Manager for the Today page.

# Today Page Finite State Manager Proposal (v1.x)

## Table of Contents

I. [Overview](#i-overview)
II. [Phase 1 – Core State Manager](#ii-phase-1--core-state-manager)
III. [Phase 2 – Diagnostics and Hardening](#iii-phase-2--diagnostics-and-hardening)
IV. [Future Enhancements](#iv-future-enhancements)

## I. Overview

The Today page aggregates active work sessions and AJAX interactions. As features grow, maintaining predictable behavior and debugging issues becomes challenging. A dedicated Finite State Manager (FSM) will provide a single source of truth for UI and timer states, improving reliability and visibility into state transitions.

## II. Phase 1 – Core State Manager

**Goal:** Establish a lightweight FSM to coordinate Today page interactions.

**Key Tasks:**

1. Implement a `TodayStateManager` module in `scripts.js` with clearly defined states:
   - `idle` – no timer running.
   - `loading` – awaiting AJAX responses.
   - `running` – timer active.
   - `paused` – timer halted but not saved.
   - `error` – unexpected failure.
2. Expose methods `getState()`, `setState()`, and `onChange()` for other scripts to query or react to state changes.
3. Route existing Today page actions (start/stop timer, quick start, task selection) through the FSM.
4. Log state transitions via `console.debug` and flag illegal transitions to aid debugging.

**Outcomes:**

- Centralized state coordination reduces race conditions.
- Developers can trace issues by reviewing state transition logs.

## III. Phase 2 – Diagnostics and Hardening

**Goal:** Enhance resilience and provide richer debugging utilities.

**Key Tasks:**

1. Persist current state and transition history in a hidden field or `localStorage` to survive page reloads.
2. Add an optional debug panel on the Today page (toggle via query arg) displaying current state, history, and last AJAX response.
3. Integrate server-side validation in `today.php` to confirm state expectations on start/stop requests and return descriptive errors.
4. Extend self-tests to cover basic FSM flows, ensuring transitions like `idle → running → idle` pass and invalid sequences report failures.
5. Provide hooks (`ptt_today_state_change`) for future extensions.

**Outcomes:**

- Improved visibility into client/server synchronization issues.
- Early detection of invalid states or AJAX failures, increasing stability.

## IV. Future Enhancements

- Add unit tests for complex transitions (e.g., pause/resume).
- Consider visual indicators in the UI tied to FSM state.
- Explore using the FSM pattern on other plugin pages for consistency.

