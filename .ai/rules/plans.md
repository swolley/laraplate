---
paths:
  - 'docs/superpowers/plans/**'
---

# Plans

## Check plan status before starting or resuming work
Before deciding what to work on next, list the development plans and their progress: `bash plan-status` from the laraplate-stack root (whole workspace), or `php scripts/plan-status.php` from laraplate (also `composer run plan-status`). Add `--open` to show only plans still open. Prefer finishing or closing an open plan over starting a new one: do not run many plans in parallel and leave others unfinished.

## Mark a dropped step `- [-]` and say why
A step deliberately not done is `- [-]`, with its reason on the same line: plan-status counts it as resolved but not done, and shows a task whose steps are all cancelled as CANCELLED. Use it when a plan closes without some of its work, and record the decision in the plan's delivery status too. Never leave dropped work as `- [ ]`: the plan would read as open forever.
