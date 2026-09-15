---
status: completed
retrospective: true
written_on: 2026-09-15
---
# Core in-app notifications — Retrospective Plan

> **Retrospective record.** This plan was written on 2026-09-15 by reading the shipped code, not
> before the work. It exists so the design spec is no longer orphaned and so the delivery is
> recorded where plans are looked for. It deliberately carries no task checkboxes: inventing the
> steps somebody actually took would be fiction.

**Spec:** `docs/superpowers/specs/2026-08-23-core-in-app-notifications-design.md`

## What shipped

- `notifications` table (`2026_08_24_000000_create_notifications_table`) with the indexed
  `module_name` column mirrored from `data->scope`.
- `Modules\Core\Models\Notification` with `scopeForModule()`.
- `NotificationController` and `routes/notifications.php`: list, unread count, mark one, mark all.
- Producers `SendImportFinishedNotification` and `ApprovalNotificationService`.
- SPA tray `NotificationBell.vue`, mounted once in `AppShell`.
- The push-vs-poll question the spec asked is answered by what shipped: **poll**. No notification
  implements `ShouldBroadcast` and there is no `channels.php`.

## Delivery status (2026-08-24): shipped

**Documented in:** `Modules/Core/docs/rag/NOTIFICATIONS.md`.

That documentation did not exist until this record was written: the system shipped described only by route comments and one passing mention in `IMPORT_FRAMEWORK.md`. Writing it was part of closing this plan.
