# Architecture

```
 Flutter app (mobile/)  ──HTTPS/JSON, bearer token ──▶  PHP REST API (backend/public)  ──PDO──▶  MySQL 8
                                                           ▲   shared Services/Support code
 Admin browser  ──HTTPS, session cookie + CSRF──▶  Admin dashboard (admin/public) ──────┘
```

| Part | Tech | Notes |
|---|---|---|
| Mobile | Flutter / Dart, Provider, Material 3 | Thin client; all rules are enforced by the server. Token in secure storage. |
| API | Native PHP 8.x, no framework | `Support/Router`, `Middleware`, `Controllers` (thin) → `Services` (business rules) → PDO prepared statements. |
| Matching | `Services/MatchingEngine` | Pure deterministic function, see `matching-algorithm.md`. |
| Admin | Native PHP, server-rendered, no JavaScript | Reuses the backend autoloader, `.env` and services so rules are never duplicated. Separate document root. |
| DB | MySQL 8 (InnoDB, utf8mb4) | Versioned migrations in `backend/database/migrations`; run `php bin/migrate.php`. |

## Layers (backend)
`public/index.php` → `App::handle` → `Router` → middleware (authentication, role, rate limit, security headers) →
`Controller` (input validation via `Validator`) → `Service` (transactions, state machine, audit) → `Database`.
Errors are `ApiException`s mapped to a uniform JSON envelope `{success, data|error:{code,message,fields}}`; unexpected
exceptions are logged and return a generic 500 (no stack traces).

## Task state machine
`OPEN → PENDING_ACCEPTANCE → IN_PROGRESS → SUBMITTED → UNDER_REVIEW → COMPLETED`, with `REVISION_REQUESTED`
(back to `IN_PROGRESS` on resubmission), `CANCELLED`, `EXPIRED` and `DISPUTED`. Invalid transitions return 409
`INVALID_TRANSITION`. Every transition is audited in `activity_logs` and notifies the counterpart.

## Security design (summary)
* Passwords: `password_hash` (bcrypt/argon default); bearer tokens are random, **only their SHA-256 is stored**, with expiry and revocation.
* Authorization is checked server-side on every object (clients only see their requests, providers only their invitations/tasks); admin accounts cannot use the mobile API.
* All SQL uses prepared statements; all admin output is HTML-escaped; CSV exports neutralise formula injection.
* Admin: CSRF token on every POST, idle + absolute session timeout, `HttpOnly`/`SameSite=Strict` cookie, strict CSP, role re-verified from the DB on every request, brute-force rate limit with generic login errors, audit log.
* Uploads: extension + MIME + magic-byte allow-list, size limits, random server-side names, stored **outside the web root**, downloaded only through authorised endpoints; admin sees metadata only.
* Payments are **simulated** (state records only, always labelled `SIMULATED / TEST TRANSACTION`). No card data exists anywhere.
