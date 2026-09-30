---
paths:
  - 'docs/superpowers/plans/**'
---

# Plans

## Check plan status before starting or resuming work
Before deciding what to work on next, list the development plans and their progress: `bash plan-status` from the laraplate-stack root (whole workspace), or `php scripts/plan-status.php` from laraplate (also `composer run plan-status`). Add `--open` to show only plans still open. Prefer finishing or closing an open plan over starting a new one: do not run many plans in parallel and leave others unfinished.
