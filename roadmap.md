1.x Series (Pre PSR-4 Legacy)
—

## 1. Test Key Helper Functions

The plugin has helper functions in `reports.php` that are crucial for formatting data correctly. The current tests don’t check these directly, meaning a change in their logic wouldn’t be caught.
`	•	ptt_get_assignee_name()`: This function translates a saved assignee ID into a display name.
	- **Proposed Test**: We could add a new test that creates a test user and a test post, assigns the user to the post using `update_post_meta`, and then calls `ptt_get_assignee_name()` on the post’s ID. The test would pass if the function returns the correct display name for the user and fails otherwise. It could also test the fallback “No Assignee” text.
`	•	ptt_format_task_notes()`: This function is responsible for sanitizing, truncating, and converting URLs into links in the task notes displayed in reports.
	- **Proposed Test**: A new test could be created to check this function’s logic. It would pass a string containing HTML and a long URL to the function and then verify that the output:
		- Has the HTML stripped.
		- Is truncated to the correct length (200 characters).
		- Contains a correctly formatted `<a>` tag.
—

## 2. Refine the “Reporting Logic” Test

The current “Reporting Logic” test (Test #5) runs a generic `WP_Query` which is a good start but doesn’t test the complex, custom logic that happens _after_ the query within the `ptt_display_report_results()` function.
- **Test Custom Status Sorting**: A key feature of the reports is the custom sorting based on task status (e.g., “In Progress” before “Completed”).
	- **Proposed Test**: Instead of a broad query, we could make this test specifically check the status sorting logic. It would replicate the steps from the `ptt_display_report_results` function to build the status order map and then verify that the statuses are sorted in the correct, expected order. This would provide a much more meaningful test of the actual reporting feature.


## Security – Today Page Inputs

### Short‑term / Quick wins
- [x] Add per‑post capability checks for all task actions
  - [x] Verify current_user_can('edit_post', $post_id) (and for $target_post_id on moves)
  - [x] Validate post_type === 'project_task' before acting
- [x] Validate and sanitize inputs more strictly
  - [x] Use absint() for IDs; clamp session_index to valid bounds
  - [x] Validate date format strictly (YYYY‑MM‑DD) and reject invalid
  - [x] Clamp duration inputs to [0, 48] hours; support HH:MM(:SS)
  - [x] Keep whitelist mapping for editable fields; prefer ACF field keys
- [x] Harden responses and nonce scope
  - [x] Consistent error messages; maintain check_ajax_referer('ptt_ajax_nonce', 'nonce')

### Mid‑/Long‑term
- Centralize validation in a TodayService layer called by all endpoints
- Replace admin‑ajax endpoints with REST routes using argument schema and permission_callback
- Add rate‑limit/debounce on write operations (e.g., transient‑based per user)
- Add audit logging for session create/update/delete/move events
- Add automated tests covering authZ, parameter validation, and negative cases

## Performance – Today Page

### Short‑term / Quick wins
- [x] Bound and optimize queries
  - [x] Add 'no_found_rows' => true and 'suppress_filters' => true to non‑paginated queries
  - [x] Avoid 'posts_per_page' => -1; use a reasonable cap or batch
  - [ ] For dropdown queries: 'fields' => 'ids', disable meta/term cache updates (N/A in final UI after consolidation)
- [x] Remove N+1 selector queries
  - [x] Preload the user’s task <option> list once per response, or lazy‑load via a single AJAX call
- [x] Reduce ACF overhead immediately
  - [x] Early‑exit processing when a task has no sessions on the target date
  - [x] Add a 60‑second cache for Today entries; invalidate on acf/save_post affecting sessions

### Mid‑/Long‑term
- Introduce PSR‑4 services and repositories to centralize data access
  - ACFAdapter: field‑key access and UTC normalization
  - SessionRepository: minimal‑read access to sessions (avoid full repeater hydration)
  - TodayService: builds entries, owns caching and invalidation
- Add stable session_uuid for each row and update by UUID instead of numeric index
- Coarsely pre‑filter task candidates by post_modified or date_query
- Promote session storage
  - Option B1: Session CPT (ptt_session) with indexed meta and native REST
  - Option B2: Custom table (wp_ptt_sessions) with indexed columns for fast lookups
- Consider prefetching term data once per response (or include in query) to avoid repeated get_the_terms calls
- Add pagination/virtual scrolling for very large daily entry lists


## Security – Audit Follow‑ups (from prior review)

Priority order based on the audit (do not implement yet; track and schedule):

1) Data Authorization (HIGH PRIORITY)
- [x] Introduce ptt_validate_task_access( $post_id, $user_id ) helper; rule: user must be the ptt_assignee to start/move/update sessions
- [x] Enforce in today.php handlers that change state: ptt_today_start_timer_callback, ptt_move_session_callback, ptt_update_session_duration_callback, ptt_update_session_field_callback, ptt_delete_session_callback
- [x] Add unit/integration tests to confirm access is denied for non‑assignees

2) Input Validation (MEDIUM PRIORITY)
- [x] Create centralized ptt_validate_* helpers (ptt_validate_date, ptt_validate_id, ptt_validate_session_index, ptt_validate_duration)
- [x] Refactor all Today AJAX handlers to use these helpers consistently (replace ad‑hoc inline checks)

3) Information Disclosure (LOW PRIORITY)
- [ ] Restrict PTT_Today_Page_Manager::get_debug_info() output to current_user_can('manage_options') only (or behind a filter/feature flag)
- [ ] Ensure the Debug Info panel is hidden for non‑admin roles
