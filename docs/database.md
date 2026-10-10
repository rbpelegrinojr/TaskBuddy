# Database

Source of truth: `backend/database/migrations/001_schema.sql` (schema) and `002_reference_data.sql` (default matching
configuration, categories and skills). Apply with `php bin/migrate.php` (`--fresh` drops and recreates, development only).
Applied migrations are tracked in `schema_migrations`.

| Group | Tables |
|---|---|
| Accounts | `users`, `user_profiles` (also provider headline/experience/minimum rate), `mobile_auth_tokens`, `user_blocks`, `user_reports` |
| Catalog | `service_categories`, `skills`, `category_skills`, `provider_categories`, `provider_skills`, `provider_availability` |
| Requests | `service_requests`, `service_request_skills`, `service_request_attachments` |
| Matching | `matching_configurations` (versioned weights), `matching_results`, `matching_result_details` |
| Workflow | `service_invitations`, `service_assignments`, `task_submissions`, `submission_files`, `task_revisions`, `service_disputes`, `simulated_payments` |
| Communication | `conversations`, `messages`, `notifications`, `announcements` |
| Platform | `activity_logs` (audit), `rate_limits`, `schema_migrations` |

Design rules: InnoDB with foreign keys, `utf8mb4`, money as `DECIMAL(12,2)`, scores as `DECIMAL(5,2)`, UTC timestamps,
indexes on every foreign key and on filter columns (status, deadline, category). Uploaded file contents are never in the
database, only metadata and a random 40-hex `stored_name`. A uniqueness constraint prevents more than one active
assignment/invitation per request, and assignments are created inside a transaction with row locks.
