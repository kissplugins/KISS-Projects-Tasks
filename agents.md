Agents.MD - LLM Maintainer Guidelines: WordPress Project & Task Time Tracker Plugin
This document provides an overview of the "Project & Task Time Tracker" WordPress plugin and critical guidelines for LLM maintainers to ensure consistency, stability, and efficient development.

1. Project Outline
The "Project & Task Time Tracker" is a WordPress plugin designed to facilitate time tracking for developers on client projects and individual tasks.

Key Components:
Custom Post Type (CPT): project_task for individual task entries.

Custom Taxonomies: Clients and Projects for categorization.

ACF Pro Integration: Leveraged for custom fields (Start Time, Stop Time, Calculated Duration).

Time Tracking Logic: Automated calculation of task duration with manual override capabilities.

User Interfaces:
Enhanced WordPress admin CPT editor with Start/Stop buttons.
Front-end shortcode [task-enter] for streamlined time entry.

Reporting: Admin section for comprehensive time reports with filtering, subtotals, and grand totals.

Self-Testing Module: Built-in tests for core functionalities to prevent regressions.

Core Principles:
Increment version numbers up for every change performed.

Please update changelog.md every update with version number and notes.

Modern WordPress Best Practices: Adherence to official WordPress coding standards, security guidelines, and API usage.

DRY (Don't Repeat Yourself): Emphasis on reusable code and minimizing redundancy.

Security: Robust input sanitization, output escaping, and nonce verification.

Performance: Optimized database interactions and AJAX for key features.
User-Centric Design: Intuitive interfaces for time entry and reporting.

2. Guidelines for LLM Maintainers
When making modifications, updates, or bug fixes to this plugin, please adhere to the following critical guidelines:

2.1. Minimize Refactoring
Purpose-Driven Refactoring: Refactoring should only be undertaken when absolutely necessary to:
Fix a critical bug that cannot be resolved otherwise.
Implement a new feature that genuinely requires a structural change for proper integration.
Significantly improve performance or security where current implementation poses a risk.
Avoid "Just Because" Refactoring: Do not refactor code solely for aesthetic preferences or minor stylistic changes if the existing code is functional, readable, and adheres to standards. Unnecessary refactoring introduces risk of new bugs and increases maintenance overhead.
Small, Incremental Changes: When refactoring is necessary, aim for the smallest possible change set to minimize impact and simplify review.
2.2. Preserve Code Documentation
PHPDoc Comments: The existing PHPDoc-style comments (function descriptions, parameter types, return values, etc.) are crucial for code readability and maintainability.

Do NOT modify PHPDoc comments unless the underlying code logic, parameters, or return values have genuinely changed and the comments are no longer accurate.

Ensure any new functions or modified functions have accurate and comprehensive PHPDoc comments.
Table of Contents (TOC) & Numbered Modules: The main plugin file includes a numbered Table of Contents at the top, and the code is organized into numbered modules.

Do NOT modify the TOC or module numbering/titles unless a significant new module is added, or an existing module is fundamentally restructured, requiring an update to reflect the new organization.
Maintain the existing structure for consistency.

2.3. Adhere to WordPress Standards & DRY Principles
WordPress Coding Standards: Always ensure new or modified code strictly follows the WordPress PHP, CSS, and JavaScript coding standards. This includes naming conventions, indentation, spacing, and comment styles.
WordPress API Usage: Prioritize the use of existing WordPress APIs and functions (e.g., register_post_type, register_taxonomy, add_meta_box, wp_insert_post, update_post_meta, wp_localize_script, add_shortcode, add_menu_page, wp_date, current_time, etc.). Avoid reinventing the wheel.
DRY (Don't Repeat Yourself):
Identify and abstract common functionalities into reusable functions or classes.
Avoid duplicating code blocks. If you find yourself writing the same logic multiple times, consider creating a helper function.
Security: Maintain rigorous security practices:
Sanitization: Sanitize all user inputs (sanitize_text_field, sanitize_textarea_field, absint, floatval, etc.).
Escaping: Escape all output (esc_html, esc_attr, esc_url, wp_kses, etc.) before displaying it to the user.
Nonces: Always use nonces for forms and AJAX requests to prevent CSRF attacks.

Capabilities: Ensure all actions are properly gated by user capabilities.
2.4. Testing
Utilize Self-Tests: Before and after any code changes, run the built-in "Self Test" suite to ensure core functionalities remain intact and no regressions have been introduced.
Add New Tests: If a new feature is added or a complex bug is fixed, consider adding new self-tests to cover that specific functionality or edge case.
By following these guidelines, LLM maintainers can contribute effectively to the plugin's evolution while preserving its integrity and simplifying future maintenance.
