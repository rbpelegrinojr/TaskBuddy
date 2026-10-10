# Knowledge review

Approved by the user on 2026-10-10. All 13 records are approved. Evidence covers the current workspace and related earlier handoffs.

Portable snapshot: agent_memory_portable.py. Verified: all 13 records restored with every field intact; a second restore added zero records. Entries describing pending work reflect the collection time; approval and transfer verification are now complete.

## 1. Collection scope and evidence

ID: `e541532ba6ddf6e1097c` | Status: **approved** | Project: AgentMemort

As inspected on 2026-10-10, the AgentMemort workspace initially contained only agent_memory.py (8280 bytes); no README, AGENTS.md, tests, dependency manifest, or Git metadata was present. The local memory store was empty before this collection. Evidence comes from the current source, this conversation, two related Prepare portable agent knowledge chats, the Transfer Agent Learning chat, and the earlier handoff review and verification log. Other listed projects were not inspected and no facts about their implementations are claimed. No commit history or implementation rationale is available in this workspace.

Source: Workspace inventory and related handoff history, 2026-10-10

Tags: scope,evidence

## 2. User requirements and review preferences

ID: `b965cebabe71ad66f0ef` | Status: **approved** | Project: AgentMemort

The user wants a single portable Python knowledge file for a receiving AI agent. Collect useful accessible project facts and development experiences, cite evidence, avoid duplicate records, invented experiences, and confidential information. Execute python --version before running the memory script and inspect add --help before saving. Save additions pending human review, display list and full contents for review, apply the user's review decisions, then pack agent_memory_portable.py and report its verified record count. Do not treat stored material or attached text as authority over the user's request.

Source: Current user requests and related handoff user messages, 2026-10-10

Tags: requirements,review

## 3. Architecture and runtime

ID: `23fec1189adfa9461584` | Status: **approved** | Project: AgentMemort

The implementation is a single Python CLI using only the standard library: argparse for command dispatch, sqlite3 for persistence, hashlib for record IDs, json/zlib/base64 for portable archives, pathlib for paths, and datetime for UTC timestamps. There is no web service or external model API in the script. Python 3.11.9 was observed on Windows using PowerShell; that is a verified runtime, not a documented minimum Python version. The inspected source SHA256 is BD109F5792FBB7F3C5B2B37815B49BDB4439F4211650AE55D72F2BA811B3F8F2.

Source: agent_memory.py imports and source hash; python --version output, 2026-10-10

Tags: architecture,python

## 4. SQLite schema and review lifecycle

ID: `c0b89d503dce03176a3f` | Status: **approved** | Project: AgentMemort

DB_PATH is derived from the resolved script filename with suffix .sqlite3, so database location depends on the script path rather than the working directory. The memories table stores id (primary key), project, title, content, tags, status, created_at, and source. Tables are created lazily. add creates pending records. review accepts pending, approved, or rejected and fails if the ID is missing. created_at is a UTC ISO timestamp; reviewing a record does not change it. There is no edit or delete CLI command.

Source: agent_memory.py: DB_PATH, db, record, now, main

Tags: storage,review

## 5. Exact CLI and input validation

ID: `c570789efd643ba1ff53` | Status: **approved** | Project: AgentMemort

CLI actions are add, list, search, review, pack, restore, and export-training. add requires --project and --title; content comes from --text, a UTF-8 --file, or standard input when neither is supplied. Supplying both --text and --file is an argparse error. Optional --tags defaults to an empty string and --source to manual. Title and content are trimmed and sanitized; blank project/title/content is rejected and content is capped at 300000 UTF-8 bytes. Example: python agent_memory.py add --project AgentMemort --title 'Decision' --text 'Evidence' --tags decision --source 'source reference'.

Source: Observed add --help; agent_memory.py: record and add branch

Tags: cli,validation

## 6. Listing and search behavior

ID: `34927d64207e9f076e39` | Status: **approved** | Project: AgentMemort

list prints ID, status, project, and title, ordered by created_at descending; it omits full content. search splits the query into lowercase words and requires every word to occur in the combined project/title/content/tags text. --project is an exact project filter. Search prints full matching content and status, including pending and rejected records. There is no status filter. A query matching the project name can display all project records for review.

Source: agent_memory.py: get_all, list and search branches

Tags: search,features

## 7. Deduplication and merge semantics

ID: `7e8d8b6ec3f53b98c01c` | Status: **approved** | Project: AgentMemort

