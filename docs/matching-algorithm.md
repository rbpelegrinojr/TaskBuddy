# Matching algorithm (v1.0.0)

TaskBuddy uses a **deterministic, rule-based, weighted** matcher. It is not machine learning and uses no randomness, no
network calls and no database access inside the scoring code (`backend/app/Services/MatchingEngine.php`). The same inputs
always produce the same ranking and the same explanation. `MatchingService` loads data and persists results;
`MatchingEngine` is a pure function over arrays.

## 1. Eligibility rules (hard filters)

A provider failing **any** rule is excluded and never scored. Every failed rule is recorded so the exclusion is explainable.

| Code | Rule |
|---|---|
| `ACCOUNT_NOT_ACTIVE` | provider account is not `active` (suspended / deactivated) |
| `PROFILE_INCOMPLETE` | profile is not complete or has no minimum rate |
| `CATEGORY_MISMATCH` | provider does not serve the request's category |
| `MISSING_MANDATORY_SKILL` | a skill the client marked mandatory is missing |
| `NO_AVAILABILITY_DECLARED` | no weekly availability declared (availability is **never assumed**) |
| `NOT_AVAILABLE_BEFORE_DEADLINE` | zero declared hours before the deadline, or fewer than `estimated_hours` |
| `PROVIDER_RESTRICTED` | provider is restricted by an administrator |
| `BLOCKED_BY_CLIENT` | the client blocked this provider |
| `CONFLICTING_ASSIGNMENT` | an exclusive task overlaps the window |

## 2. Scoring (eligible providers only)

```
MATCH_SCORE = (w_category·CATEGORY + w_skills·SKILLS + w_availability·AVAILABILITY + w_budget·BUDGET) / 100
```

* `CATEGORY` = 100 (non-matching categories are already excluded).
* `SKILLS` = matched requested skills ÷ requested skills × 100 (100 when no skills were requested).
* `AVAILABILITY` = 100 in v1.0.0 (binary: unavailable providers are excluded by the rules above). The hours available
  before the deadline are still reported in the explanation.
* `BUDGET` = 100 when the provider's minimum rate ≤ proposed compensation, otherwise 0 and the result is flagged
  `OVER_BUDGET`. Over-budget providers are **not** excluded; the client sees the flag.
* Weights are integers 0-100 that must total exactly 100. Defaults: category 30, skills 40, availability 20, budget 10.
* Arithmetic is done in integer basis points (0-10000) with half-up rounding, so results are exactly reproducible
  (no floating-point drift). Money is compared in integer cents.

**Tie-break:** higher final score → higher skills score → lower provider id.

## 3. Weight management and audit

Administrators change weights on **Matching configuration**. Each change creates a **new version**
(`matching_configurations`), deactivates the previous one and writes an `MATCHING_WEIGHTS_CHANGED` audit entry.
Every matching run stores the weights used (`matching_results.weights_json`), the algorithm version, the number of providers
evaluated, an exclusion summary, and a per-provider explanation (`matching_result_details.explanation_json`), so old results
remain reproducible after weights change.

## 4. Reference dataset (also an automated test)

Request: category 1, compensation 500.00, mandatory skill 10 plus optional skills 11-13, Monday 09:00 → Friday 17:00.
Weights 30/40/20/10. Source: `backend/tests/fixtures/matching_dataset.php`, asserted in `MatchingEngineTest`.

| Provider | Situation | Result |
|---|---|---|
| P1 | all 4 skills, rate 400 | **rank 1, score 100** |
| P4 | all 4 skills, rate 800 | rank 2, score **90**, `OVER_BUDGET` |
| P2 | 3 of 4 skills, rate 450 | rank 3, score **90** (ties with P4; P4 ranks higher on the skills tie-break, 100 vs 75) |
| P3 | 1 of 4 skills, rate 300 | rank 4, score **70** |
| P5 | lacks mandatory skill | excluded `MISSING_MANDATORY_SKILL` |
| P6 | wrong category | excluded `CATEGORY_MISMATCH` |
| P7 | suspended | excluded `ACCOUNT_NOT_ACTIVE` |
| P8 | no availability declared | excluded `NO_AVAILABILITY_DECLARED` |
| P9 | blocked by client | excluded `BLOCKED_BY_CLIENT` |

Worked check for P2: 30·100 + 40·75 + 20·100 + 10·100 = 9000 → 90.00. P4: 30·100 + 40·100 + 20·100 + 10·0 = 9000 → 90.00.

## 5. Evaluation (for the study)

Beyond the unit tests, the planned effectiveness measures are listed in `docs/iso25010-evaluation.md` (precision@k against
expert ranking, rank agreement, exclusion correctness). **No such measurements have been collected yet**; the dataset above
is a correctness test, not an effectiveness result.
