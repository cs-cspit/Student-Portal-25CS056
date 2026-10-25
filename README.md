# StudentHub Portal

StudentHub is a PHP/MySQL university portal with role-based login, student and event management, and a Fetch-based student records API.

**Integrated source repository:** [cs-cspit/Student-Portal-25CS056](https://github.com/cs-cspit/Student-Portal-25CS056)

## Requirements

- PHP 8.1+ with `mysqli`, `pdo_mysql` (used by existing Practical 8 pages), `fileinfo`, and session support
- MySQL 8.0+ or a compatible MariaDB release
- Apache or another PHP-enabled web server
- Browser with JavaScript enabled

## Setup

1. Copy or clone the repository into the web server's document root (for example, `htdocs/studenthub`).
2. Start Apache and MySQL.
3. Import the integrated database dump into MySQL:

   ```sh
   mysql -u root -p < studenthub.sql
   ```

   Or select `studenthub.sql` in phpMyAdmin and import it. The dump creates `studenthub_db`, its tables, sample records, and demo login accounts. It uses `CREATE TABLE IF NOT EXISTS` and does not drop existing data. For a fresh practical database, use the included `studenthub_db`.

4. Configure database environment variables for the PHP web-server process:

   | Variable | Default | Purpose |
   | --- | --- | --- |
   | `DB_HOST` | `127.0.0.1` | MySQL host |
   | `DB_PORT` | `3306` | MySQL TCP port |
   | `DB_NAME` | `studenthub_db` | Database name |
   | `DB_USER` | `root` | Database user |
   | `DB_PASS` | empty | Database password |
   | `AUTH_SESSION_TIMEOUT` | `900` | Inactivity timeout, seconds (allowed 60–86400) |

   Do not use the development `root` account or an empty password on a network-accessible deployment. Grant the application only the database permissions it needs.

5. Make `uploads/event-posters/` writable by PHP for Practical 12 poster uploads. Keep `storage/` outside the public web root where possible; the included `.htaccess` protects it when using Apache.
6. Open `http://localhost/studenthub/index.html` (adjust the URL to match your document-root folder).

### Demo accounts

The SQL dump contains example hashed credentials for local coursework testing:

- Admin: `admin.demo@example.test` / `Admin@123`
- Student: `student.demo@example.test` / `Student@123`

These are public demo credentials, not production secrets. Replace or remove them before deployment.

## Main pages

- `login.php` / `authenticate.php` — email/password login and role session
- `dashboard.php` — role-aware dashboard; administrators see live counts, recent registrations, and audit entries
- `admin_dashboard.php` and `student_dashboard.php` — role-protected dashboards
- `student_management.php` and `event_management.php` — Admin-only management workflows
- `index.html` + `script.js` + `api.php` — Practical 13 JSON API CRUD interface
- `studenthub.sql` — integrated database setup and sample records

## Testing checklist

1. Open the login page and sign in as the demo Student; verify the student menu and dashboard.
2. Attempt to open `admin_dashboard.php` as Student; verify HTTP 403 Unauthorized Access.
3. Sign in as the demo Admin; verify the dynamic user/student/event counts, latest registration table, and admin menu.
4. From the Admin session, open `student_management.php` and `event_management.php`; create, edit, and delete/deactivate a sample record. Check the corresponding `audit_logs` entries.
5. Sign out, then verify protected pages redirect to login. For a timeout test, configure a short development timeout and wait longer than that interval.
6. Open the home page and test Practical 13 list, add, edit, delete, invalid input, duplicate email, and loading/error states. Inspect DevTools → Network → `api.php`; successful requests return HTTP 200 and JSON (creation intentionally returns 200 for the coursework status-code requirement).
7. Capture actual browser screenshots using the checklist in [`screenshots/README.md`](./screenshots/README.md).

For the full verification and security/accessibility checklist, see [`TEST_SECURITY_ACCESSIBILITY.md`](./TEST_SECURITY_ACCESSIBILITY.md).
