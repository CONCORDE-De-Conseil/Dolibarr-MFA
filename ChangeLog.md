# CHANGELOG MFA FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

## 1.1 (May 11, 2026)

### Improvements

- Refactored QR code generation to use server-side TCPDF barcode rendering directly, removing dependency on barcode module for QR display
- QR codes now generate successfully even when barcode module is not enabled
- Enhanced MFA setup UI with improved layout and inline base64 encoded QR images for better presentation
- Added secret key validation with auto-recovery for corrupted Base32 secrets
- Improved MFA session logout behavior to clear all session markers on user cancellation
- Added translations for QR setup flow: `ScanThisWithYourApp`, `MFASecretKey`, `UseThisForManualEntry`, `EnterSixDigitCodeFromApp`
- Fixed MFA abort logic to perform complete session logout when user clicks "No" on MFA challenge

### Bug Fixes

- Fixed MFA challenge abort not properly logging out user (was leaving password verification flag set)
- Fixed QR code WAF (Web Application Firewall) injection protection errors by computing provisioning URI server-side
- Fixed MFA secret display in setup to always show valid Base32 keys

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
