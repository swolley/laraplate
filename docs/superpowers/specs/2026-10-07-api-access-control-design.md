# API access control: guards, default roles and tokens

**Status:** Draft for owner review. Nothing in this document is built.

**Date:** 2026-10-07

**Scope:** `Modules/Core` (authorization, permissions, seeders, API middleware, tokens), with
consequences for CMS (public ACLs and hidden attributes), ERP, SAO and MES (their `/api` routes become
subject to the same switch and authentication).

**Related:** `2026-09-12-mcp-server-design.md` (MCP tokens build on this), stack-root
`docs/superpowers/specs/2026-10-05-mes-machine-data-acquisition-design.md` (machine routes migrate to this
authentication), `docs/superpowers/plans/2026-06-30-erp-hardening-spec2-phase3-remaining.md` (Task 2, per-entity
exposure, stays a later refinement; Task 11, Sanctum for the external API, is superseded by this spec).

---

## 1. Purpose

One access model for every route under `/api`: who is calling (a token or nobody), through which surface
(the `api` guard, never the `web` one), with which permissions (roles of that guard, the token's abilities,
row-level ACLs), and from where (optional network restrictions). Turning the API on must expose nothing that
a role of the `api` guard has not been explicitly granted.

## 2. Current state (verified 2026-10-07)

| Finding | Evidence |
|---|---|
| The `api` middleware group has no authentication. | `bootstrap/app.php`: the `api` group is Laravel's default; `auth` is never applied to `api/v1`. |
| A request with no user falls back to the `anonymous` user. | `AuthorizationService::resolveUser()` logs in `anonymous` when `$request->user()` is null. |
| Permission checks always use the `web` guard. | `AuthorizationService::passesPermission()` reads `Auth::guard()`; nothing in the codebase calls `Auth::shouldUse()`, so it is the default guard `web` on every route, `api/v1` included. |
| Only `web` permissions exist. | 888 permissions, all `guard_name = web`; `PermissionsRefreshCommand` creates them for `config('auth.defaults.guard')` only. |
| Role inheritance ignores the guard. | `User::hasPermission()` falls back to `Role::hasPermission()`, which calls `hasPermissionTo($permission)` without a guard. |
| `guest` holds every `select`. | `CoreDatabaseSeeder` grants the `guest` role every `select` permission except `versions`, `modifications`, `cron_jobs`: 140 permissions here, including `users`, `erp_invoices`, `erp_invoice_lines`, `erp_companies`. Row-level ACLs exist only on `core_settings` (public) and `cms_contents` (published). |
| The API switch covers only Core routes. | `core.crud.expose_api` (seeded setting `crud.expose_api`, default `false`) gates the Core CRUD and graph routes through `EnsureCrudApiAreEnabled`; module `/api` routes (ERP callbacks, SAO webhooks, MES machine data, Shop) are not gated. |
| `User` cannot hold tokens. | `HasApiTokens` is absent from `Modules\Core\Models\User`; the only tokenable model is `Modules\MES\Models\MachineSource`. |
| Existing roles are never realigned. | The role seeder creates missing roles only; `SeedReconciler` reconciles rows, not role-permission pivots, and cannot revoke. |

The exposure is latent while the API switch is off (its default), and real the moment it is turned on.

## 3. Decisions

| # | Decision |
|---|---|
| A1 | Every permission exists for both guards, `web` and `api`. `web` permissions already exist; `api` ones are added for every table and action. Existing does not mean granted. |
| A2 | Every route under `/api` runs on the `api` guard, and permission checks use the guard of the current request on every path, direct and inherited. A permission missing for the guard is a denial (403), never an error. |
| A3 | One authentication for every `/api` route: an optional bearer token. A token that is present but invalid, expired or revoked is a 401 and never falls back. No token means the `anonymous` user with the roles of the `api` guard. |
| A4 | One switch for the whole API: `core.crud.expose_api` is renamed `core.expose_api` (seeded setting `expose_api`, default `false`) and gates every `/api` route, Core and modules. |
| A5 | Roles are keyed by `(name, guard)`. `guest` on `web` has no permissions. `guest` on `api` holds the public list of section 6. Every other role keeps its `web` permissions and holds no `api` permission by default. |
| A6 | Attributes a model declares hidden from anonymous callers are removed from responses to the `anonymous` user. ACLs filter rows; this filters columns. |
| A7 | Users may create personal tokens; administrators create service accounts (non-interactive users) and their tokens. A request needs both the permission of an `api` role and the token's ability. Superadmin tokens are refused. |
| A8 | No migration for existing installations: they are rebuilt with `migrate:fresh --seed`. |

## 4. Permissions per guard

