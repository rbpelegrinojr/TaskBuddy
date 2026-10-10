# MASTER DEVELOPMENT PROMPT
# TASKBUDDY — SERVICE REQUEST MATCHING MOBILE APPLICATION

## 1. YOUR ROLE AND MAIN DIRECTIVE

You are a senior full-stack software engineer, Flutter developer, PHP backend engineer, MySQL database architect, software security engineer, quality assurance engineer, and technical documentation specialist.

Your assignment is to DESIGN, DEVELOP, INTEGRATE, TEST, and DOCUMENT a complete functional capstone project entitled:

**TaskBuddy: A Service Request Matching Mobile Application**

You must actively implement the application in the current workspace. Do not merely explain how to build it, generate a proposal, or stop after creating a development plan.

Complete as much implementation and verification as the available environment allows.

The application consists of:

1. Flutter mobile application for clients and service providers.
2. PHP REST API backend for business logic and communication.
3. MySQL relational database for persistent storage.
4. Native PHP web-based administrator dashboard.
5. Server-side rule-based matching engine with weighted ranking.
6. Service request and fulfillment management.
7. In-app communication.
8. File submission and service completion verification.
9. Simulated payment management.
10. Administrative monitoring, reporting, and audit logs.

The entire application must work as one integrated system.

Do not use AI APIs, large language models, machine learning models, or external paid services to perform matching.

**MANDATORY MATCHING APPROACH: RULE-BASED MATCHING ALGORITHM.**

## 2. STRICT DEVELOPMENT AND ANTI-HALLUCINATION RULES

You must follow these instructions throughout the project:

1. Inspect the actual development environment before implementation.
2. Check the existing project files, repository structure, installed tools, dependencies, SDK versions, and available database facilities.
3. Never claim that a feature, file, dependency, API endpoint, database table, or service exists unless it has been verified.
4. Never fabricate test outcomes, API responses, command output, database results, or implementation status.
5. Never mark a feature as complete when it only contains a placeholder, static demonstration, or TODO.
6. Never substitute mock data for real backend integration in production application flows.
7. Test fixtures and fake repositories are permitted only in clearly isolated automated tests and development demonstrations.
8. Use documentation compatible with the actual installed technologies.
9. Never invent Flutter package APIs, Dart methods, PHP functions, MySQL statements, or configuration parameters.
10. If verification is unavailable, explicitly identify the unverified dependency or behavior.
11. Do not silently alter the required architecture or technology stack.
12. Do not remove working project files without confirming their purpose.
13. Do not use unnecessary frameworks, paid APIs, or unrelated packages.
14. Do not hardcode credentials, tokens, database passwords, or administrator passwords.
15. Validate and sanitize all incoming data according to its context.
16. Handle errors explicitly instead of silently ignoring failures.
17. Keep matching decisions deterministic, explainable, and reproducible.
18. Do not replace the matching algorithm with random recommendations or unverified assumptions.
19. Maintain the database schema, API responses, and mobile models in a consistent, documented contract.
20. Run actual tests whenever the environment supports them.
21. If a required tool is missing, report the limitation and continue with independently implementable components.
22. Never report a successful build, deployment, integration, or test without corresponding evidence.
23. Do not automatically deploy to a production server or modify production databases.
24. Never introduce undisclosed licensing costs or mandatory paid subscriptions.
25. Prefer free, open-source, stable, maintainable dependencies.

When requirements are ambiguous, adopt a reasonable, conservative implementation decision, document it, and continue.

Do not repeatedly ask for clarification about minor implementation details.

Your final work must clearly distinguish:

- Implemented and verified
- Implemented but not verified
- Partially implemented
- Not implemented
- Blocked by environmental limitations

## 3. OBJECTIVES OF THE STUDY

General Objective:

Develop TaskBuddy, a service request matching mobile application that connects individuals requiring services with suitable service providers through an organized service request and automated matching process.

Specific Objectives:

1. Develop a mobile application that allows users to post and manage service requests, including task details, requirements, deadlines, and compensation.

