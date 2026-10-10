# ISO/IEC 25010 quality evaluation package (templates only)

> **No survey participants, responses, ratings or statistics exist in this repository and none were invented.**
> Every table below is an empty instrument to be completed by the researchers during the actual evaluation.

**Edition note.** ISO/IEC 25010 exists in a 2011 edition (usability) and a revised 2023 edition (which renames usability
to *interaction capability* and adds *flexibility* and *safety*). The authors must confirm which edition their institution
requires and verify exact terminology against the purchased standard text before finalising the instrument. The
characteristics below are the ones relevant to TaskBuddy; use the names of the chosen edition.

Selected characteristics: Functional suitability · Performance efficiency · Compatibility · Usability / Interaction
capability · Reliability · Security · Maintainability.

## 1. Test case template
| ID | Feature / objective | Preconditions | Steps | Expected result | Actual result | Pass/Fail | Evidence (screenshot/log) | Tester / date |
|---|---|---|---|---|---|---|---|---|

## 2. Functional testing checklist (to be ticked during execution – all unchecked)
- [ ] Register/login/logout as client and as provider; suspended user blocked
- [ ] Provider profile, categories, skills, weekly availability
- [ ] Create/edit/cancel a service request (validation messages)
- [ ] Ranked matches with explanations; excluded providers explained; over-budget flag
- [ ] Invite → accept/decline/expire; exclusive-task conflict
- [ ] Chat between assigned parties; notifications
- [ ] Submission with files; revision loop; approval
- [ ] Simulated payment states are labelled `SIMULATED / TEST TRANSACTION`
- [ ] Dispute opened and resolved by an administrator
- [ ] Admin: users, providers, catalog, requests, matching config versioning, reports/CSV, logs, announcements

## 3. Respondent questionnaire (5-point scale: 1 Strongly disagree … 5 Strongly agree)
Respondent role: ☐ client ☐ provider ☐ other ____ ; experience with similar apps: ____
| # | Characteristic | Statement | 1 | 2 | 3 | 4 | 5 |
|---|---|---|---|---|---|---|---|
| 1 | Functional suitability | The app provides the functions I need to request or offer services. | | | | | |
| 2 | Functional suitability | The suggested providers/requests were relevant to my needs. | | | | | |
| 3 | Usability | I could learn to use the app without assistance. | | | | | |
| 4 | Usability | The explanation of why a provider was suggested was understandable. | | | | | |
| 5 | Usability | Error messages helped me recover from mistakes. | | | | | |
| 6 | Performance efficiency | Screens loaded without noticeable delay. | | | | | |
| 7 | Reliability | The app behaved consistently and did not lose my data. | | | | | |
| 8 | Security | I felt my account and files were handled securely. | | | | | |
| 9 | Compatibility | The app worked on my device/OS version. | | | | | |
| 10 | Overall | The simulated payment screens made clear that no real money is used. | | | | | |
Open comments: ______________________________

## 4. Technical evaluator checklist
| Area | Check | Method/tool | Result | Notes |
|---|---|---|---|---|
| Functional suitability | All requirements in the traceability matrix covered | Test cases | | |
| Performance efficiency | Response times of key endpoints under defined load | Load tool of choice, record environment | | |
| Compatibility | Android/iOS versions, screen sizes; browsers for admin | Device matrix | | |
| Reliability | Behaviour on network loss, duplicate submits, concurrent invitations | Fault tests | | |
| Security | Authz/IDOR, injection, upload abuse, session handling, headers, secrets absent from repo | Review + OWASP checks | | |
| Maintainability | Layering, test coverage, static analysis (PHPStan/`flutter analyze`) | Tools | | |

## 5. Evaluation result recording format
| Evaluation date | Evaluator | Role | Characteristic | Instrument | Score / outcome | Evidence | Comments |
|---|---|---|---|---|---|---|---|

## 6. Matching-effectiveness measures (planned, not yet measured)
Precision@k against an expert-ranked ground truth, rank agreement (e.g. Kendall's τ), exclusion correctness against labelled cases.

## 7. Objective-to-feature traceability matrix
| Study objective (MasterPrompt §3) | Feature | Where implemented | Automated test evidence |
|---|---|---|---|
| Rule-based, explainable matching | Eligibility + weighted ranking + explanation JSON | `backend/app/Services/MatchingEngine.php`, `MatchingService.php` | `tests/Unit/MatchingEngineTest.php`, `WorkflowTest` |
| Configurable weights with audit | Versioned `matching_configurations` | `MatchingService::updateWeights`, admin *Matching configuration* | `AdminDashboardTest::testMatchingConfigurationVersioning` |
| Complete service workflow | State machine, invitations, submissions, revisions | `TaskService`, `InvitationService`, `RequestService` | `WorkflowTest` |
| Mobile client for both roles | Flutter app | `mobile/lib` | **none executed** (no SDK) |
| Administrator oversight | Admin dashboard | `admin/` | `AdminDashboardTest` |
| Security and privacy | Auth, authz, uploads, CSRF, audit | `backend/app/Middleware`, `FileService`, `admin/app` | `SecurityTest`, `FileSecurityTest`, `AdminDashboardTest` |
| Simulated payments | `simulated_payments` + labels | `PaymentService`, `mobile` payment screens | `WorkflowTest` (state), UI not executed |
