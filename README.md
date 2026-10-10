# TaskBuddy

A service-request matching system: clients post service requests, a **deterministic rule-based weighted matching engine**
ranks eligible providers with a human-readable explanation, and both parties complete the task workflow (invitation, chat,
submission, revision, approval) with **simulated** payments. Built for a capstone study evaluated against ISO/IEC 25010.

| Component | Path | Tech |
|---|---|---|
| Mobile app (clients + providers) | `mobile/` | Flutter / Dart — **source only, not compiled here** (see `docs/limitations.md`) |
| REST API | `backend/` | Native PHP 8.x, PDO, MySQL 8 |
| Admin dashboard | `admin/` | Native PHP, server-rendered |
| Documentation | `docs/` | architecture, database, api, matching-algorithm, deployment, testing, iso25010-evaluation, limitations |

## Prerequisites
PHP ≥ 8.1 (`pdo_mysql`, `mbstring`, `fileinfo`, `json`, `openssl`), MySQL 8, Composer (for the autoloader) or any PSR-4 autoload
generation, PHPUnit 8.5+ for tests, Flutter 3.44.9 stable (Dart 3.12.2) for the mobile app — the reference toolchain; the project's `pubspec.yaml` allows Dart `>=3.3.0 <4.0.0`.

## Backend
```bash
cd backend
composer install
cp .env.example .env        # edit DB_* values; never commit .env
php bin/migrate.php          # schema + reference data (categories, skills, default weights)
ADMIN_EMAIL=you@example.com php bin/create_admin.php      # prompts for a 12+ char password
php -S 127.0.0.1:8080 -t public public/index.php   # development server (API at http://127.0.0.1:8080/api/health)
php database/seeds/dev_seed.php        # OPTIONAL demo data (development only, random passwords printed once)
```
## Admin dashboard
```bash
cd admin/public && php -S 127.0.0.1:8081 index.php    # development; shares backend/.env
```
Production: serve `admin/public` as a separate host (see `docs/deployment.md`).

## Mobile app
```bash
cd mobile
flutter create .        # generates android/ ios/ (not included)
flutter pub get
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8080/api   # Android emulator → host machine
```
## Tests
```bash
cd backend
phpunit -c phpunit.xml --testsuite unit                       # no database needed
# integration (needs a throwaway MySQL DB whose name contains "test", already migrated):
TB_TEST_DB_NAME=taskbuddy_test TB_TEST_DB_USER=… TB_TEST_DB_PASS=… phpunit -c phpunit.xml
```
See `docs/testing.md` for results and what was **not** verified. Payments are simulated and always labelled
`SIMULATED / TEST TRANSACTION`.
