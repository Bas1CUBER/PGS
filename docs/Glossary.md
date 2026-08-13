# Glossary

Domain vocabulary for the PGS (Performance Governance System) at TRC DOH. Definitions here are the shared language between business users (focal/admins) and engineers — when a term is ambiguous, this file wins.

---

## 1. Governance framework terms

| Term | Definition |
|---|---|
| **PGS** | Performance Governance System — the institutional governance framework TRC DOH implements; also the name of this application. |
| **PGS-PA** | PGS Pathway Assessment — the staged assessment of the institution's governance maturity (used by the `about_pgs_pathway` content). |
| **OPCR** | Office Performance Commitment and Review Form — the annual performance commitment of an office, with targets and measures. |
| **Strategy Map** | Visual one-page articulation of the institution's strategic objectives (financial, stakeholder, process, learning & growth perspectives). |
| **Roadmap** | A thematic development plan (e.g., "Reduced Adverse Events") composed of titled sections (`roadmap_titles`) and items (`roadmap_items`). |
| **Roadmap block** | A content block within a roadmap item's detail page (text, table, stat card, chart…). Legacy stores JSON in `roadmap_page_blocks.content`. |
| **Cascading** | The process of aligning lower-level unit targets with institutional roadmaps (`cascading_activities`). |
| **Annex (annex B/D/E/H/J/K)** | Standardized document templates of the PGS-PA evidence toolkit; each annex covers a governance component. |
| **Sector roadmap modules** | Thematic roadmaps grouped by pillar: `culture`, `collab` (collaboration), `training`, `technology`, `research`, `revenue`, `resilience`. |
| **Strategy refresh / review / operations review** | Periodic reassessments: refresh (roadmap update), review (institutional assessment forms), operations review (operational performance). |
| **Communication plan** | The plan for communicating governance initiatives (`communication_plan_roadmap` + uploads). |
| **Deadline control** | System-managed submission windows per role (`deadline_controls`); enforcement blocks uploads/submissions after `end_time`. |

## 2. Roles

| Role | Definition |
|---|---|
| **Admin** | System owner: users, backups, deadlines, audit, all modules. |
| **Focal** | Unit/area lead: reviews and approves submissions (deliverables, reviews), manages notices. |
| **Employee** | Staff: submits deliverables/uploads, tracks status. |
| **Guest** | Unauthenticated: only login page. |

## 3. Domain objects

| Term | Definition |
|---|---|
| **Deliverable** | A submission item tied to a roadmap/office target (`p_deliverables`), with file upload and status (uploaded → approved / returned). |
| **Upload record** | A file attached to a deliverable or module table (`*_uploads` patterns); tracked with original name, size, MIME, uploader. |
| **Notice** | Announcement shown on the landing page (`notice.php`); CRUD by admins/focals. |
| **Resource** | Shared reference file (`resources_uploads`) with viewer page. |
| **Impact scorecard** | Indicator tracking table (`impact_scorecard_*`): measures × years × values, with baseline (bl). |
| **User page access** | Per-user matrix of allowed modules (`user_page_access` columns: roadmaps, scorecard, performance_assessment, cascading, governance). |
| **Notification** | In-app event record (type: upload, approved, returned, edit) for a user with related object reference. |
| **Audit log** | Append-only record of sensitive actions (actor, action, before/after). |

## 4. Status vocabulary (single source of truth)

- **Deliverable**: `uploaded → approved` | `uploaded → returned` | `returned → uploaded` (resubmission)
- **Upload status**: `pending → approved` | `pending → returned` (focal review); some modules use `Not Accomplished/Started`, `Ongoing`, `Completed`
- **Reviews**: `draft → submitted → approved` | `submitted → returned`
- **Deadline**: `enabled / disabled`, each with `end_time` and message

Colors per status in [Design.md](./Design.md) §2; transition ownership in [Workflows.md](./Workflows.md).

## 5. Technical terms used in this codebase

| Term | Meaning here |
|---|---|
| **Legacy app / legacy tree** | The current procedural PHP codebase (root-level `.php` files) being replaced. |
| **New app** | The Laravel 12 + React application built through this roadmap. |
| **Dual-run** | Period (Phases 5–8) when both apps serve data from the same DB for parity verification. |
| **Grep-gate** | CI check that fails if banned patterns (`mysqli`, raw SQL interpolation, `CREATE TABLE` in app code) appear. |
| **Parity script** | Test that compares legacy vs new output (row counts, dashboard KPIs, screenshots). |
| **Inertia page** | A server-rendered page whose React component mounts without full reload. |

## 6. Additions

When a new module or term is introduced, add it here in the same PR (per README §4).
