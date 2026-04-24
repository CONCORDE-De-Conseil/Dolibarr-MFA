Status: User approved implementation kickoff on 2026-04-24. Ready for handoff execution.

## Plan: Dolibarr User TOTP MFA Login

Implement MFA as a module-only extension (no core patch) by combining a custom authentication mode from the MFA module with login-page and user-card hooks. This lets password validation happen first, then TOTP verification only for users with MFA enabled, while keeping rollout optional per user and scoped to main Dolibarr UI login.

**Steps**

1. Phase 1 - Module wiring and architecture baseline: update module declaration to register login backend and hook contexts for login page and user card (_blocks all later steps_).
2. In [htdocs/custom/mfa/core/modules/modMFA.class.php](htdocs/custom/mfa/core/modules/modMFA.class.php), set module parts for login and hooks contexts (`mainloginpage`, `login`, `usercard`, `globalcard`), and keep activation flow loading SQL from module path.
3. Phase 2 - Secure MFA data model: add dedicated table for user MFA binding (_depends on 1_).
4. In [htdocs/custom/mfa/sql](htdocs/custom/mfa/sql), add schema for per-user MFA record (user id, entity, enabled flag, encrypted secret, timestamps, audit fields), plus uniqueness/index constraints by user/entity.
5. Phase 3 - User management UI/hooks: add hook class under [htdocs/custom/mfa](htdocs/custom/mfa) to expose MFA status and setup controls on user card (_depends on 1, 3_).
6. Implement user-card hook actions to: generate secret, display provisioning string, validate a first TOTP code before enabling, disable/reset MFA, and persist encrypted secret with `dolEncrypt`/`dolDecrypt` conventions.
7. Phase 4 - Login UX integration: inject OTP input on login form (_depends on 1_).
8. Implement `mainloginpage` hook methods to render an MFA code field and preserve login form state/messages on failed MFA checks.
9. Phase 5 - Authentication enforcement path: add custom auth checker under [htdocs/custom/mfa](htdocs/custom/mfa) using Dolibarr auth mode contract (_depends on 3, 4_).
10. In module-provided auth function (`functions_mfa.php`), call standard Dolibarr password validation first, then if user MFA-enabled verify TOTP for current time window; reject with localized login message when code is missing/invalid.
11. Add minimal anti-bruteforce handling for OTP failures (short delay + event/log consistency), without changing existing global failed-login semantics.
12. Phase 6 - Admin setup and rollout controls: add admin guidance in [htdocs/custom/mfa/admin](htdocs/custom/mfa/admin) and [htdocs/custom/mfa/README.md](htdocs/custom/mfa/README.md) (_parallel with 3-5 once data model is set_).
13. Document required config: set authentication mode to include MFA backend (recommended `mfa` first), rollout strategy, and rollback instructions.
14. Phase 7 - Internationalization and hardening: add language keys in [htdocs/custom/mfa/langs](htdocs/custom/mfa/langs) for setup/errors/prompts, ensure permissions/checks restrict who can modify other users’ MFA settings (_depends on 3-6_).

**Relevant files**

- /home/ali/dolibarr/dolibarr-fork/htdocs/custom/mfa/core/modules/modMFA.class.php — enable login backend and hook contexts; module activation bootstrap.
- /home/ali/dolibarr/dolibarr-fork/htdocs/custom/mfa/sql — create/update MFA table schema and upgrade scripts.
- /home/ali/dolibarr/dolibarr-fork/htdocs/custom/mfa/admin/setup.php — admin instructions and configuration checks.
- /home/ali/dolibarr/dolibarr-fork/htdocs/custom/mfa/README.md — module usage and authentication mode rollout docs.
- /home/ali/dolibarr/dolibarr-fork/htdocs/core/lib/security2.lib.php — reference auth mode dispatch contract (`checkLoginPassEntity`) to match function naming/signature.
- /home/ali/dolibarr/dolibarr-fork/htdocs/core/login/functions_dolibarr.php — reference for failed-login messaging, event behavior, and password checks.
- /home/ali/dolibarr/dolibarr-fork/htdocs/user/card.php — reference hook entry points and action flow for user-card integration.

**Verification**

1. Enable module in a test entity and verify SQL table creation succeeds without errors.
2. Configure auth mode to route through MFA backend and verify non-MFA users can still log in with password only.
3. For an MFA-enabled test user: validate setup flow requires correct first code before enable; then verify login fails without OTP, fails with wrong OTP, succeeds with valid OTP.
4. Confirm login page renders MFA code field only in intended context and preserves standard error display behavior.
5. Confirm disabling/resetting MFA on user card immediately reverts login behavior to password-only for that user.
6. Validate logs/events are written for MFA failures and no PHP warnings/errors appear in debug logs.
7. Run existing project checks relevant to changed PHP files (at minimum syntax/lint on touched files) and perform one manual end-to-end login regression.

**Decisions**

- Included scope: main Dolibarr UI login only, optional per user, dedicated MFA table storage, TOTP-only first version.
- Excluded scope: API/webservices login MFA, webportal MFA, recovery codes, SMS/email OTP, core-file patching.
- Security decision: do not store raw TOTP secret in user extrafields; store encrypted secret in module table and keep only enable/state metadata user-visible.

**Further Considerations**

1. Auth mode ordering recommendation: `mfa,dolibarr` during rollout versus `mfa` only after validation. Recommended: start with `mfa,dolibarr` in staging, move to `mfa` in production once verified.
2. Initial secret provisioning UX: plain secret string only versus QR URI display in v1. Recommended: include provisioning URI text now, optional QR rendering next iteration.
3. OTP drift tolerance window: ±1 step versus ±2 steps. Recommended: ±1 for stronger security unless many users have clock drift.
