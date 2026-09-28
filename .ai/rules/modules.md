---
paths:
  - 'Modules/**/*.php'
---

# Modules

## Run Pint from the repository root on explicit files
Run `vendor/bin/pint --format agent <files>` from the laraplate root only, always with an explicit file list. Modules have no pint.json: running Pint inside `Modules/<Name>` falls back to a different preset and reformats unrelated files. `--dirty` from the root does not see files changed inside the module submodules, so list them. Never run Pint with an empty file list: it formats the whole project.
