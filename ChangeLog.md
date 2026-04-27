# CHANGELOG MFA FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

## 1.0 (April 27, 2026)

### New Features

- Added TOTP-based MFA enrollment and verification for Dolibarr users
- Added login challenge flow with MFA code prompt after password validation
- Added user-card MFA management with QR code provisioning and enable/disable actions
- Added admin-only MFA attempt history page with reset actions for locked users

### Security

- Added CSRF validation for MFA management actions
- Added persistent tracking for failed MFA login and setup attempts
- Added lockout handling with admin reset capability
- Hardened Base32 secret decoding and TOTP verification input validation

### Admin

- Added MFA attempt state and history tables for operational support
- Added module admin tab for reviewing and clearing MFA lock state

### Documentation

- Added and refreshed copyright headers across MFA service files
- Replaced module template placeholders with MFA-specific metadata and labels
