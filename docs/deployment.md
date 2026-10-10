# Deployment (Apache / HestiaCP, PHP 8.x, MySQL 8)

1. **Database** – create a database and a dedicated user with privileges on that database only.
2. **Code** – upload the repository; run `composer install --no-dev --optimize-autoloader` in `backend/`.
3. **Configuration** – copy `backend/.env.example` to `backend/.env` (mode 600, never commit). Set `APP_ENV=production`,
   `APP_DEBUG=false`, DB credentials and `STORAGE_PATH`.
4. **Migrate** – `php backend/bin/migrate.php`.
5. **Administrator** – `ADMIN_EMAIL=… ADMIN_NAME=… php backend/bin/create_admin.php` (prompts for a 12+ character password).
6. **Web roots (important)**
   * API: document root = `backend/public` (e.g. `https://api.example.com`).
   * Admin: document root = `admin/public` on a **separate host/sub-domain** (e.g. `https://admin.example.com`) or an IP-restricted path.
   * Never expose `backend/app`, `backend/storage`, `.env` or `vendor`. Uploads live in `STORAGE_PATH`, outside any web root.
7. **TLS** – force HTTPS (HestiaCP: Let's Encrypt + "Force SSL"). The admin cookie is `Secure` automatically over HTTPS.
8. **Apache** – `mod_rewrite` and `AllowOverride All` for the two public directories (the `.htaccess` files do front-controller routing).
9. **Cron** – expire stale invitations/requests every few minutes: `*/5 * * * * php /path/backend/bin/maintenance.php`.
10. **PHP** – extensions `pdo_mysql`, `mbstring`, `fileinfo`, `json`, `openssl`; `display_errors=Off`, `expose_php=Off`, `upload_max_filesize`/`post_max_size` ≥ `UPLOAD_MAX_BYTES`, `file_uploads=On`.
11. **Mobile** – build with `--dart-define=API_BASE_URL=https://api.example.com/api`.
12. **Backups** – nightly `mysqldump` plus a copy of `STORAGE_PATH`.

Development only: `php backend/database/seeds/dev_seed.php` creates demo accounts with random passwords (refuses in production).