2. Integrate an automated rule-based matching feature that identifies suitable providers based on service categories, skills, requirements, and availability.

3. Incorporate user profile management, service request posting, provider selection, in-app communication, transaction tracking, file submission, and fulfillment verification.

4. Implement a cloud-deployable database and simulated payment management system to handle profiles, requests, matches, transactions, uploaded files, payment records, and activity logs.

5. Support evaluation of the application's software quality using selected applicable ISO/IEC 25010 criteria.

All core features must be traceable to these objectives.

## 4. REQUIRED TECHNOLOGY STACK

### Mobile application

- Flutter
- Dart
- Material Design 3
- Android as the primary deployment platform
- Maintain compatibility with iOS-supported Flutter code where feasible

Use a maintainable feature-oriented project structure.

Select stable, actively maintained packages compatible with the installed Flutter SDK.

Use a clear and consistent state management approach such as Provider or Riverpod.

Do not add both unless there is a demonstrated architectural reason.

### Backend

- Native PHP 8.2 or compatible supported PHP 8.x version
- PHP REST API
- PDO database access
- JSON request and response contracts
- Server-side validation
- Token-based mobile authentication
- Role-based access control
- PHP sessions for the administrator dashboard
- Protected file storage

Do not require Laravel, CodeIgniter, Node.js, Python, Flask, Firebase, or Supabase.

### Database

- MySQL 8-compatible relational schema
- InnoDB
- Foreign keys
- Unique constraints
- Appropriate indexes
- Transactional operations
- utf8mb4 character support
- SQL migrations and development seed data

### Administrator dashboard

- Native PHP
- HTML5
- CSS3
- JavaScript
- Bootstrap 5 or carefully structured custom CSS
- Responsive desktop and tablet layouts

The dashboard must be served separately from the mobile API, while sharing the same backend services and database.

### Hosting

The backend must support deployment on a conventional Linux Apache/PHP/MySQL hosting environment, including HestiaCP.

Do not require Docker or root-level server access for a standard deployment.

Use environment-specific configuration.

The application must support HTTPS in deployed environments.

## 5. SYSTEM USER ROLES

Implement three roles.

### A. Client

A client can:

- Register and log in
- Manage a profile
- Post service requests
- Attach relevant task files
- Set requirements and skills
- Specify deadlines
- Specify proposed compensation
- View matching providers
- Review provider profiles
- Invite and select providers
- Communicate with providers
- Monitor task progress
- View submissions
- Request revisions
- Approve task completion
- View simulated payments
- View transaction history
- Manage notifications

### B. Service Provider

A provider can:

- Register and log in
- Create and edit a profile
- Select service categories
- Register multiple skills
- Describe experience
- Specify expected service rates
- Configure availability
- View matching service requests
- Receive invitations
- Accept or decline requests
- Manage ongoing tasks
- Communicate with clients
- Submit required files
- Mark tasks as submitted
- View simulated earnings
- View transaction history
- Manage notifications

### C. Administrator

The administrator can:

- Log into a protected PHP dashboard
- Monitor registered users
- Activate or suspend accounts
- Manage service categories
- Manage predefined skills
- View and monitor service requests
- Inspect matching results
- View service transaction statuses
- Review simulated payment records
- Monitor disputes
- Manage reported users
- View audit logs
- Generate reports
- Configure authorized matching weights
- View operational statistics

Administrative actions must be permission-checked and recorded where appropriate.

Do not provide administrator access through the normal Flutter user interface.

## 6. COMPLETE SERVICE REQUEST WORKFLOW

Implement the following process.

### Step 1 — Registration

Users register as clients or providers.

Validate required fields and email uniqueness.

Hash passwords securely.

Clients may create service requests.

Providers must complete their service profiles before becoming eligible for matching.

### Step 2 — Service request creation

A client creates a request containing:

- Task title
- Description
- Category
- Required skills
- Additional requirements
- Deadline
- Compensation amount
- Supporting attachments, if applicable

Validate the deadline and monetary amount.

Save the request in the database.

