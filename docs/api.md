# TaskBuddy REST API

Base URL: `https://<host>/api` (the backend front controller is `backend/public/index.php`; every non-file URL is rewritten to it by `.htaccess`).
All bodies are JSON (`Content-Type: application/json`) except file uploads (`multipart/form-data`). All timestamps are UTC ISO-8601 (`2030-01-07T09:00:00Z`). Money values are strings with two decimals (`"500.00"`).

## Envelope

```json
{ "success": true,  "data": { }, "meta": { } }
{ "success": false, "error": { "code": "VALIDATION_FAILED", "message": "…", "details": { "fields": { "deadline": "Must be in the future." } } } }
```

`meta` is present for paginated lists: `{ "page": 1, "per_page": 20, "total": 42, "has_more": true }` (use `?page=` and `?per_page=` (max 50)).

## Authentication

Send the header `Authorization` with the scheme `Bearer`, a space, then the session token, on every authenticated request. Tokens are random 256-bit values (only their SHA-256 hash is stored), expire after `TOKEN_TTL_HOURS` (default 168 h), and are revoked on logout/refresh. Identity, role and ownership are always derived from the token on the server; client-supplied user ids, roles, scores and statuses are never trusted. Administrator accounts cannot use the mobile API (they use the PHP dashboard with PHP sessions + CSRF).

## HTTP status / error codes

| Status | `error.code` examples |
|---|---|
| 400 | `INVALID_JSON` |
| 401 | `UNAUTHENTICATED`, `INVALID_CREDENTIALS` |
| 403 | `FORBIDDEN`, `ACCOUNT_SUSPENDED` |
| 404 | `NOT_FOUND` (also returned for objects the caller may not see) |
| 405 | `METHOD_NOT_ALLOWED` |
| 409 | `EMAIL_TAKEN`, `INVALID_STATE`, `INVALID_TRANSITION`, `INVITATION_PENDING`, `INVITATION_NOT_PENDING`, `INVITATION_EXPIRED`, `PROVIDER_NOT_ELIGIBLE`, `PROVIDER_ALREADY_DECLINED`, `ASSIGNMENT_CONFLICT`, `ALREADY_ASSIGNED`, `CONVERSATION_CLOSED`, `TOO_MANY_FILES`, `NO_SUBMISSION` |
| 413 | `FILE_TOO_LARGE` |
| 415 | `UNSUPPORTED_FILE_TYPE` |
| 422 | `VALIDATION_FAILED`, `PROVIDER_NOT_RECOMMENDED` |
| 429 | `RATE_LIMITED` (`details.retry_after_seconds`) |
| 500 | `INTERNAL_ERROR` (generic message; details only in logs) |

## Objects

