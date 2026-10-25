# StudentHub Test, Security, and Accessibility Report

**Report status:** implementation checklist prepared; live PHP/MySQL and browser verification still needs to be run in the target environment. No screenshots or runtime test results are represented as completed by this report.

## Test report

| Area | Expected check | Current verification |
| --- | --- | --- |
| PHP/DB setup | Import `studenthub.sql`; load pages with PHP/MySQL running | Pending local runtime |
| Login | Valid Student and Admin credentials succeed; invalid credentials fail | Pending manual test |
| Role-based access | Student cannot access Admin-only page (HTTP 403); unauthenticated user is redirected to login | Role guard implemented; pending browser test |
| Dashboard | Admin sees live account/student/event counts and latest registrations | Queries/UI implemented; pending database test |
| Student management | Admin add, edit, search/filter, soft-delete and CSV export | Existing feature; pending manual test |
| Event management | Admin create, edit, delete events and validate poster upload | Existing feature; pending manual test |
| Audit trail | Admin dashboard view and successful student/event create/update/delete actions add rows in `audit_logs` | Prepared audit inserts implemented; pending database proof |
| Practical 13 API | List/add/edit/delete, invalid data, duplicate email, and server-error states | Fetch/API code implemented; pending browser/network test |
| Session behavior | Session ID changes at login; inactivity timeout and logout clear session | Existing implementation; pending browser test |

Record actual results, dates, PHP/MySQL versions, and any deviations after running the checks. Do not mark pending tests as passed without executing them.

## Security checklist

- [x] Passwords are stored using password hashes and verified with `password_verify()`.
- [x] Login, registration, management, and API database operations use prepared statements.
- [x] Session IDs are regenerated after successful login; cookie flags and inactivity expiration are configured.
- [x] Admin pages enforce the Admin role on the server; hidden navigation is not relied upon as authorization.
- [x] State-changing forms/API calls use CSRF tokens.
- [x] HTML output from database/user data is escaped; API table values are inserted using `textContent`.
- [x] API input is validated, unsupported HTTP methods are rejected, and errors do not reveal SQL details to the browser.
- [x] Admin audit entries store actor, action, details, IP address, user agent, and timestamp; sensitive values such as passwords are not logged.
- [ ] Replace demo credentials and configure a least-privilege database account before deployment.
- [ ] Serve over HTTPS in a deployed environment; confirm secure session cookies under HTTPS.
- [ ] Verify PHP error display is disabled in production and server logs are access-controlled.
- [ ] Confirm uploaded-file and CSV storage directories are not publicly executable/readable.
- [ ] Inspect database permissions and backups; test restore from backup before production use.

## Accessibility checklist

- [x] Forms have explicit labels and native HTML validation attributes.
- [x] Status/errors use live status or alert semantics.
- [x] Data tables use captions and scoped column headers.
- [x] Interactive controls are native buttons/links and keyboard operable.
- [x] Dynamic student data is added with text nodes, not interpreted as HTML.
- [ ] Manually test keyboard-only navigation and visible focus on each PHP and API page.
- [ ] Test with a screen reader and verify dynamic status/error announcements.
- [ ] Check color contrast in light/dark themes and at 200% zoom.
- [ ] Run an automated WCAG scan against the running site and record tool/version and results.

## Evidence still to capture

Use [`screenshots/README.md`](./screenshots/README.md) for the requested UI, RBAC, audit, and Network-panel screenshot list. Save unaltered screenshots in `screenshots/` and update the report with the evidence filenames after capture.