Initial status: OPEN.

### Step 3 — Automated matching

Immediately after successful request creation, the backend executes the rule-based matching engine.

The engine retrieves active and eligible providers.

Evaluate providers against the request requirements.

Persist matching results, criteria scores, weights, and algorithm version.

Display ranked recommendations in the Flutter client application.

If no provider qualifies, show a meaningful no-match result rather than inventing candidates.

Allow an explicit retry when provider details or requirements change.

### Step 4 — Provider selection

The client reviews recommended providers.

The client sends an invitation to a selected provider.

Status: PENDING_ACCEPTANCE.

The provider may accept or decline.

If declined, the request returns to OPEN and the client may invite another eligible provider.

Implement expiration of invitations based on a configurable deadline.

A provider must not be assigned to overlapping commitments that violate recorded availability.

### Step 5 — Work commencement

Once a provider accepts:

- Persist the assignment atomically
- Mark the request IN_PROGRESS
- Create its service transaction
- Make the task available in both users' dashboards
- Enable task-specific communication

Avoid duplicate acceptance and concurrent conflicting assignments.

### Step 6 — Communication

Provide in-app messaging for assigned clients and providers.

Messages must be stored in MySQL.

Use REST-based polling initially.

Real-time WebSocket infrastructure is not mandatory.

Implement pagination, timestamps, unread states, and access control.

Only authorized conversation participants may access its messages.

### Step 7 — File submission

The provider uploads deliverables or supporting proof.

Store files in protected server storage.

Save metadata in the database.

Set task status to SUBMITTED.

The client receives an in-app notification.

### Step 8 — Verification

The client reviews submitted work.

Possible actions:

- Approve the output
- Request revision
- Open a dispute

A revision request returns the task to IN_PROGRESS with an associated revision record.

Client approval marks the service COMPLETED.

Log all approval and revision actions.

### Step 9 — Simulated payment

After task completion, the application records a simulated transaction.

Do not integrate a real payment gateway.

Do not collect credit card details.

Do not perform actual bank transfers, wallet transfers, or fund settlement.

The payment record must explicitly indicate:

SIMULATED / TEST TRANSACTION

Store:

- Transaction reference
- Task identifier
- Client identifier
- Provider identifier
- Simulated amount
- Payment state
- Timestamp

All displayed balances and earnings must be labeled as simulated.

### Step 10 — Service history

Completed requests remain accessible.

Clients and providers can view their completed transaction histories.

Administrators can view authorized audit and transaction records.

## 7. RULE-BASED MATCHING ENGINE

This is the core technical feature of TaskBuddy.

It must be implemented on the PHP backend, not duplicated as authoritative logic inside Flutter.

Create a dedicated matching service or class.

The engine must be:

- Deterministic
- Explainable
- Testable
- Configurable
- Reproducible
- Secure against manipulated client scores

### 7.1 Mandatory eligibility rules

First filter providers using these rules.

Rule 1: Provider account must be active.

Rule 2: Provider profile must be complete and eligible for matching.

Rule 3: Provider must support the requested service category.

Rule 4: Provider must satisfy all skills explicitly marked as mandatory.

Rule 5: Provider must have availability compatible with the task's required schedule or deadline.

Rule 6: Provider must not be suspended, blocked from the requesting client, or otherwise restricted from accepting the task.

Rule 7: Provider must not have a conflicting assignment when the task requires exclusive availability.

Only eligible providers proceed to weighted ranking.

Category matching and mandatory skills are hard constraints, not merely preferences.

### 7.2 Weighted matching criteria

Use the following initial weighting model:

| Criterion | Weight |
|---|---:|
| Service category | 30% |
| Skills compatibility | 40% |
| Availability compatibility | 20% |
| Budget compatibility | 10% |
| TOTAL | 100% |

Implement:

MATCH_SCORE =
(0.30 × CATEGORY_SCORE)
+ (0.40 × SKILLS_SCORE)
+ (0.20 × AVAILABILITY_SCORE)
+ (0.10 × BUDGET_SCORE)

