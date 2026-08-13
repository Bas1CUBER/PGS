# Workflows

Status engines for every state-changing process in PGS. One shared engine implementation (`TransitionsWorkflowService`), configured per module. This file defines **who** may transition **what** — implementation must match it exactly, tested.

---

## 1. Engine design

- Statuses = PHP enums (single source, mirrored to TS).
- Transitions = explicit map: `from → to` + allowed actors + preconditions + side effects (notifications, audit, deadline checks).
- `TransitionsWorkflowService::canTransition(object, to, actor): bool` — the ONLY way to change status (no direct column writes).
- Every transition logs an audit entry and fires a domain event.
- Concurrency: transitions run inside `DB::transaction` with row locking (`lockForUpdate`) to prevent double-approval.

---

## 2. Deliverable workflow (`p_deliverables`)

```
                 ┌──────────┐
   upload ──────►│ uploaded │
                 └────┬─────┘
                      │ focal reviews
              ┌───────┴────────┐
        approve│               │return
              ▼                ▼
        ┌──────────┐      ┌──────────┐
        │ approved │      │ returned │
        └──────────┘      └────┬─────┘
              ▲                │ employee resubmits
              │                │
              └────────────────┘
```

| Transition | Actor | Precondition | Side effects |
|---|---|---|---|
| `— → uploaded` | Employee (owner) | Deadline enabled & not passed; file valid | Notification to focal(s); audit |
| `uploaded → approved` | Focal | Upload exists; not already approved | Notification to employee (`approved`); audit |
| `uploaded → returned` | Focal | Reason required (min 10 chars) | Notification to employee (`returned`); audit; return reason stored |
| `returned → uploaded` | Employee (owner) | New file provided | Reset to `uploaded`; notify focal; deadline re-check |

**Banned**: admin editing status directly (admin may only manage records/deletes with audit).

## 3. Upload-status workflow (module uploads: communication plan, strategy refresh, operations review)

```
pending ──approve──► approved          (focal/admin)
pending ──return───► returned          (focal/admin; reason required)
returned ──resubmit──► pending         (owner; deadline-checked)

Statuses also used (roadmap/comm-plan tables):
Not Accomplished/Started ──► Ongoing ──► Completed   (owner sets, focal approves)
Completed ──► Ongoing (reopen; audit + reason)
```

## 4. Review workflow (strategy review / operations review forms)

```
draft ──submit──► submitted ──approve──► approved
                    │  └────return────► draft(returned)
                    ▼
              (deadline lock: submitted forms locked at end_time;
               admin can unlock with audit + reason)
```

| Transition | Actor | Side effects |
|---|---|---|
| `draft → submitted` | Owner | Deadline check; notify focal; audit |
| `submitted → approved` | Focal/admin | Notify owner (`approved`); audit; lock form |
| `submitted → returned` | Focal/admin | Reason required; notify owner; form editable again |
| `submitted → draft` (reopen) | Admin only | Reason required; audit |

**PDF/export** is read-only: any status can export; exports are audited.

## 5. Communication plan template updates

- Template edits (`communication_plan_roadmap` rows): owner edits → `draft` → focal `approve` → visible to all. Type `edit` notifications to focals/admins on save (legacy behavior preserved).

## 6. Deadline enforcement

- Checked at **every** transition that creates content: upload, resubmit, review submit.
- `DeadlineControl` per role: `enabled && end_time < now()` → transition denied with clear message (UX §6).
- Admin bypass flag with audit (used for extensions; always logged).

## 7. Notification matrix (side effects)

| Event | Recipients | Type | Related object |
|---|---|---|---|
| Deliverable uploaded | Focals (role) | `upload` | deliverable |
| Deliverable approved | Uploader | `approved` | deliverable |
| Deliverable returned | Uploader | `returned` | deliverable |
| Template updated | Focals/admins | `edit` | template |
| Roadmap block changed | Admins | `edit` | roadmap item |
| Review submitted | Focals | `upload` | review |
| Review returned | Owner | `returned` | review |
| Deadline approaching | Role | `default` | deadline control |

Deduplication key: `(user_id, type, related_type, related_id)` within 60s — prevents double-fire during dual-run.

## 8. Testing requirements per workflow

For each engine config:
- [ ] Every allowed transition happy-path test
- [ ] Every denied transition test (wrong actor, wrong from-status)
- [ ] Precondition tests (deadline closed, reason missing, file invalid)
- [ ] Concurrency test: two simultaneous approvals → one wins
- [ ] Side-effect tests: notification created once, audit row written

## 9. Adding a new workflow

1. Define status enum + transition map here first (PR).
2. Implement `TransitionsWorkflowService` config + tests.
3. UI uses `StatusBadge`/`ConfirmDialog` only — never hand-rolled status logic in components.
