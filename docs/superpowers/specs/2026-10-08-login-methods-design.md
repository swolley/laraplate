# Login methods: registration guard, passkeys and per-method second factor

**Status:** Implemented (2026-10-08, decisions agreed with the owner in chat). Plan: `docs/superpowers/plans/2026-10-08-login-methods.md`.

**Date:** 2026-10-08

**Scope:** `Modules/Core` (auth providers, Fortify wiring, `User`, settings seeder, migrations, docs).

**Related:** `2026-10-07-api-access-control-design.md` (the `api` guard and tokens are a separate surface; nothing here changes them).

---

## 1. Purpose

Login methods are independent switches under the `auth` settings group. Each one says how a person proves who
they are. The second factor (TOTP) is not a global rule: it is asked only for the methods that do not already
carry one.

## 2. Current state (verified 2026-10-08)

| Finding | Evidence |
|---|---|
| Five runtime switches exist, all default `false`: `auth.registration.enabled`, `auth.email_verification.enabled`, `auth.two_factor.enabled`, `auth.social_login.enabled`, `auth.licenses.enabled`. | `CoreDatabaseSeeder::runtimeSettingDefinitions()`. |
| Social login creates users even when registration is off. | `SocialiteProvider::authenticate()` and `HandleSocialLoginAction::__invoke()` both `updateOrCreate` on `social_id` without reading `auth.registration.enabled`. |
| Two social paths exist. | The Fortify login pipeline (`SocialiteProvider`, via `AuthenticationService`) and the Socialite redirect/callback routes (`HandleSocialLoginAction`, which calls `Auth::login()` directly). |
| The TOTP challenge is decided before the provider is known. | Fortify's `RedirectIfTwoFactorAuthenticatable` runs the `authenticateUsing` callback and challenges any user with a confirmed secret, whatever the provider was. A social login through the Fortify pipeline would therefore be challenged. |
| Fortify 1.41.0 already supports passkeys through `laravel/passkeys` 0.2.1 (installed, unused). | `Features::passkeys()`, `PasskeyUser`, `PasskeyAuthenticatable`, `Passkeys::authorizeLoginUsing()`. The package logs in with `$guard->login()`, so Fortify's 2FA redirect is never in its path. |
| The account checks live in the providers, not in the guard. | `ValidatesUserAccount` (active account, license), `FortifyCredentialsProvider` (roles, verified email), `FortifyServiceProvider::ensureModuleScope()`. A passkey login would skip all of them. |

## 3. Decisions

| # | Decision |
|---|---|
| L1 | Methods stay separate switches. A new `auth.passkeys.enabled` (boolean, default `false`) is added. Password login is always available; passkeys are an addition, never a replacement. |
| L2 | A social login creates a user only when `auth.registration.enabled` is on. With registration off, an unknown `social_id` is refused. Both social paths obey this. An existing `social_id` always logs in. |
| L3 | A social login never attaches to an existing account by email. A known email with another account type stays refused (current behaviour of `SocialiteProvider`). |
| L4 | Each login method declares whether it already satisfies a second factor (`satisfiesSecondFactor()` on `IAuthenticationProvider`). Password: no. Social: yes (the provider owns it). Passkey: yes (device possession plus biometric or PIN). The TOTP challenge is asked only when the method used does not satisfy it. |
| L5 | `auth.two_factor.enabled` keeps its meaning: it lets users enrol a TOTP and applies to password logins. It does not add a challenge on top of social or passkey logins. |
| L6 | Passkey login runs the same account checks as password login before the session starts: active account, at least one role, verified email when required, license, module `scope`. One place applies them. |
| L7 | Passkeys are registered by an authenticated user, so a passkey never creates a user and the registration switch does not apply to it. Registration and removal require password confirmation (Fortify default). |
| L8 | No role is forced to use 2FA or a passkey for now. A later setting may require it per role. |
| L9 | The `passkeys` table follows the Core naming rule: `core_passkeys`, through a Core `Passkey` model that extends the package model. |

## 4. Interaction matrix

| Method | Needs switch | Creates users | Challenged by TOTP |
|---|---|---|---|
| Password | none (always) | only via registration (`auth.registration.enabled`) | yes, if the user confirmed a TOTP and `auth.two_factor.enabled` |
| Social | `auth.social_login.enabled` | only if `auth.registration.enabled` | no |
| Passkey | `auth.passkeys.enabled` | never | no |

Switching a method off blocks only that method. Switching every optional method off leaves password login.

## 5. Design

### 5.1 Registration guard for social

`SocialiteProvider::authenticate()` and `HandleSocialLoginAction` first refuse an email that belongs to an
account with another (or no) `social_id` (L3), then check, before creating, whether a user with that `social_id` exists. If not and `auth.registration.enabled` is off, the login fails with a stable message
(`Registration is disabled`). The redirect/callback path answers with a redirect to the admin entry point
carrying the error; the Fortify path returns `success => false`.

### 5.2 Second factor per method

- `IAuthenticationProvider::satisfiesSecondFactor(): bool`.
- `AuthenticationService::satisfiesSecondFactor(Request): bool` returns the flag of the enabled provider that
  handles the request, `false` when none does.
- A Core action `RedirectIfSecondFactorRequired` extends Fortify's `RedirectIfTwoFactorAuthenticatable`: when
  the request's provider satisfies a second factor it passes the request on, otherwise it defers to the parent.
  Registered with `Fortify::redirectUserForTwoFactorAuthenticationUsing()`.
- `HandleSocialLoginAction` already calls `Auth::login()` and so already skips the challenge; this spec makes
  that the rule instead of an accident.

### 5.3 Passkeys

- `User` implements `Laravel\Fortify\Contracts\PasskeyUser` and uses `PasskeyAuthenticatable`.
- `Features::passkeys()` is added to the Fortify feature list when `auth.passkeys.enabled` is on (both places
  that build the list: `config/fortify.php` and `CoreServiceProvider::configureFortifyFeatures()`).
- `Passkeys::authorizeLoginUsing()` applies L6. A shared `ValidatesUserAccount` check covers the account and
  license rules, roles and verified email are checked alongside, and the license is stored in the session the
  way the password flow does it.
- Migration `create_passkeys_table` (folded into the create migrations, pre-stable) creates `core_passkeys`
  with a foreign key to `users`. `CoreTables::Passkeys = 'core_passkeys'`.
- Relying party id and allowed origins come from Fortify's defaults (the host of `APP_URL`). Changing the
  domain invalidates registered passkeys; documented in the Core README.
- `laravel/passkeys` is pre-1.0 (0.2.1). It is already a dependency of Fortify, so nothing is added to
  `composer.json`.

## 6. Out of scope

- A user interface for listing, naming and revoking passkeys (the routes exist; the UI belongs to
  `laraplate-ui`).
- Linking a social identity to an existing account by an authenticated user.
- Enforcing 2FA or a passkey per role (L8).

## 7. Documentation

Behaviour is documented in `Modules/Core/docs/rag/MODULE.md` and the Core README (settings table, the
interaction matrix, passkey domain note). No new environment variables.