- `PermissionsRefreshCommand` creates every permission for `web` and `api` (`guard_name`), same names.
- `AuthorizationService` checks against the guard of the current request (the driver set by the API
  middleware, otherwise the default), not against `Auth::guard()` read as the default guard.
- `User::hasPermission($name, $guard)` and `Role::hasPermission($name, $guard)` take the guard on every path:
  direct permissions, roles, and ancestor roles; only roles of that guard are considered.
- Spatie's `PermissionDoesNotExist` for a guard is caught and treated as "not granted".
- `canAccessPanel()` and every other caller that passes a guard keep working unchanged; callers that pass
  none get the request's guard.

## 5. The API surface

### 5.1 Middleware

A Core middleware, `AuthenticateApiRequest`, runs first in the `/api` group of Core and of every module
(Core's `RouteServiceProvider::registerApiRoutes()` and the module base `Overrides\RouteServiceProvider::mapApiRoutes()`):

1. `core.expose_api` false: 403, as `EnsureCrudApiAreEnabled` does today (that middleware is folded in).
2. `Auth::shouldUse('api')`.
3. An `Authorization: Bearer` header present: the Sanctum guard resolves the token. No user: 401. The token
   belongs to a superadmin: 401. The token has `allowed_cidrs` and the client address is outside them: 403,
   logged without the token.
4. No header: the request user is `anonymous` (resolved without a session: the `api` guard is not a session
   guard, so the user is set on the guard and on the request resolver, not logged in).
5. Rate limit per token, or per client address for anonymous requests (`core.api.rate_limit_per_minute`,
   default 600).

### 5.2 One switch

`core.expose_api` gates every `/api` route. Consequences, stated in each module's documentation:

- An installation with the API off receives no ERP payment or e-invoicing callbacks, no SAO webhooks and no
  MES machine data. Using any of them requires the API on.
- With the API on, access is decided by the next layers: roles of the `api` guard, token abilities, ACLs,
  network restrictions. Per-entity exposure (ERP hardening Task 2) remains a later refinement.

### 5.3 Callers that cannot hold a token

Payment providers, e-invoicing providers and webhook senders arrive without a token, as `anonymous`. Their
routes keep verifying the provider's signature on the content; that check is in addition to authentication,
not instead of it. A request with no valid signature is refused even though it reached the route.

### 5.4 Rename

The seeded setting `crud.expose_api` becomes `expose_api` (config `core.expose_api`), with
`CrudApiExposure`, `EnsureCrudApiAreEnabled`, their tests, `Modules/Core/README.md` and
`Modules/Core/docs/CRUD_SYSTEM.md`. Closed specs and plans that name the old key are dated records and stay
as written.

## 6. Default roles

`CoreDatabaseSeeder` keys roles by `(name, guard)` and declares, per role, the permissions of each guard.

- `guest` (`web`): no permissions. `/app` is for authenticated users only.
- `guest` (`api`): the public list below, with its ACLs. The `anonymous` user holds `guest` on both guards.
- `admin`, `publisher`, `system`: their current `web` permissions, no `api` permission.
- `superadmin`: unchanged (no permissions, full access by construction); cannot use tokens.
- Roles of the `api` guard for people and integrations are created by administrators when needed.

Public list of `guest` (`api`), all `select`:

| Entity | Row condition (ACL) | Hidden from anonymous |
|---|---|---|
| `core_settings` | `is_public` | |
| `cms_contents` | currently published (existing ACL) | |
| `cms_contents_references` | owner content currently published | |
| `vend_media` | owner is a CMS content currently published | |
| `cms_tags` | none | |
| `cms_locations` | none | |
| `cms_contributors` | none | `user_id`; `shared_components` too unless the plan verifies it holds no personal data |
| `cms_comments` | none by default: `Comment` uses `HasApprovals`, so pending comments never reach the table and every row is already approved. An installation may still add its own ACL (e.g. only comments of published contents) | `user_id` |
| `cms_contents_ratings` | none | `user_id` |

Every row without a default ACL may still receive one from an installation; "none" means no default, not
that ACLs are excluded.

The owner conditions on `cms_contents_references` and `vend_media` need an ACL filter on a related record.
ACL filters today are column conditions on the entity's own table (`AuthorizationService::applySingleFilter()`),
so the plan extends the filter language with a relation filter (a `whereHas` on a named relation, morph
relations included, with nested filters on the related record). A `vend_media` ACL limited to `model_type`
alone is not acceptable: the table also holds SAO ticket attachments, and a media of an unpublished content
would still be public.

A guard test fails when the `guest` (`api`) grant set differs from this list, and when `guest` (`web`) holds
any permission.

## 7. Hidden attributes for anonymous callers

- A model implements a Core contract declaring the attributes never returned to the `anonymous` user
  (`anonymousHiddenAttributes(): list<string>`).
- The CRUD response layer removes them when the request user is `anonymous`, on every action that returns
  records (list, detail, search, tree, history) and on relations loaded with them.
- Authenticated callers, staff UIs and Filament see the attributes as today.

## 8. Tokens

### 8.1 Model

- `HasApiTokens` on `Modules\Core\Models\User`.
- `personal_access_tokens` gains `allowed_cidrs` (json, nullable), folded into its create migration.
- Abilities are permission names of the `api` guard (`default.cms_contents.select`). `*` is refused.
- A request passes when the user holds the permission through an `api` role **and** the token carries it as
  an ability.
- Expiry: personal tokens always expire, at most `core.api_tokens.max_lifetime_days` (default 365). Service
  account tokens may be non-expiring; the backoffice marks them.
- `last_used_at` is shown wherever tokens are listed.

### 8.2 Personal tokens

- Routes under `/app/auth/user/tokens` (session, `auth` group): list, create, revoke the caller's own tokens.
- On creation the abilities must be a subset of the `api` permissions the user's roles grant; anything else
  is a 422. The plain token is returned once.
- A superadmin cannot create tokens.

### 8.3 Service accounts

- `users.is_service_account` (boolean, default false), folded into the users create migration.
- A service account has no usable password, cannot log in through Fortify (`authenticateUsing` refuses it),
  cannot open a Filament panel, and authenticates only with tokens.
- Administrators create service accounts, assign their `api` roles and issue and revoke their tokens in the
  Filament user resource.

## 9. Consumers

- **MCP** (`2026-09-12-mcp-server-design.md`): uses personal tokens and the `api` guard as defined here.
- **MES machine data**: the machine routes move from tokens owned by `MachineSource` to service accounts.
  `mes_machine_sources.user_id` points at the source's service account; the source is resolved from the
  authenticated user; `mes:machine-*` abilities become `api` permissions on the machine entities. Rate limits
  and `auth_failure` incidents stay. No consumer exists yet, so there is nothing to migrate. This change
  belongs to a MES plan that follows the Core plan, and is reflected in the stack-root machine data spec
  (section 7.3) and in the edge agent spec.
- **ERP, SAO**: their callbacks and webhooks come under the switch (5.2) and run as `anonymous` with
  signature verification (5.3).
- **ERP hardening Task 11** (Sanctum for an external API) is superseded by this spec; Task 2 (per-entity
  exposure) is unchanged.

## 10. Upgrade note

There is no migration of roles or permissions. An existing installation keeps `guest` with its 140 `web`
`select` permissions until it is rebuilt with `php artisan migrate:fresh --seed`. The Core README states this
in its upgrade section, next to the rename of the API switch.

## 11. Out of scope

- Per-entity API exposure (ERP hardening Task 2).
- Column-level permissions beyond the anonymous hidden list of section 7.
- Mutual TLS (left to the reverse proxy, documented as an option).
- OAuth or any third-party identity provider.

## 12. Testing

| Area | Proof |
|---|---|
| Guards | A permission granted only on `web` does not pass on an `api` request, through direct permission, role and ancestor role; and the reverse. A permission missing for the guard yields 403, not 500. |
| Switch | With `core.expose_api` off, Core and module `/api` routes answer 403; on, they reach authentication. |
| Authentication | Invalid, expired or revoked token: 401, never `anonymous`. Superadmin token: 401. Address outside `allowed_cidrs`: 403. No token: `anonymous` on the `api` guard. |
| Abilities | Permission without ability: 403. Ability without permission: 403. Both: allowed. |
| Default roles | Guard test on the exact `guest` (`api`) grant set and on `guest` (`web`) being empty. |
| Public ACLs | An unpublished content, its references and its media are invisible to `anonymous`; a SAO ticket attachment in `vend_media` is invisible. |
| Hidden attributes | `user_id` absent for `anonymous` on contributors, comments and ratings, present for staff. |
| Personal tokens | Abilities outside the user's `api` permissions: 422; superadmin cannot create; plain token shown once. |
| Service accounts | Fortify login refused, panel refused, token accepted. |
| Signed callbacks | A provider callback without a valid signature is refused as `anonymous`. |

## 13. Delivery

One Core plan, in this order: guard-aware checks and `api` permissions; the middleware, the switch and its
rename; default roles and public ACLs; hidden attributes; tokens and service accounts; documentation (Core
README with the upgrade note, `CRUD_SYSTEM.md`, CMS, ERP, SAO and MES notes on the switch). The MES machine
route migration follows as its own MES plan.
