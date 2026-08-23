# Core — In-App Notification System (push vs poll)

**Status:** proposed · **draft — deep analysis pending** · **Module:** Core (consumed by all modules)
**Related:** `2026-05-15-modification-moderation-design.md`,
`2026-07-16-in-app-ai-assistance-security-design.md`,
`2026-08-23-core-bulk-import-mapping-design.md`.

> One of four specs scaffolded together. Analysed and implemented on its own later.

## The idea

A first-class in-app notification tray. Many features need to reach a user or
admin: **AI-chat operations** awaiting moderation/admin intervention, an **admin
rejecting a modification and asking for corrections**, **deadline reminders**, and
existing admin alerts. Decide **push vs poll** and give every producer one durable
channel and one preference surface.

## What already exists (and the gaps)

- `Notifiable` is on **`User` only**. Two `Notification` classes:
  `Core\PendingApprovalsNotification` (default channels **mail/slack**) and
  `MES\MaterialShortageNotification` (default **database**). Sent via
  `Notification::send()` from `ApprovalNotificationService`.
- **No `notifications` table migration exists in-app** — the `database` channel
  cannot persist until it is published. No `DatabaseNotification` in production
  code.
- **No broadcasting stack**: no `config/broadcasting.php`, `BROADCAST_CONNECTION=log`,
  no Reverb/Pusher/Echo/websockets in composer or npm.
- **Filament** 5.7.6 is present; its **database-notifications feature is NOT
  enabled** (`->databaseNotifications()` called nowhere). Filament transient
  **toasts** (`Notification::make()`) are used widely but are ephemeral.
- **Latent consumers already modelled**:
  - AI `ActionRequest` (`pending_user_confirmation` / `pending_admin_approval` /
    `rejected` / executed), linked to a Core `Modification` — but **no notification
    is emitted** when admin action is needed.
  - Core moderation pipeline (`Modification`, `Approval`/`Disapproval`,
    `ModificationRequiresModeration` event, `ModerationAdapterRegistry`).
    "Reject and request correction" is only latent: `Disapproval.reason` free-text
    + `Modification.active`; **no feedback notification** to the modifier, and no
    `needs_correction` status.
  - Deadline feeders: `SAO Ticket::overdue()` / `dueWithin()` (`due_at`), ERP
    payment schedules (`PaymentScheduleLine`, `PaymentRunLine`). The one alerting
    command `approvals:check-pending` exists but **is not scheduled** (no
    `Schedule::` registration anywhere).
- **Preference plumbing**: per-user `User.preferences` JSON (via
  `UpdatePreferencesRequest`); global `Setting` + `PerModelSettingResolver`
  (e.g. `approval_threshold__{table}`); role fan-out in `ApprovalNotificationService`.

## Proposed direction

**Foundation (channel + store):**
- Publish the Laravel `notifications` table; standardise on the **`database`
  channel** as the durable in-app feed, with a consistent `toArray()` payload
  (`type`, `title`, `body`, `url`, `severity`, `actor`, morph `subject`). Keep
  `Notifiable` on `User`.

**Transport — start POLL, keep PUSH a later transport swap:**
- **Filament admin:** enable `->databaseNotifications()` (+ polling interval) for
  the bell/feed — zero new dependencies.
- **Vue SPA:** a notifications API (paginated list, unread-count, mark-read,
  mark-all-read) with client polling (~30–60s) — the in-app tray.
- **Push (later, optional, behind a flag, needs approval):** add Reverb + Echo and
  `toBroadcast()` on the *same* `Notification`, so poll → push is a transport
  change, not a data-model change. No websockets dependency in phase 1.

**Producers — wire the latent consumers via events/adapters:**
- **AI `ActionRequest`:** notify admins on `pending_admin_approval`, the user on
  `pending_user_confirmation`, and the user on reject/approve/executed.
- **Modification moderation:** notify moderators on pending; notify the original
  modifier on disapproval, carrying the `reason`. Model **"request correction"** as
  a first-class outcome (new `needs_correction` status, or `Disapproval.reason` +
  re-open) — decide in analysis.
- **Deadlines:** finally register a scheduled command scanning `Ticket::dueWithin`/
  `overdue` and ERP payment schedule lines, emitting reminders deduped per
  `(subject, window)`.
- **Reuse** existing `PendingApprovals` + `MaterialShortage` by adding the
  `database` channel alongside mail/slack.

**Preferences:** per-user, per-notification-type channel opt-in stored in
`User.preferences`; global defaults via `Setting`/`PerModelSettingResolver`. A small
Core **notification-type catalog/enum** keeps rendering and preference keys
consistent.

## Open decisions

- Push now vs later (recommend **poll first**, push as an approved increment).
- Adopt Filament's feed for admin **and** build the SPA feed over the same
  `DatabaseNotification` store (recommend both).
- Notification-type taxonomy and preference granularity.
- "Request correction" modelling: new status vs `reason` + reopen.
- Read-notification retention / cleanup; grouping / digest.
- Scheduler registration home (a Core scheduled provider vs app) — note the app
  currently registers **no** schedule; this feature likely establishes it.
- Fan-out reuse (roles → users) from `ApprovalNotificationService`.

## Out of scope (phase 1)

No websockets/broadcasting dependency; poll only. Push (Reverb/Echo) is a later,
approval-gated increment on the same data model.

## Sequencing

1. `notifications` migration + `DatabaseNotification` conventions + SPA feed API +
   enable Filament feed (poll).
2. Wire producers (AI `ActionRequest`, modification feedback, deadline reminders) +
   register the scheduler.
3. Per-user preferences + notification-type catalog.
4. Optional push (Reverb/Echo) behind a config flag.