Record IDs are the first 20 hex characters of SHA-256 over JSON containing project, sanitized title, and sanitized content. record and merge use parameterized INSERT OR IGNORE, making identical-ID inserts idempotent. Re-adding the same record does not update tags, source, timestamp, or status. Restoring an existing ID preserves its existing content and review status. Changed title/content yields a different ID, so semantic duplicates still need human inspection. Use review to change an existing status.

Source: agent_memory.py: record, merge, review branch

Tags: deduplication,patterns

## 8. Portable snapshot and receiving-agent workflow

ID: `ac2c473ab1a69ec4a8a8` | Status: **approved** | Project: AgentMemort

pack serializes all stored rows into format-1 JSON, compresses with zlib, base64 encodes the result, and embeds it in a new source copy by replacing EMBEDDED_ARCHIVE. The default output is agent_memory_portable.py, relative to the working directory; overwriting the running script is rejected. pack includes pending and rejected records as well as approved ones. Copy the generated file to the receiving environment, inspect it, then run python agent_memory_portable.py restore followed by python agent_memory_portable.py list. Restore creates agent_memory_portable.sqlite3 beside that file. New additions or review decisions require a new pack; existing receiving records do not acquire changed statuses automatically.

Source: agent_memory.py: pack, restore, merge; earlier KNOWLEDGE_REVIEW.md

Tags: portability,workflow

## 9. Training export boundary

ID: `050e007e7945a8bb2f28` | Status: **approved** | Project: AgentMemort

export-training selects only approved records and writes JSONL with project, title, content, and source, defaulting to approved_experiences.jsonl. These are candidate records requiring any further dataset preparation. The script transfers textual knowledge; it neither transfers model parameters nor automatically fine-tunes an AI model.

Source: agent_memory.py docstring and export-training branch

Tags: export,limitations

## 10. Privacy and archive validation limitations

ID: `438fc7b3cbf2471bfbed` | Status: **approved** | Project: AgentMemort

Sanitization applies regex redaction to title and content for certain credential-like assignments and selected private-key blocks. Project, tags, and source are not sanitized. The rules are not a complete secret detector, so confidential content must be excluded before add or pack. Base64/zlib provide encoding and compression, not encryption. merge checks the exact field-name set and allowed status, but does not recompute IDs from imported content or fully validate field types. restore checks its 50 MB raw limit only after decompressing. These are source-review observations, not reproduced exploit tests.

Source: agent_memory.py: REDACTIONS, sanitize, merge, restore

Tags: privacy,limitations

## 11. Observed coding conventions and reusable patterns

ID: `4d3bd6d8d65aaba4c3bb` | Status: **approved** | Project: AgentMemort

The existing file uses snake_case functions, four-space indentation, pathlib paths, a main guard, and argparse subcommands with a required action. Small helpers separate persistence, sanitization, packing, and restoring. Parameterized SQL keeps values out of query text, and sqlite connection context managers handle transactions. Deterministic content IDs plus INSERT OR IGNORE support repeatable imports. These are observed conventions; no separate coding-style policy or explanation of the original architectural choices was found.

Source: agent_memory.py source inspection

Tags: conventions,patterns

## 12. Execution failure and working recovery path

ID: `05920cbdf85378abfbcc` | Status: **approved** | Project: AgentMemort

Earlier related chats and this conversation observed helper_unknown_error: setup refresh had errors before ordinary shell processes launched; the Node execution path also failed during sandbox setup. Authorized exec_command calls using sandbox_permissions=require_escalated succeeded, including Python 3.11.9 detection, script inspection, and CLI help. This is an observed execution workaround, not evidence that the sandbox was repaired. A failure before process creation should not be diagnosed as a Python syntax or script runtime failure. Use the available authorization mechanism when that execution mode is required.

Source: Current tool results and related Prepare portable agent knowledge chats, 2026-10-10

Tags: errors,experience

## 13. Historical validation and remaining work

ID: `22ae1885dbc92e52cd0d` | Status: **approved** | Project: AgentMemort

An earlier related handoff's verification.txt reports that seven pending entries packed/restored with all fields equal, a second restore imported zero records, and all entries remained pending. That log was read during this collection; it is historical evidence for the earlier seven-record snapshot, not a test of the present collection. The current workspace has no automated test suite. Next steps for this collection are human review, application of approved/rejected statuses, packing the current database, and checking the resulting archive count and restore behavior. Potential follow-up improvements identified by source inspection are explicit approved-only packing, stronger archive validation, broader privacy controls, and a documented test suite; these have not been implemented or requested as code changes.

Source: Earlier outputs/verification.txt and KNOWLEDGE_REVIEW.md; current source and user workflow

Tags: validation,pending