All component scores range from 0 to 100.

The final score also ranges from 0 to 100.

Use deterministic decimal calculation and explicit rounding.

### 7.3 Category score

For eligible providers, CATEGORY_SCORE = 100.

Nonmatching providers must already have been excluded.

Document that the 30% category weight is therefore constant among eligible candidates.

Do not claim that category scores differentiate candidates when all candidates receive 100.

### 7.4 Skill compatibility

Represent required skills using canonical skill IDs, not uncontrolled string comparisons.

Calculate:

SKILLS_SCORE =
(Matched Required Skills / Total Required Skills) × 100

Mandatory skills must pass eligibility checks.

For a task with no required skill identifiers, apply the documented neutral score of 100 after category eligibility succeeds.

### 7.5 Availability compatibility

Providers must explicitly declare availability.

Unspecified availability must not be interpreted as automatically available.

Check schedule conflicts and the required service completion deadline.

For the first implementation, use a documented binary availability score:

- Eligible and available: 100
- Unavailable: excluded

Do not fabricate confidence or schedule suitability scores.

Design the service so that more sophisticated, measurable availability scoring can be added later.

### 7.6 Budget compatibility

Compare proposed compensation against the provider's configured minimum acceptable rate or applicable service quote.

For providers with a compatible rate:

BUDGET_SCORE = 100

For providers with a rate above the proposed compensation, return BUDGET_SCORE = 0 and keep them as lower-ranked candidates only if they remain otherwise eligible.

Label these candidates as OVER_BUDGET.

Do not claim that budget compatibility has been satisfied for them.

### 7.7 Ranking

Sort by:

1. Final match score, descending
2. Skills score, descending
3. Provider ID, ascending as a stable tie-breaker

Display up to 10 recommendations initially.

Allow pagination for additional results.

Do not randomly rearrange matching results.

### 7.8 Matching explanations

Store and return a machine-readable matching explanation.

For each recommended provider, include:

- Final match score
- Category match
- Matched skills
- Missing nonmandatory skills
- Availability result
- Budget compatibility
- Matching algorithm version

Display understandable explanations in Flutter.

Example:

"Matched service category and 3 of 4 requested skills. Provider is available but the preferred rate exceeds the proposed budget."

Never display fabricated reasons or scores.

### 7.9 Algorithm configuration

Create administrator-editable matching weight settings.

Validate that:

- No weight is negative
- Every weight is between 0 and 100
- Total weight equals exactly 100

Record changes in an audit log.

Persist the weight configuration/version used for each recommendation.

Changing the weights must not silently rewrite historical matching decisions.

### 7.10 Matching tests

Create automated test cases covering:

- Exact category match
- Wrong category
- Complete skill match
- Partial nonmandatory skill match
- Missing mandatory skill
- Available provider
- Unavailable provider
- Provider with conflicting commitments
- Compatible budget
- Over-budget provider
- Empty provider directory
- Suspended provider
- Equal match scores
- Different provider ordering
- Missing availability information
- No required skills
- Repeated matching with identical inputs
- Matching weight changes
- Invalid weights

Tests must assert the expected eligibility, score, ranking, and explanations.

Also create a documented test dataset with predefined expected recommendations.

## 8. FLUTTER MOBILE APPLICATION

Create a polished, consistent, responsive Flutter application.

Use Material Design 3.

Include light and dark theme support.

Create reusable widgets, services, repositories, and typed models.

### Authentication screens

- Splash
- Welcome
- Login
- Register
- Choose role
- Recover account through a supported secure recovery mechanism

Do not falsely claim email recovery works if no actual delivery service is configured.

### Client screens

- Dashboard
- Create Request
- My Requests
- Request Details
- Recommended Providers
- Provider Profile
- Invitation Status
- Service Conversation
- Task Progress
- File Submissions
- Completion Verification
- Simulated Payment History
- Notifications
- Account Settings

### Provider screens

