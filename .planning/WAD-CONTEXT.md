# WAD Coursework Context - TicketTrade

> Single-source reference for everything in `WAD_Batch26.md` that affects
> this build. Read this before doing anything else in Phase 3+.
> Also referenced from `.planning/PROJECT.md` (Team & Assignment Context)
> and `AGENTS.md` (WAD Assignment Context).

**Module:** Web and Mobile Application Development (WAD)
**Batch:** 26.1, NSBM Green University, Faculty of Computing
**Topic:** 4 of 5 - "NSBM Marketplace - Student Business and Service Platform"
**Brief file:** `WAD_Batch26.md` (read once before grading the build)
**Deadline (sec 7):** tentative 2026-09-02 (matches MVP date in PROJECT.md Constraints)

---

## 1. Topic 4 scope (sec 2)

### Admin features (the build's admin role must provide)
- Login
- Manage users
- Approve product listings
- Manage product categories
- View sales reports

### Student/User features (the build's student role must provide)
- Register and login
- Add products or services
- Edit and remove listings
- Browse products
- Search by category
- Simulate product purchases
- Manage personal listings

TicketTrade ships all of the above plus the "innovative features" listed in sec 6: gamification, digital ticket, dispute, review, and ranking systems. These are the project's material differentiators for the rubric.

## 2. Tech mandate (sec 3)

| Layer | Required | TicketTrade choice |
|-------|----------|--------------------|
| Frontend | HTML / CSS / JavaScript; Bootstrap or Material UI optional | Bootstrap 5.3 (CDN) |
| Backend | PHP (mandatory) | PHP 8+ |
| Database | MySQL (mandatory) | MySQL 8+ |
| Composer | Not mentioned; team constraint is sole dep `ramsey/uuid` | confirmed |

No other backend language may be substituted. No framework may replace the Layered Modular Monolith architecture. No ORM may replace PDO. No regex routing may replace the hand-rolled route list.

## 3. Functional mandate (sec 3)

Every project must include:

- [x] Separate Admin and Student/User interfaces (role-based routing + admin guard in `Support\Auth`)
- [x] Secure login (bcrypt >= 12, rate-limited 5/5min/IP, anti-enumeration per UX-DR-36)
- [x] Complete CRUD (listings CRUD in Phase 3; categories CRUD in Phase 8; users CRUD in Phase 8; reports CRUD in Phase 7)
- [x] Responsive and user-friendly interface (Bootstrap 5 grid, mobile-first breakpoints at 576/768/992/1200px)
- [x] Database integration using PHP and MySQL (`Support\Db` PDO wrapper, prepared statements everywhere)
- [x] Proper form validation (Service-layer `E_VALIDATION` codes, field-level UI)
- [x] Navigation menus and consistent page layouts (bottom nav + top bar, layout template)
- [x] Search and filtering functionality where applicable (FULLTEXT search + category tabs in Phase 3; admin filters in Phase 8)

The brief encourages **innovative features** beyond this list. The gamification/ticket/dispute systems are those innovations.

## 4. Evaluation rubric (sec 5)

| Weight | Line | Owner function | Phase evidence |
|--------|------|----------------|-----------------|
| 20% | UI Design and Frontend | Frontend Lead | Phase 1 (tokens + 3 mockups), Phase 3 (board + modal), Phase 6 (rank badges); DESIGN.md contrast ledger |
| 20% | Backend and Database Integration | Backend Lead + Database Engineer | AD-1..AD-20 compliance; phpcs PSR-12 clean; `php migrate.php` idempotent; EXPLAIN plans in report |
| 15% | Admin Panel Functionality | Backend Lead | Phase 3 (cron + queue), Phase 7 (reports), Phase 8 (full admin console + audit log) |
| 15% | Student/User Panel Functionality | Frontend Member | Phase 2 (auth + profile), Phase 3 (list), Phase 4 (buy+redeem), Phase 5 (rate), Phase 6 (earn) |
| 15% | Project Report, Screenshots & Drive Links | QA/Docs Lead | `.planning/phases/*/SUMMARY.md` with screenshots; `docs/` directory; Drive folder |
| 15% | Video Demonstration & Teamwork | All 6 (each describes contribution) | Single screen-recorded video; per-member narration |

