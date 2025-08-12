# Session Recordings Quick Start

A concise guide to starting and managing session recordings quickly from the Today page.

## Overview
The Quick Start flow lets you begin recording work sessions with minimal input:
- Choose a Client on the Today page and click Start
- The plugin creates/uses a per-user daily Quick Start task under the "Quick Start" project (scoped by user and client)
- An initial session is started immediately with an auto-generated title

Key goals:
- Reduce friction to start tracking time
- Keep work attributed to the right Client without context switching
- Make it easy to reassign the session later to the appropriate permanent task

## How it works
1. On the Today page, select a Client and click Start
2. The system creates/uses your daily Quick Start task for that Client under the "Quick Start" project
3. A new session is created and the timer starts; a default title like "Session 3:42 PM" is generated
4. You may stop, resume, or move the session to any of your tasks for the same Client

### Task Selector (Move Session)
- For Quick Start entries, the selector lists any of your tasks that match the same Client (Project is not required)
- For non-Quick-Start entries, the selector lists tasks that match the same Project; if the entry has a Client, tasks must also match that Client
- An inline hint appears beneath the selector for Quick Start entries: “Quick Start: showing tasks for client (project not restricted)”

### Visual Cues
- Quick Start entries display a small "Quick Start" badge next to the session title
- The Today page Debug panel explains the filtering rules and what appears for the selected date

## History of changes
- v1.12.1
  - Relaxed reassignment for Quick Start sessions to "same Client only" (Project not required)
  - Added inline hint under Task Selector for Quick Start entries
  - Updated Debug panel narrative to reflect new reassignment rules
  - Self Tests UI: Added summary at top ("Number of Tests: X out of Y Failed"), a "Jump to first failed" link, and a green “All tests have passed.” note when zero failures
- v1.12.0
  - Introduced Quick Start on the Today page with Client selection
  - Auto session title format (localized) and improved UX for daily workflow

## Road Map
Under Consideration...
- “A customizable pattern” lets you define the format in settings and we render it dynamically.

Potential future enhancements
- Optional toggle to switch Quick Start reassignment list between “Client-only” and “Project + Client”
- Inline hint may include the Client name (e.g., “showing tasks for client Neochrome”)
- Additional test summaries or filters in the Self Tests table