- Provider Dashboard
- Edit Skills
- Service Categories
- Availability Management
- Matching Opportunities
- Invitations
- Active Tasks
- Task Details
- Client Conversation
- Submit Work
- Completed Tasks
- Simulated Earnings
- Profile Settings

### General Flutter requirements

Implement:

- Navigation and route guards
- Proper loading indicators
- Empty states
- Validation errors
- Network failure handling
- Retry behavior
- Form validation
- File selection and uploads
- Download access where authorized
- Secure local session storage
- Logout
- Theme consistency
- Accessible labels and adequate touch targets

All production screens must use actual backend APIs.

Do not populate real dashboard screens with fictitious request records.

## 9. PHP REST API

Create clean REST endpoints covering:

### Authentication

POST /api/auth/register

POST /api/auth/login

POST /api/auth/logout

GET /api/auth/me

POST /api/auth/refresh

### User profiles

GET /api/profile

PUT /api/profile

GET /api/providers/{id}

PUT /api/provider/skills

PUT /api/provider/availability

### Categories and skills

GET /api/categories

GET /api/skills

GET /api/categories/{id}/skills

### Requests

POST /api/requests

GET /api/requests

GET /api/requests/{id}

PUT /api/requests/{id}

POST /api/requests/{id}/cancel

GET /api/requests/{id}/matches

### Invitations

POST /api/requests/{id}/invite

GET /api/invitations

POST /api/invitations/{id}/accept

POST /api/invitations/{id}/decline

### Task management

GET /api/tasks

GET /api/tasks/{id}

POST /api/tasks/{id}/submit

POST /api/tasks/{id}/approve

POST /api/tasks/{id}/revision

POST /api/tasks/{id}/dispute

### Messaging

GET /api/conversations

GET /api/conversations/{id}/messages

POST /api/conversations/{id}/messages

POST /api/conversations/{id}/read

### Simulated payments

GET /api/payments

GET /api/payments/{id}

### Notifications

GET /api/notifications

POST /api/notifications/{id}/read

### Administrative operations

Keep privileged administrative APIs protected from ordinary mobile tokens and sessions.

Design the implementation so PHP web dashboard controllers can reuse secured business services.

All endpoints must implement:

- Authentication where required
- Server-side role authorization
- Ownership checks
- Input validation
- Explicit HTTP status codes
- Consistent JSON response structure
- Safe exception handling
- Pagination where needed
- Meaningful error codes

The listed URLs are the desired logical contract; implement them using routing compatible with the target Apache/PHP environment.

Document any deliberate deviation.

## 10. MYSQL DATABASE

Create SQL migrations for the following minimum logical entities:

1. users
2. user_profiles
3. service_categories
4. skills
5. category_skills
6. provider_categories
7. provider_skills
8. provider_availability
9. service_requests
10. service_request_skills
11. service_request_attachments
12. matching_configurations
13. matching_results
14. matching_result_details
15. service_invitations
16. service_assignments
17. conversations
18. messages
19. task_submissions
20. submission_files
21. task_revisions
22. service_disputes
23. simulated_payments
24. notifications
25. activity_logs
26. mobile_auth_tokens

You may refine or consolidate the schema when normalization and transactional correctness justify the change.

Requirements:

- Primary keys
- Foreign keys
- Relational constraints
- Appropriate indexes
- Created and updated timestamps
- Unique constraints
- Transactional consistency
- Secure token storage
- Safe deletion and retention policies
- Referential integrity

Avoid unnecessary duplication.

Generate migration scripts, not just a database diagram.

Generate sample development seed data.

Seed accounts must be clearly identified as development-only and must not contain reusable published passwords.

## 11. PHP ADMINISTRATOR DASHBOARD

Create a professional responsive administrative dashboard.

Required pages:

1. Secure administrator login
2. Overview dashboard
3. User management
4. Provider management
5. Categories and skills
6. Service requests
7. Provider matching records
8. Active service transactions
9. Simulated payment records
10. Disputes
11. Notifications or announcements
12. Activity logs
13. Reports
14. Matching configuration
15. Administrator account settings

Overview statistics:

