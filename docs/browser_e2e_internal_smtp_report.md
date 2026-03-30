# Internal SMTP Browser E2E Report

- Date: 2026-03-30
- HEAD: `b7ab3117f7093840ba0049d315480f2c2167162f`
- Command: `npx playwright test tests/e2e/internal-smtp-settings.phaseP2.spec.js --workers=1`
- Result: passed

## Verified

- Internal super admin can open `/internal/smtp-settings`
- SMTP settings can be saved against the local SMTP sink
- Saved username and password remain masked in the UI after save
- No plaintext SMTP secret is rendered back into the page during the flow
- Test connection succeeds with a success toast
- Test email succeeds with a success toast
- The local SMTP sink receives the test message for `probe@example.test`
- External organization users receive `403` on `/internal/smtp-settings`
- No exception text or stack trace is rendered on the denied page

## Screenshots

- [01 Internal SMTP Settings](c:/Users/Ahmed/Desktop/cebx-code/docs/browser_e2e_internal_smtp_screenshots/20260330/01-internal-smtp-settings.png)
- [02 Settings Saved And Masked](c:/Users/Ahmed/Desktop/cebx-code/docs/browser_e2e_internal_smtp_screenshots/20260330/02-settings-saved-and-masked.png)
- [03 Test Email Success](c:/Users/Ahmed/Desktop/cebx-code/docs/browser_e2e_internal_smtp_screenshots/20260330/03-test-email-success.png)
- [04 External Access Denied](c:/Users/Ahmed/Desktop/cebx-code/docs/browser_e2e_internal_smtp_screenshots/20260330/04-external-access-denied.png)