**Rubric-to-phase traceability:** every phase SUMMARY.md MUST record which rubric line it produces evidence for. The QA/Docs Lead owns the roll-up; the Project Report collects the links.

## 5. Deliverables checklist (sec 4)

### 5.1 Project Report (PDF or Word) - REQUIRED
Must include:
- [ ] Project title (TicketTrade - NSBM Marketplace)
- [ ] Team leader's name + contact (single submitter per sec 4.4)
- [ ] Names + roles of all 6 group members (from `config/team.php`)
- [ ] Introduction
- [ ] Project objectives
- [ ] Description of the system
- [ ] Screenshots of key features (admin + student interfaces)
- [ ] Google Drive link to source code (public access)
- [ ] Google Drive link to demo video

### 5.2 Project Source Code - REQUIRED
- [ ] Complete project folder uploaded to Google Drive
- [ ] Public access enabled
- [ ] Drive link embedded in report
- [ ] Drive folder also contains: `WAD_Batch26.md`, `AGENTS.md`, `.planning/PROJECT.md`, `.planning/REQUIREMENTS.md`, `DESIGN.md`, `EXPERIENCE.md`, `ARCHITECTURE-SPINE.md`, `.planning/ROADMAP.md`, `prd.md`, `epics.md`, `composer.json`, `composer.lock`, `README.md`

### 5.3 Video Demonstration - REQUIRED
One screen-recorded video covering:
- [ ] Login functionality (Phase 2)
- [ ] Admin panel (Phase 8 - recordings from Phase 3 / Phase 7 are interim)
- [ ] Student/User panel (Phases 2-6)
- [ ] CRUD operations (Phase 3 listings; Phase 8 categories)
- [ ] Database functionality (brief demo of phpMyAdmin / SQL)
- [ ] Main features (digital ticket, gamification, dispute, reviews)
- [ ] Brief explanation from each group member describing their contribution (~30s each)

### 5.4 Submission - REQUIRED
- [ ] Only the team leader (or one designated member) submits the final report via LMS
- [ ] All Google Drive links verified accessible before submission
- [ ] Deadline: tentative 2026-09-02

## 6. Important guidelines (sec 6)

- Select only ONE topic - team selected Topic 4 (this PRD).
- Must use PHP and MySQL - confirmed.
- Properly designed database - confirmed (utf8mb4, InnoDB, indexes per Phase 1+ migrations).
- CRUD throughout - confirmed.
- Professional, responsive, easy-to-use UI - DESIGN.md contrast ledger + Bootstrap 5.
- Creative and innovative features - gamification, digital ticket, dispute, reviews, leaderboards.
- Teamwork, coding best practices, proper documentation - phpcs PSR-12, AD-1..AD-20, this doc.
- Original work only - no plagiarism; all code is the team's own.

---

## 7. Where the rubric evidence lives in the repo

| Rubric line | Evidence file(s) |
|-------------|------------------|
| UI Design | `DESIGN.md` contrast ledger; `public/assets/css/tickettrade.*.css`; `public/mockups/*.html` |
| Backend + DB | `ARCHITECTURE-SPINE.md` AD-1..AD-20; `composer.json` (sole dep); `phpcs.xml`; `migrations/*.sql` |
| Admin Panel | `src/Admin/*` (Phase 8); `src/Listing/Action/ListingAutoApproveAction.php` (Phase 3 interim) |
| Student Panel | `src/Auth/*`, `src/User/*` (Phase 2); `src/Listing/*` (Phase 3); `src/Ticket/*` (Phase 4); `src/Points/*` (Phase 6) |
| Report | `WAD-CONTEXT.md` (this file); `WAD_Batch26.md`; `prd.md`; `epics.md`; `.planning/PROJECT.md`; `.planning/ROADMAP.md` |
| Video | Drive link (recorded separately); `bin/record-demo.sh` when present |

---

*Last updated: 2026-09-01 after Phase 3 context gathering*
*Owner: QA/Docs Lead (Role #6) - confirm at submission time*