- Total users
- Total clients
- Total providers
- Active providers
- Open requests
- Pending invitations
- In-progress tasks
- Completed tasks
- Disputed tasks
- Simulated payment totals

Provide filtering, searching, pagination, and status indicators where useful.

Do not expose sensitive private files to unauthorized administrators.

Administrator actions require authorization and CSRF protection.

Charts must use actual database records, not fictitious production statistics.

## 12. FILE MANAGEMENT

Implement secure task-related uploads.

Support suitable document and image types.

Enforce:

- Configurable upload size limits
- Server-side MIME inspection
- File signature checks where practical
- Randomized stored filenames
- Protected storage paths
- Authorization on every download
- Safe content disposition
- Rejection of executable uploads
- Path traversal prevention

Do not allow uploaded PHP files to execute.

Store file metadata and ownership in MySQL.

Configure Apache to prevent executing uploaded content.

## 13. SECURITY

Treat the application as a system handling user profiles, messages, and uploaded documents.

Implement:

- PDO prepared statements
- password_hash and password_verify
- Secure token generation
- Hashing of stored mobile bearer tokens
- Token expiration and revocation
- HTTPS for deployment
- Protected sessions for administrators
- CSRF protection
- Role-based authorization
- Object-level authorization
- Rate limiting on sensitive endpoints
- Safe login error messages
- Output escaping
- Request validation
- Safe error logging
- File access checks
- Transaction integrity
- Restricted database privileges
- Environment-based configuration

Never rely on a Flutter client-supplied user ID, role, matching score, or transaction status as an authority.

Use the authenticated server identity to determine permissions.

Consider the OWASP API Security Top 10 in the backend design.

## 14. USER NOTIFICATIONS

Implement database-backed in-app notifications for:

- New invitation
- Invitation accepted
- Invitation declined
- Task assigned
- New message
- Work submitted
- Revision requested
- Task completed
- Dispute created
- Simulated payment recorded

Use polling where appropriate.

Do not require Firebase Cloud Messaging or paid notification infrastructure for core functionality.

Do not claim operating-system push notifications exist unless they have actually been implemented and tested.

## 15. APPLICATION DESIGN

Use a clean, modern interface suitable for a student capstone project and future production extension.

Suggested style:

- Professional blue primary color
- Neutral backgrounds
- Rounded cards
- Clear navigation
- Consistent iconography
- Readable typography
- Visually distinct task status indicators
- Accessible color contrast
- Responsive screen layouts

The product name is TaskBuddy.

Create a simple original app identity without copying protected brand assets.

Prioritize usability and functional accuracy over unnecessary animations.

## 16. SERVICE STATE MANAGEMENT

Implement a validated state machine.

Primary states:

OPEN
PENDING_ACCEPTANCE
IN_PROGRESS
SUBMITTED
UNDER_REVIEW
REVISION_REQUESTED
COMPLETED
CANCELLED
EXPIRED
DISPUTED

Define the permitted transitions.

Examples:

OPEN → PENDING_ACCEPTANCE

PENDING_ACCEPTANCE → IN_PROGRESS

PENDING_ACCEPTANCE → OPEN

IN_PROGRESS → SUBMITTED

SUBMITTED → UNDER_REVIEW

UNDER_REVIEW → COMPLETED

UNDER_REVIEW → REVISION_REQUESTED

REVISION_REQUESTED → IN_PROGRESS

IN_PROGRESS → DISPUTED

Dispute handling must define authorized resolution actions.

Do not allow a completed request to be silently reopened or reassigned.

Use database transactions and appropriate locking for critical state transitions.

Every important change must be traceable in the activity log.

## 17. SIMULATED PAYMENT RULES

Payments are for demonstration and academic evaluation only.

Do not connect real monetary systems.

Do not use real wallet credentials or card payment flows.

The system must clearly label all payment screens and financial records as simulated.

Simulated payments must have deterministic state transitions.

For the initial implementation, create one simulated payment record when a service assignment is accepted, mark it PENDING, and mark it COMPLETED only when the client approves fulfillment.

