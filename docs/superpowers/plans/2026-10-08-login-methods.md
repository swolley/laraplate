# Login methods: registration guard, passkeys, per-method second factor. Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Social login obeys the registration switch, passkeys are an optional login method, and the TOTP challenge is asked only for methods that do not carry a second factor.

**Architecture:** `IAuthenticationProvider` gains `satisfiesSecondFactor()`; a Core subclass of Fortify's two-factor redirect consults it; passkeys use Fortify's `Features::passkeys()` with a Core `Passkey` model on `core_passkeys` and one shared account check.

**Tech Stack:** PHP 8.5, Laravel 12, Fortify 1.41, laravel/passkeys 0.2.1 (already installed), Pest 4. No new dependency.

**Spec:** `docs/superpowers/specs/2026-10-08-login-methods-design.md`.

## Global Constraints

- Every PHP file `declare(strict_types=1)`; braces always; explicit types; `#[Override]`; PHPDoc over inline comments; English code and docs.
- Tests live in `Modules/Core/tests`; support classes in `tests/Stubs` or `tests/Support`, never declared in a test file. Never run tests with a cached config.
- Pint from the laraplate root with explicit files: `vendor/bin/pint --format agent <files>`.
- Schema changes fold into create migrations (pre-stable). Five-driver portability.
- No commit without the owner's go-ahead; when committing, explicit paths, never `git add -A`.

## Task 1: Social login obeys the registration switch

**Files:** `Modules/Core/app/Auth/Providers/SocialiteProvider.php`, `Modules/Core/app/Actions/Users/HandleSocialLoginAction.php`, tests in `Modules/Core/tests/Integration/Auth/Providers/AuthenticationProvidersTest.php`.

- [x] Step 1: Failing tests: unknown `social_id` with registration off is refused on both paths; with registration on it is created; a known `social_id` logs in with registration off.
- [x] Step 2: Guard in `SocialiteProvider::authenticate()` (`Registration is disabled`).
- [x] Step 3: Same guard in `HandleSocialLoginAction`, answering with a redirect carrying the error.
- [x] Step 4: Tests pass.

## Task 2: Second factor per method

**Files:** `IAuthenticationProvider`, both providers, `AuthenticationService`, new `Modules/Core/app/Auth/RedirectIfSecondFactorRequired.php`, `FortifyServiceProvider`.

- [x] Step 1: Failing tests: provider flags (credentials false, social true), service lookup, and the redirect action passing a satisfied request on and deferring otherwise.
- [x] Step 2: Interface method, provider implementations, `AuthenticationService::satisfiesSecondFactor()`.
- [x] Step 3: `RedirectIfSecondFactorRequired`, registered through `Fortify::redirectUserForTwoFactorAuthenticationUsing()`.
- [x] Step 4: Tests pass.

## Task 3: Passkeys as an optional login method

**Files:** `CoreDatabaseSeeder`, `CoreTables`, `Modules/Core/app/Models/Passkey.php`, `Modules/Core/app/Models/User.php`, passkeys migration, `config/fortify.php`, `CoreServiceProvider`, `FortifyServiceProvider`, `ValidatesUserAccount`.

- [x] Step 1: Failing tests: seeded setting `auth.passkeys.enabled` default false; feature list contains passkeys only when on; the login authorization refuses an inactive user, a user without roles, an unverified email when required, and a license failure; and accepts a valid user.
- [x] Step 2: Setting definition, `CoreTables::Passkeys`, `Passkey` model, migration.
- [x] Step 3: `User` implements `PasskeyUser`; feature wiring in both places.
- [x] Step 4: `Passkeys::authorizeLoginUsing()` with the shared account checks.
- [x] Step 5: Tests pass.

## Task 4: Documentation and close

- [x] Step 1: Core README settings table and interaction matrix; `docs/rag/MODULE.md`.
- [x] Step 2: Pint on touched files; related Core auth tests green.
- [x] Step 3: Spec and plan indexes updated; `## Delivery status` and `**Documented in:**` added to this plan.

## Delivery status (2026-10-08): delivered

All four tasks built as planned. Divergences:

- The documentation lives in the Core README (the `auth.passkeys.enabled` toggle in the settings list) and in `docs/rag/MODULE.md` (the interaction matrix and the passkey notes); the README carries no separate matrix.
- `HandleSocialLoginAction` applies the registration guard and the email-conflict refusal (L3, same message as `SocialiteProvider`) only on its default path; a custom `userUpserter` owns user creation.
- Not built, as the spec says: a UI for listing and revoking passkeys, linking a social identity to an existing account, and a per-role 2FA or passkey requirement.

**Documented in:** `Modules/Core/docs/rag/MODULE.md`, `Modules/Core/README.md`.
