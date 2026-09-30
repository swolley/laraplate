---
paths:
  - 'Modules/**/Filament/**'
---

# Filament

## Filament is the backoffice, user workflows belong to laraplate-ui
Filament serves configuration, monitoring and superadmin maintenance/helpdesk: read views, fix-up actions (e.g. workflow transitions to unstick a record), configuration CRUD. Features for the users who work the data every day (commenting, personal saved filters/views, day-to-day working surfaces) belong to the module's application in laraplate-ui, built on the backend services and models. When a plan asks for such a feature in Filament, build only the backoffice part and record the rest as left to the application.