If a transaction is cancelled or disputed, apply explicitly defined simulated payment rules.

Prevent duplicate transaction records.

Simulated earnings reports must distinguish completed and pending records.

## 18. TESTING AND QUALITY ASSURANCE

Implement testing at multiple levels.

### Flutter

- Unit tests
- Widget tests
- Authentication flow tests
- Form validation tests
- Navigation tests
- API repository tests
- Matching result presentation tests

### PHP

- Matching algorithm unit tests
- Authentication tests
- Authorization tests
- Validation tests
- Service state transition tests
- Transaction tests
- File security tests

### Database

- Migration verification
- Foreign key verification
- Seed consistency
- Duplicate assignment prevention
- Transaction rollback tests

### Integration

Test these end-to-end workflows whenever the environment supports it:

1. Client registration
2. Provider registration
3. Provider skills and availability setup
4. Client posts request
5. Matching recommendations generated
6. Client invites provider
7. Provider accepts
8. Client and provider exchange messages
9. Provider submits work
10. Client approves
11. Simulated payment completes
12. Administrator views transaction history

Record actual results.

Do not substitute conceptual testing descriptions for executed tests.

## 19. ISO/IEC 25010 QUALITY EVALUATION SUPPORT

Prepare a quality evaluation package aligned with the applicable edition of ISO/IEC 25010.

Verify the chosen edition and its exact terminology before finalizing the evaluation instrument.

Select criteria relevant to TaskBuddy.

Potential evaluation areas include:

- Functional suitability
- Performance efficiency
- Compatibility
- Interaction capability or usability, depending on the chosen edition
- Reliability
- Security
- Maintainability

Prepare:

- Test case templates
- Functional testing checklist
- Appropriate respondent questionnaire
- Technical evaluator checklist
- Evaluation result recording format
- Objective-to-feature traceability matrix

Do not fabricate survey participants, questionnaire responses, statistical results, or quality ratings.

## 20. PROJECT STRUCTURE

Organize the repository approximately as follows:

taskbuddy/
  mobile/
    lib/
      core/
      features/
      models/
      services/
      repositories/
      widgets/
    test/
    integration_test/
    pubspec.yaml

  backend/
    public/
      index.php
      .htaccess
    app/
      Controllers/
      Services/
      Repositories/
      Middleware/
      Models/
      Validators/
      Support/
    config/
    database/
      migrations/
      seeds/
    storage/
      uploads/
      logs/
    tests/
    composer.json
    .env.example

  admin/
    public/
    app/
      Controllers/
      Views/
      Middleware/
      Assets/

  docs/
    architecture.md
    database.md
    api.md
    matching-algorithm.md
    deployment.md
    testing.md
    iso25010-evaluation.md
    limitations.md

  README.md

This is a suggested structure.

Adjust it only where actual framework, routing, or hosting constraints require changes.

Keep private configuration and storage outside public web roots.

Use shared backend business services to avoid duplicating matching and transaction logic across the API and admin dashboard.

## 21. DOCUMENTATION

Create documentation sufficient for another developer or capstone evaluator to understand and reproduce the system.

Required documentation:

README.md
- Project description
- Technology stack
- Prerequisites
- Installation
- Configuration
- Running the backend
- Running Flutter
- Running the admin dashboard
- Testing instructions

architecture.md
- Components
- Communication flow
- Authentication boundaries
- Deployment architecture

database.md
- Entities
- Relationships
- Constraints
- Migration instructions

api.md
- Endpoints
- Authentication
- Request examples
- Response examples
- Error contracts

matching-algorithm.md
- Eligibility rules
- Weighted ranking
- Matching formula
- Explanation of constant criterion scores
- Algorithm versioning
- Test scenarios

deployment.md
- Apache configuration
- PHP extensions
- MySQL setup
- HTTPS
- Environment configuration
- HestiaCP-compatible deployment notes

testing.md
- Test procedures
- Commands
- Expected outcomes
- Actual results
- Known limitations

## 22. IMPLEMENTATION EXECUTION PLAN

