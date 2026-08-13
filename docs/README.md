# PGS Documentation

> Navigation hub for the Performance Governance System (TRC DOH) modernization documentation set.

---

## 1. How the docs fit together

```
                    ┌──────────────────────┐
                    │   README (you are    │
                    │      here) — index   │
                    └──────────┬───────────┘
                               │
        ┌──────────────────────┼──────────────────────┐
        │                      │                      │
  ┌─────▼──────┐         ┌─────▼─────┐         ┌──────▼──────┐
  │  Strategy   │         │  Phases   │         │  Standards  │
  │  Roadmap    │         │ (execution│         │  (how to    │
  │  (what/why) │         │  plans)   │         │   build)    │
  └─────┬──────┘         └─────┬─────┘         └──────┬──────┘
        │                      │                      │
  Roadmap.md              Phase_1.md..Phase_9.md  TechStack / Architecture /
  TechStack.md                                    Backend / Frontend / Design /
  Skills.md / Glossary.md                         Security / UX / Consistency /
                                                  Uploads / Workflows / Reports
        ┌──────────────────────┼──────────────────────┐
        │                      │                      │
  ┌─────▼──────┐         ┌─────▼─────┐         ┌──────▼──────┐
  │    Data    │         │  Process  │         │   Run       │
  │  (schemas) │         │  (quality)│         │  (ops)      │
  └─────┬──────┘         └─────┬─────┘         └──────┬──────┘
        │                      │                      │
  DataModel.md /              Testing.md /           CI-CD.md /
  Migration.md /              Consistency.md         Operations.md /
  Env.md                                             ReleaseProcess.md
```

---

## 2. Full file index

### Strategy (why & what)
| File | Content |
|---|---|
| [Roadmap.md](./Roadmap.md) | Master plan: vision, 9 phases, DoD, KPIs, risks |
| [TechStack.md](./TechStack.md) | Target vs legacy stack, versions, rejected alternatives |
| [Skills.md](./Skills.md) | Team + agent skill matrix, learning path |
| [Glossary.md](./Glossary.md) | Domain terminology (PGS-PA, OPCR, focal, cascading…) |

### Execution plans
| File | Content |
|---|---|
| [Phase_1.md](./Phase_1.md) | Foundation & quality gates |
| [Phase_2.md](./Phase_2.md) | Database & migrations |
| [Phase_3.md](./Phase_3.md) | Auth, RBAC & core services |
| [Phase_4.md](./Phase_4.md) | Frontend foundation |
| [Phase_5.md](./Phase_5.md) | Dashboards & user administration |
| [Phase_6.md](./Phase_6.md) | Roadmaps & deliverables (core modules) |
| [Phase_7.md](./Phase_7.md) | Module ports (annexes & sectors) |
| [Phase_8.md](./Phase_8.md) | Hardening (security/perf/a11y/load) |
| [Phase_9.md](./Phase_9.md) | Cutover & decommission |

### Standards (how to build)
| File | Content |
|---|---|
| [Architecture.md](./Architecture.md) | Modules, request lifecycle, topology, ADR log |
| [Backend.md](./Backend.md) | Controllers/services/requests/models, DB rules |
| [Frontend.md](./Frontend.md) | React + TS + Inertia + shadcn conventions |
| [Design.md](./Design.md) | Tokens, typography (Inter), print rules |
| [Security.md](./Security.md) | Threat model, auth, CSP, uploads, secrets |
| [UX.md](./UX.md) | Personas, states, forms, notifications, a11y |
| [Consistency.md](./Consistency.md) | Enforced gates, naming, git/PR rules |
| [Uploads.md](./Uploads.md) | Upload pipeline spec (whitelist, scan, storage) |
| [Workflows.md](./Workflows.md) | Status engines per module + transition matrix |
| [Reports.md](./Reports.md) | Exports/PDF: annexes, OPCR, review generation |
| [ADRs.md](./ADRs.md) | Architecture decision records (chronological) |

### Data & process
| File | Content |
|---|---|
| [DataModel.md](./DataModel.md) | Table inventory, ERD, indexes (Phase 2 output) |
| [Migration.md](./Migration.md) | Legacy→new data migration, checksums, cutover |
| [Env.md](./Env.md) | `.env` keys, Docker services, environments |
| [Testing.md](./Testing.md) | Test pyramid, coverage, CI matrix, parity suites |
| [CI-CD.md](./CI-CD.md) | Pipelines, deploy/rollback, branch protection |

### Operations
| File | Content |
|---|---|
| [Operations.md](./Operations.md) | Runbooks: backup/restore, on-call, triage |
| [ReleaseProcess.md](./ReleaseProcess.md) | Versioning, release choreography |
| [API.md](./API.md) | Route/API surface for integrations |

---

## 3. Reading order for new team members

1. [Roadmap.md](./Roadmap.md) — context
2. [Glossary.md](./Glossary.md) — vocabulary
3. [Architecture.md](./Architecture.md) — shape
4. [Consistency.md](./Consistency.md) — rules
5. Current phase file — what to do now

---

## 4. Doc maintenance rules

- Every PR that changes behavior updates the relevant doc in the same PR (Consistency §7).
- Phase files are living: check off tasks as they land; exit criteria must be met before starting the next phase.
- `DataModel.md` and `Workflows.md` must be regenerated from migrations/code during Phase 2 and 6 (verify against live schema).
- ADRs are append-only.