* **User**: `{id, email, full_name, role: "client"|"provider", status, phone, created_at}`
* **Session**: `{user, token, token_type: "Bearer", expires_at}`
* **Provider details** (inside `GET /profile` for providers): `{categories:[{id,name}], skills:[{id,name}], availability:[{day_of_week 0=Sun..6=Sat, start_time "HH:MM", end_time "HH:MM"}], profile_complete, missing_fields:[…], has_availability}`
* **Request**: `{id, client:{id,full_name}, category:{id,name}, title, description, requirements, deadline, compensation, estimated_hours, requires_exclusive, status, skills:[{id,name,is_mandatory}], created_at, updated_at, attachments:[{id,original_name,mime_type,size_bytes,created_at,download_url}], pending_invitation:{id,provider_id,status,expires_at}|null, assignment:{id,provider:{id,full_name},assigned_at}|null}`; for providers also `my_match:{final_score,budget_status,summary}|null`. List items omit `attachments`, `pending_invitation`, `assignment`.
* **Statuses**: `OPEN, PENDING_ACCEPTANCE, IN_PROGRESS, SUBMITTED, UNDER_REVIEW, REVISION_REQUESTED, COMPLETED, CANCELLED, EXPIRED, DISPUTED`
* **Match item**: `{rank, provider:{id,full_name,headline,min_rate,years_experience}, final_score, scores:{category,skills,availability,budget}, budget_status: "WITHIN_BUDGET"|"OVER_BUDGET", explanation:{algorithm_version, final_score, weights, category:{matched,score}, skills:{score, required_total, matched_count, matched_skills:[{id,name}], missing_nonmandatory_skills:[{id,name}]}, availability:{available,score,available_hours_before_deadline}, budget:{status,score,provider_min_rate,proposed_compensation}, summary}}`
* **Invitation**: `{id, status: PENDING|ACCEPTED|DECLINED|EXPIRED|WITHDRAWN, message, expires_at, responded_at, created_at, request:{id,title,compensation,deadline,category,status}, client:{id,full_name}, provider:{id,full_name}, assignment_id}`
* **Task** (a request with an assignment; **task id == request id**): summary `{id,title,status,category,deadline,compensation,client,provider,assignment_id,conversation_id,assigned_at,completed_at,updated_at}`; detail adds `description, requirements, skills, attachments, submissions:[{id,note,created_at,files:[{id,original_name,mime_type,size_bytes,download_url}]}], revisions:[{id,reason,submission_id,created_at}], dispute:{id,reason,status,resolution,resolution_note,created_at,resolved_at}|null, payment, allowed_actions:["submit"|"start_review"|"approve"|"revision"|"dispute"]`
* **Payment**: `{id, reference, label:"SIMULATED / TEST TRANSACTION", is_simulated:true, task_id, task_title, client, provider, amount, currency_note, state: PENDING|COMPLETED|ON_HOLD|CANCELLED, created_at, completed_at}`. **Every payment screen must show the label.**
* **Notification**: `{id, type, title, body, request_id, is_read, created_at}`; types: `INVITATION_RECEIVED, INVITATION_ACCEPTED, INVITATION_DECLINED, INVITATION_EXPIRED, TASK_ASSIGNED, NEW_MESSAGE, WORK_SUBMITTED, REVISION_REQUESTED, TASK_COMPLETED, DISPUTE_CREATED, DISPUTE_RESOLVED, PAYMENT_RECORDED, ANNOUNCEMENT`

## Endpoints

Roles: **P** public, **A** any authenticated user (client or provider), **C** client, **V** provider.

### Auth
| | Endpoint | Body → `data` |
|---|---|---|
| P | `POST /auth/register` | `{email, password (8-72, letter+digit), full_name, role: "client"\|"provider", phone?}` → 201 Session |
| P | `POST /auth/login` | `{email, password}` → Session |
| A | `POST /auth/logout` | → `{logged_out:true}` |
| A | `GET /auth/me` | → `{user}` |
| A | `POST /auth/refresh` | rotates the current token → Session |

Account recovery: no e-mail delivery service is configured, so there is **no** self-service password reset. Users must contact an administrator (documented in `docs/limitations.md`).