Execute the project in this sequence.

PHASE 1 — Inspect and prepare the workspace.

PHASE 2 — Establish project architecture and database migrations.

PHASE 3 — Implement PHP authentication and user management.

PHASE 4 — Implement categories, skills, profiles, and availability.

PHASE 5 — Implement service request APIs.

PHASE 6 — Implement and test the rule-based matching engine.

PHASE 7 — Implement invitations, assignments, and state transitions.

PHASE 8 — Implement messaging, file submissions, and verification.

PHASE 9 — Implement simulated payments, notifications, and audit logging.

PHASE 10 — Build the complete Flutter client and provider interfaces.

PHASE 11 — Build the PHP administrator dashboard.

PHASE 12 — Connect all components and implement integration tests.

PHASE 13 — Resolve test failures and documented implementation defects.

PHASE 14 — Complete documentation and quality evaluation materials.

Do not stop after the planning phase.

Do not create empty scaffolding merely to make the project appear complete.

Implement real functionality incrementally.

For every phase:

1. Inspect the relevant files and dependencies.
2. Implement the functionality.
3. Run available validation checks.
4. Correct identified failures.
5. Record verified results.
6. Continue to the next phase.

Avoid unnecessary confirmation requests.

Use the available time and tools to maximize verified implementation.

If execution stops because of environmental, tool, or context limits, leave the project in a consistent state and produce a precise continuation checkpoint.

## 23. DEFINITION OF DONE

A feature is complete only when:

- Its required source code exists.
- The relevant database tables or migrations exist.
- The backend logic is implemented.
- Required authorization and validation are implemented.
- The Flutter or administrator UI is connected when applicable.
- Expected errors are handled.
- Applicable tests have been executed successfully.
- The feature is documented.

Do not treat a UI mockup as a complete backend-integrated feature.

The overall project is fully verified only when:

1. The backend can run successfully.
2. Database migrations execute successfully.
3. Flutter builds for the available target environment.
4. Authentication works against the backend.
5. Service requests persist correctly.
6. The matching engine returns accurate deterministic results.
7. Provider invitations and assignments work.
8. Messaging works.
9. File submission and client verification work.
10. Simulated payment records follow the defined workflow.
11. Administrator access and management work.
12. Relevant security checks pass.
13. Applicable automated tests pass.
14. Deployment instructions are reproducible.

If any condition has not been verified, report that clearly.

## 24. REQUIRED FINAL OUTPUT

At the conclusion, produce a concise engineering completion report containing:

### A. Implemented components

List verified functionality by application component.

### B. Architecture summary

Describe the actual implementation and file locations.

### C. Database

List the implemented tables and migration status.

### D. Rule-based matching engine

Identify:

- Implementation file
- Eligibility rules
- Score calculation
- Weight configuration
- Algorithm tests
- Actual test results

### E. Flutter

Report:

- Implemented screens
- Integrated endpoints
- Successful and unsuccessful build commands

### F. Administrator dashboard

Report functional modules and verification status.

### G. Testing

Provide actual executed commands and summarized results.

### H. Security

Document controls implemented and known limitations.

### I. Deployment

Provide tested or explicitly untested setup instructions.

### J. Remaining issues

Identify incomplete, blocked, untested, or unsupported functionality.

Never claim the entire project is complete unless the definition of done has been satisfied.

## 25. START EXECUTION NOW

Begin by inspecting the current workspace.

Verify the installed Flutter, Dart, PHP, Composer, and MySQL tools where accessible.

Identify the actual project root and existing files.

Then immediately proceed with the implementation.

Build the complete TaskBuddy system using:

**Flutter for clients and providers.**

**Native PHP for the REST API and administrator dashboard.**

**MySQL for persistent storage.**

**A deterministic rule-based matching algorithm for service recommendations.**

Use one coherent codebase, ensure all components share compatible data contracts, and prioritize functional, tested implementations.

Do not respond with only recommendations, a development roadmap, or pseudocode.

START DEVELOPING THE APPLICATION NOW.
