# Testing

## Automated tests (PHPUnit 8.5/9)

| Suite | What it covers | Needs DB |
|---|---|---|
| `unit` | `MatchingEngine` (31 tests): every eligibility rule, scoring maths, rounding, weights validation, tie-breaks, determinism, the 9-provider reference dataset | no |
| `integration` | End-to-end API through `App::handle` against **real MySQL** (41 tests: registration/auth/tokens, request → match → invite → accept → submit → revise → complete, cancellation, expiry, disputes, payments, messaging, notifications, authorization/IDOR, validation, SQL-injection payloads, rate limiting, upload security) **and** the admin dashboard through `AdminApp::handle` (18 tests: auth, CSRF, session timeout, escaping, suspend/reactivate, dispute resolution, weights versioning, announcements, catalog, CSV safety, all pages render) | yes |

### Commands
```bash
cd backend
# unit tests only
phpunit -c phpunit.xml --testsuite unit
# integration tests: use a THROWAWAY database (all tables are truncated between tests!)
php bin/migrate.php --fresh        # with DB_* pointing at the throwaway DB
TB_TEST_DB_HOST=127.0.0.1 TB_TEST_DB_PORT=3306 TB_TEST_DB_NAME=taskbuddy_test \
TB_TEST_DB_USER=… TB_TEST_DB_PASS=… phpunit -c phpunit.xml
```
Without `TB_TEST_DB_NAME` the integration tests are skipped, never run against a real database by accident.
The test base class fails fast if the database name does not contain "test".

### Actual results (this development environment, PHP 8.3.6, MySQL 8.0.46, PHPUnit 8.5.55)
* Unit: **OK (31 tests, 76 assertions)**
* Integration: **OK (59 tests, 1238 assertions)**
* Full run: **OK (90 tests, 1312 assertions)**. One `[ERROR] Unhandled exception` line in the output is expected: it is the log of
  the deliberate transaction-rollback test.
* Manual smoke tests over real HTTP (`php -S`): API multipart upload accepted for a valid file and a `.php` upload rejected
  with 415; admin login with cookie (`HttpOnly; SameSite=Strict`), session, all main pages 200, security headers present.
  A defect found this way (login form lacked the CSRF field) was fixed and covered by a regression assertion.

## Not verified here
* **Flutter app**: `flutter analyze` / `flutter test` / device runs were **not possible** (no SDK). Test files exist in `mobile/test`
  but have never been executed.
* Apache/HestiaCP deployment, HTTPS behaviour, load/performance testing, cross-browser admin testing, accessibility audits.
* Static analysis (PHPStan/Psalm) was not run.

## Manual test checklist (client and provider app, after building the app)
Register both roles → provider completes profile/skills/availability → client creates a request with attachments → view ranked
matches and explanation → invite a provider → provider accepts → chat → provider submits files → client requests revision →
resubmits → client approves → payment shows `SIMULATED / TEST TRANSACTION` → notifications → dispute path → admin resolves.
