# KISS Project & Task Time Tracker - Project Documentation

## Overview
This document serves as the main index for all project documentation for the KISS Project & Task Time Tracker WordPress plugin. The plugin provides comprehensive time tracking functionality with tasks, sessions, and reporting capabilities.

## Project Information
- **Plugin Name**: KISS - Project & Task Time Tracker
- **Version**: 1.11.16
- **Author**: KISS Plugins
- **License**: GPL-2.0+
- **Requirements**: WordPress 5.0+, ACF Pro, PHP 7.0+

## Documentation Index

### 📚 Technical Documentation

#### [Tasks CPT Data Structure Guide](.claude/TASKS-CPT-DATA-STRUCTURE.md)
Comprehensive guide to the custom post type data structure, including:
- Core data structure and registration
- Taxonomies (Client, Project, Task Status)
- ACF fields structure for tasks and sessions
- AJAX endpoints and data formats
- Database schema and data flow
- Best practices for UI development

#### [ACF Architecture Analysis](.claude/ACF-ARCHITECTURE-ANALYSIS.md)
In-depth analysis of the current ACF implementation with:
- Critical performance issues identified
- Detailed performance metrics and scaling analysis
- Recommendations for optimization (60-70% improvement possible)
- Native WordPress migration option (80-90% improvement possible)
- Implementation priorities and cost-benefit analysis
- Scalability concerns and solutions

#### [Today Page Optimization Guide](.claude/TODAY-PAGE-OPTIMIZATION.md)
Specific optimization strategies for the Today page including:
- Current performance profile and critical issues
- Immediate optimizations (50-60% improvement in 1-2 days)
- Medium-term optimizations (70-80% improvement in 3-5 days)
- Long-term solutions (85-95% improvement in 7-10 days)
- Implementation priorities with expected results
- Code examples and migration strategies

### 📋 Project Management

#### [Changelog](.claude/changelog.md)
Complete version history and release notes for the plugin, tracking all features, improvements, and bug fixes.

#### [Roadmap](.claude/roadmap.md)
Future development plans and feature roadmap for upcoming releases.

#### [README](.claude/readme.md)
Basic plugin information, installation instructions, and usage guidelines.

## Key Technical Findings

### Performance Issues Summary
Based on the ACF Architecture Analysis:
- **Current State**: 50-70 unnecessary database queries per operation
- **Root Cause**: Inconsistent ACF usage, excessive recalculation, no caching
- **Impact**: Performance degrades exponentially with scale
- **Breaking Point**: ~2,000-3,000 total sessions

### Recommended Actions
1. **Immediate** (1-2 days): Implement caching layer for 40-50% improvement
2. **Short-term** (3-5 days): Optimize ACF usage for 60-70% total improvement  
3. **Long-term** (7-10 days): Consider native WordPress migration for 80-90% improvement

## Project Structure

```
KISS-Projects-Tasks/
├── .claude/                    # Documentation directory
│   ├── ACF-ARCHITECTURE-ANALYSIS.md
│   ├── TASKS-CPT-DATA-STRUCTURE.md
│   ├── changelog.md
│   ├── readme.md
│   └── roadmap.md
├── templates/                  # Template files
│   └── single-project_task.php
├── project-task-tracker.php    # Main plugin file
├── helpers.php                 # Helper functions
├── today.php                   # Today page functionality
├── today-helpers.php           # Today page helpers
├── reports.php                 # Reporting functionality
├── kanban.php                  # Kanban board feature
├── shortcodes.php              # Frontend shortcodes
├── self-test.php               # Self-test functionality
├── scripts.js                  # JavaScript functionality
├── kanban.js                   # Kanban JavaScript
├── styles.css                  # Plugin styles
└── agents.md                   # AI agent configurations
```

## Quick Reference

### Custom Post Type
- **Slug**: `project_task`
- **Capabilities**: Standard post capabilities
- **Supports**: title, editor, author, revisions

### Taxonomies
- `client` - Hierarchical, for client organization
- `project` - Hierarchical, for project grouping
- `task_status` - Hierarchical, single-select status
- `post_tag` - Standard WordPress tags

### Key Meta Fields
- `ptt_assignee` - User ID of assigned user
- `sessions` - ACF repeater field containing all work sessions
- `calculated_duration` - Total task duration in hours
- `task_max_budget` - Task budget in hours

### Critical AJAX Actions
- `ptt_start_timer` - Start task timer
- `ptt_stop_timer` - Stop task timer
- `ptt_start_session_timer` - Start session timer
- `ptt_stop_session_timer` - Stop session timer
- `ptt_get_daily_entries` - Get entries for Today page
- `ptt_update_task_status` - Update task status

## Development Guidelines

### When Making Changes
1. **Always** check the ACF Architecture Analysis for performance implications
2. **Use** consistent ACF field access patterns (see recommendations)
3. **Implement** caching where possible
4. **Avoid** loading all sessions when only one is needed
5. **Consider** scalability - will this work with 10,000 sessions?

### Testing Checklist
- [ ] Test with 0 sessions
- [ ] Test with 10 sessions
- [ ] Test with 100 sessions
- [ ] Test concurrent user access
- [ ] Verify UTC time handling
- [ ] Check memory usage

## Contact & Support
For questions about this documentation or the plugin architecture, refer to the detailed technical documentation in the `.claude` directory.