### Profile, catalog
| | Endpoint | Notes |
|---|---|---|
| A | `GET /profile` | `{user, profile:{bio,location,headline,experience_summary,years_experience,min_rate}, provider?}` |
| A | `PUT /profile` | any of `full_name, phone, bio, location`; providers also `headline, experience_summary, years_experience, min_rate, category_ids[]` |
| A | `GET /providers/{id}` | public provider profile: `{id, full_name, headline, bio, location, experience_summary, years_experience, min_rate, categories, skills, availability, completed_tasks}` |
| V | `PUT /provider/skills` | `{skill_ids:[int]}` → provider details |
| V | `PUT /provider/categories` | `{category_ids:[int]}` → provider details |
| V | `PUT /provider/availability` | `{slots:[{day_of_week,start_time,end_time}]}` replaces all slots (times in the server's `APP_TIMEZONE`) → provider details |
| P | `GET /categories` | `[{id,name,description}]` |
| P | `GET /skills?category_id=` | `[{id,name}]` |
| P | `GET /categories/{id}/skills` | `[{id,name}]` |

A provider is **eligible for matching** only when `profile_complete` (headline, experience_summary, min_rate and ≥1 category) **and** has declared availability.

### Requests
| | Endpoint | Notes |
|---|---|---|
| C | `POST /requests` | `{title, description, category_id, skills:[{skill_id,is_mandatory}], requirements?, deadline, compensation, estimated_hours?, requires_exclusive?}` → 201 `{request, matching:{status,result_id,eligible_count,no_match}}`; matching runs immediately |
| A | `GET /requests?status=&page=` | client: own requests; provider: **opportunities** (OPEN requests in which the provider is a recommended candidate) |
| A | `GET /requests/{id}` | client owner, or provider who is recommended/invited/assigned (otherwise 404) |
| C | `PUT /requests/{id}` | only while OPEN; partial update; matching is re-run when requirements change → `{request, matching}` |
| C | `POST /requests/{id}/cancel` | OPEN or PENDING_ACCEPTANCE only |
| C | `GET /requests/{id}/matches?page=&per_page=` | ranked recommendations (default 10/page). `meta`: `{result_id, algorithm_version, weights, candidates_evaluated, eligible_count, exclusion_summary, generated_at, total, has_more, no_match, message}`. When nobody qualifies: `data: []`, `meta.no_match: true`, `meta.message` set |
| C | `POST /requests/{id}/matches` | explicit retry → `{result_id}` (OPEN requests only) |
| C | `POST /requests/{id}/attachments` | multipart field `file` (one file per call, max 10 per request) → 201 `{id, original_name, mime_type, size_bytes}` |
| A | `GET /attachments/{id}/download` | authorised participants only; `Content-Disposition: attachment` |

### Invitations
| | Endpoint | Notes |
|---|---|---|
| C | `POST /requests/{id}/invite` | `{provider_id, message?, expires_in_hours?}` → 201 Invitation; request becomes PENDING_ACCEPTANCE. Only providers in the latest match result and still eligible |
| A | `GET /invitations?status=&request_id=` | provider: received; client: sent |
| V | `POST /invitations/{id}/accept` | atomically creates assignment, conversation, PENDING simulated payment; request → IN_PROGRESS |
| V | `POST /invitations/{id}/decline` | request → OPEN |

### Tasks
| | Endpoint | Notes |
|---|---|---|
| A | `GET /tasks?status=` | tasks where the caller is client or provider |
| A | `GET /tasks/{id}` | participants only |
| V | `POST /tasks/{id}/submit` | multipart: `note?`, `files[]` (1-10 files) → 201 `{submission_id, task}`; status → SUBMITTED |
| C | `POST /tasks/{id}/start-review` | SUBMITTED → UNDER_REVIEW (approve/revision do this implicitly) |
| C | `POST /tasks/{id}/approve` | → COMPLETED, simulated payment → COMPLETED |
| C | `POST /tasks/{id}/revision` | `{reason}` → creates revision record, task returns to IN_PROGRESS |
| A | `POST /tasks/{id}/dispute` | `{reason (≥10 chars)}` → DISPUTED, payment ON_HOLD; resolved by an administrator |
| A | `GET /submission-files/{id}/download` | participants only |

### Messaging (REST polling)
| | Endpoint | Notes |
|---|---|---|
| A | `GET /conversations` | `[{id, task_id, task_title, task_status, other_party, last_message, unread_count}]` |
| A | `GET /conversations/{id}/messages?limit=&before_id=&after_id=` | oldest-first. No cursor: latest `limit` (default 30). `before_id`: older page. `after_id`: newer messages (polling). `meta:{limit, has_more_older, latest_id}` |
| A | `POST /conversations/{id}/messages` | `{body (1-2000)}` → 201 message; 409 `CONVERSATION_CLOSED` once the task is COMPLETED/CANCELLED |
| A | `POST /conversations/{id}/read` | marks the other party's messages read |

### Payments (simulated) and notifications
| | Endpoint | Notes |
|---|---|---|
| A | `GET /payments?state=` | `meta.summary:{label, is_simulated, pending_total, completed_total, on_hold_total, cancelled_total}` |
| A | `GET /payments/{id}` | |
| A | `GET /notifications?unread=1` | `meta.unread_count` |
| A | `POST /notifications/{id}/read`, `POST /notifications/read-all` | |

### Moderation
`POST /providers/{id}/block` and `DELETE /providers/{id}/block` (client): blocked providers are never matched to that client. `POST /reports` `{user_id, reason}` creates a report reviewed by administrators.

### Health
`GET /health` → `{status:"ok"}`.

## Deliberate deviations from the logical contract
* Added: `POST /requests/{id}/matches` (retry), `POST /requests/{id}/attachments`, `GET /attachments/{id}/download`, `GET /submission-files/{id}/download`, `POST /tasks/{id}/start-review`, `PUT /provider/categories`, `POST /notifications/read-all`, block/report endpoints, `GET /health`.
* Task ids equal request ids.
* Administrative APIs are intentionally **not** exposed over bearer tokens; the PHP dashboard (`admin/`) calls the same service classes directly.
