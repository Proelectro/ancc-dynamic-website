# ANCC Website — Project Plan

**Algorithm and Coding Club (ANCC), IIT Delhi**
Backend: PHP 8.4 (originally planned as FastAPI) · Frontend: React · Database: MySQL 8

> **Decision update (15 Nov 2026): backend is PHP + MySQL.** The site is hosted on the IITD CSC server
> (shared Apache with PHP, MySQL 8, no sudo, no long-running processes). FastAPI could only run there as a
> CGI script (~0.7 s per API call); PHP runs inside Apache (~7 ms). The features, models, API endpoints,
> roles and frontend in this plan are unchanged. Only the backend technology differs:
>
> | Planned (Python) | Now (PHP) |
> |---|---|
> | FastAPI routers | `backend/src/Controllers/*` + routes in `App.php` |
> | Pydantic schemas / validation | `Support/Validate.php` + per-controller checks; JSON shapes in `Users::out()` etc. |
> | SQLAlchemy models | SQL tables + small repository classes (`Users.php`), PDO |
> | Alembic | numbered `backend/migrations/NNN_*.sql` + `bin/migrate.php` |
> | PostgreSQL (JSONB, UUID) | MySQL 8 (JSON, CHAR(36) UUIDs, DATETIME(6) in UTC) |
> | Authlib / PyJWT | hand-written IITD OAuth (curl) and HS256 JWT |
> | pytest | PHPUnit (in-process API tests) |
> | openapi-typescript generated types | hand-written `frontend/src/api/types.ts` |
> | APScheduler background jobs | none on shared hosting: do scheduled work lazily on request, via admin buttons, or cron if CSC allows |
> | systemd + Nginx deploy | `deploy/iitd/deploy.sh` (rsync to Apache; preview at `/new/` behind a password) |
>
> Section 2 (tech stack) and section 3.3 (backend file structure) below describe the original Python
> design; the current layout is in the repository README.

> **Scope update (24–25 Nov 2026):** event **registration** (and its form builder) and **ECA/CAIC** records are handled
> elsewhere, so this site doesn't build them. Sections 1.3 (registration parts), 1.4 (ECA/CAIC), 1.7 (form builder,
> registration settings, ECA/CAIC settings) and the related models, endpoints and pages below are kept only as history.
> What remains: an **event highlights** page (write-up, results, Drive photos, link to external registration), prizes and
> reimbursements (prizes added by finance), ICPC, About + sponsor, and a new **alumni outreach** page. The current
> roadmap is GitHub issue #15.

---

## 0. Overview

The ANCC website has three jobs:

1. **Public face of the club.** Upcoming and past events, results, photos, the team, and our sponsor (Jump Trading).
2. **Member portal.** Every IITD student logs in with their IITD account and sees their profile, ECA records, CAIC score, prizes and reimbursements, event registrations, and ICPC team status.
3. **Admin tooling.** Club coordinators create events and forms, add ECA records and CAIC scores, track prizes and reimbursements, and run the ICPC team pipeline all the way to registration on icpc.global.

### Roles

A user can hold more than one role. For example, a coordinator can also be the ICPC admin.

| Role | Who | Can do |
|---|---|---|
| `member` | Any logged-in IITD student (default) | View events, register, manage own profile, form ICPC teams, submit reimbursement claims |
| `coordinator` | Club coordinators / event heads | Create/edit events, build registration forms, view/export registrations, publish results and photos, add ECA records and CAIC scores |
| `icpc_admin` | ICPC in-charge(s) | Manage ICPC seasons, verify teams, use the registration worksheet, register teams on icpc.global (with the coach), update team status/progress |
| `finance` | Treasurer / finance head | Update prize and reimbursement status |
| `super_admin` | Overall coordinators / secretary | Everything above, plus assigning roles, editing About Us content, and viewing the audit log |

The coach (the ANCC faculty in-charge) does not need a role on the site. The ICPC admin handles everything on the site side.

---

## 1. Features

### 1.1 User Authentication

**Goal:** Secure login with IITD credentials only. No passwords are stored by us.

#### Production: IITD OAuth (Kerberos-backed)

IIT Delhi provides an OAuth 2.0 server (`oauth2.iitd.ac.in`; docs at https://oauth2.iitd.ac.in/client_config.php). The club must register an OAuth client with CSC/IITD to get a `client_id` and `client_secret`.

**Flow:**

1. The user clicks **"Login with IITD"** on the frontend. The browser goes to `GET /api/v1/auth/login`.
2. The backend generates a random `state`, stores it in a short-lived signed cookie, and redirects (302) to the IITD authorize URL with `client_id`, `redirect_uri`, `response_type=code` and `state`.
3. The user logs in on the IITD page. IITD redirects to `GET /api/v1/auth/callback?code=...&state=...`.
4. The backend:
   - validates `state` (CSRF protection),
   - exchanges `code` for an access token at the IITD token endpoint (server-to-server, with `client_secret`),
   - calls the IITD resource endpoint to get the user's identity (kerberos ID, name, email, department/category),
   - **upserts** the `User` row by kerberos ID: it creates the user on first login (this is the "sign-up") and refreshes their name/email/department on later logins,
   - issues **our own JWTs**: an access token (about 15 min) and a refresh token (about 7 days), both as `HttpOnly`, `Secure`, `SameSite=Lax` cookies,
   - redirects to the frontend `/auth/callback`, which then routes to `/dashboard`. First-time users go to `/profile?onboarding=1` to fill in phone and hostel.
5. The frontend never sees IITD tokens. It calls `GET /api/v1/auth/me` to get the current user and roles.
6. `POST /api/v1/auth/refresh` rotates the access token. `POST /api/v1/auth/logout` clears the cookies and revokes the refresh token.

> Sign-up = first successful IITD login. There is no separate registration form. Contact info and hostel are collected during onboarding.

#### Development: Dev Login

- Controlled by the env var `AUTH_MODE=dev|iitd`.
- When `AUTH_MODE=dev`, the backend mounts `POST /api/v1/auth/dev-login` with body `{ kerberos_id, name?, roles? }`. It creates or fetches that user and issues the same JWT cookies as production, so the rest of the app behaves the same.
- The frontend calls `GET /api/v1/auth/config` and gets back `{ mode: "dev" | "iitd" }`. In dev mode, the login page shows a form with a kerberos-ID field, a role picker, and quick buttons such as "Login as member", "Login as super_admin" and "Login as icpc_admin".
- **Safety guards:**
  - The dev-login router is **not registered at all** when `AUTH_MODE=iitd`.
  - The app **refuses to start** if `ENV=production` and `AUTH_MODE=dev`.
  - A seed script (`scripts/seed.py`) creates sample users, events and teams for local work.

#### Profile management

Users can edit: phone, alternate email, hostel, Codeforces handle, CodeChef handle and an avatar.

Name, kerberos ID, department and IITD email come from IITD and are **read-only**.

---

### 1.2 Dashboard (`/dashboard`)

A personal page that pulls together everything about the logged-in user. It is backed by one aggregate endpoint, `GET /api/v1/dashboard`, so the page loads in a single request, with detail endpoints for "view all" pages.

Cards, from top to bottom:

| Card / section | What it shows | Source |
|---|---|---|
| **Profile** (top) | Name, kerberos ID, department, contact info (phone, emails), hostel, Codeforces/CodeChef handles. An **Edit** link goes to `/profile`. A warning banner shows if phone or hostel is missing | `User` |
| **ECA records** | A **bullet list of achievements and participation**, not a number. For example: "Secured 1st position in Code Decode 2026", "Attended the Graph Algorithms workshop", "Organising team, ICPC De-Tryst". Grouped by academic year and semester, each with its date and a link to the event. Shows the count for the current semester | `EcaRecord` |
| **CAIC score** | The student's **numeric CAIC Trophy score**: the points they earned for their hostel in ANCC events this year. A breakdown table shows event, position or participation, points and the hostel at the time, plus the ANCC total for their hostel and a link to the official CAIC Trophy standings | `CaicScoreEntry` |
| **Prizes & reimbursements** | One combined list. Each item shows type (Prize / Reimbursement), event, amount, and a **4-step tracker** with the current step highlighted and what the student needs to do (see 1.8):<br>1. **Bank details required**: "Mail your passbook / cancelled cheque to ancclub@iitd.ac.in", with a mailto button and a prefilled subject<br>2. **Awaiting professor approval**: the faculty in-charge approves and sends it to the accounts section<br>3. **Accounts section processing**: "Expect about 21 days", with an expected-by date<br>4. **Done**<br>A **"New reimbursement claim"** button opens the claim form | `Payout` |
| **ICPC team** | If in a team: team name, members with readiness (e.g., "2/3 ready"), current status shown as a timeline, latest progress (e.g., "Online prelims: Rank 42"), and an **"Action needed"** list (e.g., "Fill your ICPC details", "Confirm your icpc.global account", "Fix: email not found on icpc.global") linking to `/icpc/details` or the guide. A **"Leave team"** button is available until the team deadline. After the deadline the team is **frozen**: no leaving, invites or roster changes, and a "Team frozen on <date>" note appears. If invites are pending: accept/decline buttons. If no team and the season is open: a **"Create a team"** call to action and a link to the **ICPC registration guide** | `ICPCTeam`, `ICPCInvite`, `ICPCContestantDetails`, `ICPCIssue`, `ICPCProgress` |
| **My events** | Upcoming events the user registered for (with countdown and joining links) and past participation (with results) | `EventRegistration` |

#### How ECA and CAIC are stored

ECA and CAIC are **different things** and are stored separately.

- **ECA** is a list of **records**, one bullet point per achievement or activity: "Got 1st in a contest", "Attended a workshop", "Volunteered at an event". There are no numbers. A record has a title, a kind (winner / participation / attendance / organiser / other), a date, an optional linked event, and the academic year and semester. The dashboard shows the list; nothing is summed.
- **CAIC** is the club's contribution to the **CAIC Trophy**, IIT Delhi's year-long inter-hostel competition, where hostels earn points from club events based on how their students perform. Each `CaicScoreEntry` is one **numeric** score for one student in one event: the points, why they got them (position or participation), and the **hostel they represented at that time**, stored as a snapshot so a later hostel change doesn't move old points. A student's CAIC score is the `SUM()` of their entries for the academic year. Hostel totals for ANCC events are the same sum grouped by hostel, and they can be exported for the CAIC team.
- **Fixing mistakes:** records and entries are never deleted. An admin **revokes** one with a reason. It disappears from the student's view and totals but stays in the database, and the action is written to the audit log.

---

### 1.3 Event Management (public + member)

#### Events list (`/events`)

- Tabs: **Upcoming**, **Ongoing**, **Past**.
- Filters: type (Contest, Workshop, Talk, Hackathon, Lecture series, Other), mode (Online / Offline / Hybrid), search by title.
- Each event card shows the banner, title, date and time, venue/mode, type badge, registration status ("Registration open, closes in 2d", "Closed", "Full"), and a **Register** button.

#### Event detail (`/events/:slug`)

The page changes depending on where the event is in its lifecycle.

**Before and during the event:**

- Title, banner, rich description (Markdown), date and time (start/end, shown in IST), venue or online mode.
- Registration window, with a countdown to its close.
- **Links**: contest link (e.g., a Codeforces gym/Vjudge/HackerRank URL), meeting link, problem statements and rules. Admins can mark a link **"visible only to registered users"** and/or **"visible after event start"**. This lets us hide a contest link until the contest begins.
- **Registration**:
  - Not logged in → a "Login to register" button.
  - Open → an inline **dynamic registration form** built from the fields the admin defined (see 1.7).
  - Team events → the form has a team-name field plus teammates' kerberos IDs, checked against registered users.
  - Already registered → "You're registered ✓", the user's submitted answers, and a **Cancel registration** button (allowed until the registration deadline).
  - Full → optional **waitlist**. When someone cancels, the first waitlisted user is promoted automatically and notified.
  - Closed → a "Registration closed" message.

**After the event (status = `completed`):**

- **Winners / results** table: position, name or team, prize amount, with a podium highlight for the top 3.
- **Photo gallery**, taken from the event's album in the **ANCC Google Drive**:
  - The admin pastes the Drive folder link into the event.
  - The backend lists the images in that folder through the Google Drive API (using a service account the folder is shared with) and caches their file IDs and thumbnails.
  - The page shows a thumbnail grid with a lightbox, plus an **"Open full album in Drive"** button.
  - Admins can re-sync after adding photos, and can hide individual photos or add captions.
- **Summary / write-up**: an optional Markdown recap of the event.
- **Resources**: slides, editorial, problem set, recording link.
- Participation stats (number of participants), if the admin chooses to show them.

#### How registration works (backend)

`POST /api/v1/events/{id}/register` with `{ responses: { field_id: value, ... }, team?: {...} }`.

The backend checks:

- the event is published and registration is open, based on `registration_opens_at` ≤ now ≤ `registration_closes_at`,
- the user is not already registered (unique constraint on `(event_id, user_id)`),
- every required field is present and matches its field type, using a Pydantic model built dynamically from `EventFormField`,
- capacity: if `registered_count >= max_participants`, the registration is waitlisted (or rejected if there is no waitlist).

The response answers are stored in `EventRegistration.responses` (JSONB). A confirmation email and an in-app notification are sent. Coordinators can view registrations, mark attendance, and **export to CSV**.

---

### 1.4 Admin Page — Users, ECA & CAIC (`/admin/users`)

**Users table:** server-side paginated, searchable (by name or kerberos ID) and filterable (department, year, hostel, role).

**User detail page (`/admin/users/:id`):**

- Full profile (read-only, plus role assignment for `super_admin`, and **deactivate** to ban a user; their data is kept, not deleted).
- **ECA records**: the student's list of records. An **"Add ECA record"** dialog takes a title (e.g., "Secured 2nd position in Code Mod"), kind, date, optional linked event, and academic year/semester. Each record can be **revoked** with a reason.
- **CAIC score**: the total for the year plus every entry. An **"Add CAIC entry"** dialog takes the event, basis (position / participation / other), position, and points. The hostel is filled in from the profile and can be edited. Each entry can be **revoked** with a reason.
- Their prizes and reimbursements, registrations, and ICPC team, each with a quick link to its admin page.

**Bulk operations.** Both work the same way for ECA and CAIC.

- **From an event** (the event's Registrations or Results tab): select participants or winners, then choose:
  - **Add ECA records**: the title is prefilled from a template, e.g., "Participated in {event}" or "Secured {position} position in {event}", and can be edited before saving.
  - **Add CAIC entries**: the points come from the event's **CAIC points table** (e.g., 1st / 2nd / 3rd / participation) and can be edited before saving.
- **CSV upload** (`/admin/records/bulk`): pick **ECA** or **CAIC**, then upload a CSV with the same fields the dialogs use:
  - ECA: `kerberos_id, title, kind, event_slug (optional), date`
  - CAIC: `kerberos_id, event_slug, basis, position (optional), points`

  The page previews the rows, flags unknown kerberos IDs and events, and then commits.

**Other status updates:** prize and reimbursement status (see 1.8), ICPC status (see 1.5).

**Audit log:** every admin write (ECA/CAIC records, statuses, roles, event edits) is recorded in `AuditLog` with the actor, action, entity, and before/after JSON. It is viewable at `/admin/audit` (super_admin only).

---

### 1.5 ICPC (member pages + admin pages)

#### How ICPC registration actually works (and why it goes wrong)

The ANCC site is **not** where teams are officially registered. Official registration happens on **icpc.global**, where teams are created under the **coach**, the ANCC faculty in-charge, who must be faculty at the same institute. The real process is:

1. Each student creates **their own icpc.global account** and completes their profile.
2. ANCC collects every team's details.
3. The coach (or the ICPC admin) logs in to icpc.global, creates each team, and adds the 3 members by looking them up (usually by email). If a member isn't found, a new person record has to be created for them.
4. ICPC / the regional director checks eligibility and accepts the team. A team counts as eligible only once everyone is **fully registered** in the ICPC system.
5. Teams take part in the online prelims and then the onsite regionals.

**Problems we see every year**, which this part of the site is designed to prevent:

| Problem | What happens | How the site prevents it |
|---|---|---|
| Student never created an icpc.global account | The team can't be created and is stuck | Guide page plus a required checklist item. The team can't reach "Ready" without it |
| Student gave us a different email from their icpc.global account | The search finds nothing, or a **duplicate** person gets created | "icpc.global email" field, typed twice to confirm, with a clear note that it must be the exact email on their icpc.global account |
| Name entered wrongly: first and last swapped, nickname, initials, or not matching the ID | Team fails the eligibility check, or there's trouble at the onsite ID check | Separate "First name as on ID" and "Last name as on ID" fields, a live preview of how it will appear, and a warning if it differs from the IITD name |
| Profile incomplete on icpc.global (degree info, T-shirt, photo, etc.) | Team shows as pending or incomplete, and one member blocks the whole team | Profile-completion checklist, optional screenshot upload, and an "issues" loop where admins flag what's missing |
| Wrong institution selected, or a new duplicate institution created | Members appear to be from different schools | The guide shows the exact institution name to pick |
| Student created a team themselves on icpc.global | Duplicate or invalid team | The guide says clearly that only the coach creates teams |
| Ineligible member (studies start year, too many past regionals or World Finals) | Rejected late | Automatic eligibility check from the season's rules when details are entered |

> ICPC rules, dates, fees and regional sites change every season. Eligibility rules are stored **per season** and edited by the ICPC admin. Regional sites are **hard-coded for now** (see Team formation, step 5). For reference, the 2025–26 rules said a student is eligible if they *first began post-secondary studies in 2021 or later* **or** were *born in 2005 or later*, and they are not eligible after competing in **5 regional contest years** or **2 World Finals**. Teams have exactly 3 contestants and 1 coach, all from the same institution.

#### Full flow

```
Captain creates team ──► invites 2 ──► both accept ──► FORMED
   (until the team deadline, any member can leave → team goes back to PENDING_INVITES)
                                                        │
     Each member: create icpc.global account, fill "My ICPC details" on ANCC,
     tick checklist (account ✓, email ✓, profile complete ✓, photo ✓, degree info ✓)
                                                        │
                       all 3 members complete ──► DETAILS_COMPLETE (automatic)
                                                        │
              ── team deadline passes: roster FROZEN (no leave / invite / changes) ──
                                                        │
          ICPC admin reviews + eligibility ──► VERIFIED (details lock)
                                                        │
 Coach / ICPC admin creates team on icpc.global, enters ICPC team ID ──► REGISTERED
                                                        │
       ICPC / regional director accepts the team ──► ACCEPTED
                                                        │
       Progress: Online prelims → Regional(s) → Asia West Finals → World Finals

  At any stage from VERIFIED onwards, the ICPC admin can raise an ISSUE
  ("name doesn't match", "email not found on icpc.global", ...). The team then
  shows a "Needs fix" badge, the member is notified, fixes it, and marks it fixed.
  The admin resolves the issue.
```

#### Team formation (members, `/icpc`)

1. **Create team.** While team formation is open, any member with no team in that season can create one with a team name. They become the **captain**. Status: `PENDING_INVITES`.
2. **Invite.** The captain enters a teammate's kerberos ID. The backend checks that the invitee:
   - exists. If they haven't logged in yet, the invite is stored by kerberos ID and attached when they first log in,
   - is not already in a team this season,
   - is not already invited to this team,
   - would not push the team past **3 members** (counting pending invites).

   An `ICPCInvite` is created (status `pending`, expiring after a configurable 7 days), and an email plus in-app notification is sent.
3. **Respond.** The invitee sees the invite on the dashboard and on `/icpc`, and can **Accept** or **Decline**.
   - **Accept** → they become an `ICPCTeamMember`, and their other pending invites for the season are cancelled automatically.
   - **Decline** → the captain is notified and can invite someone else.
   - The captain can **cancel** a pending invite.
4. **Formed.** Once 3 members have accepted, the status becomes `FORMED`.
   - **Leaving (until the team deadline):** any member can leave with **"Leave team"** on the dashboard or `/icpc`. The team goes back to `PENDING_INVITES` and the captain can invite someone new. If the captain leaves, captaincy passes to the member who joined earliest. If the last member leaves, the team is dissolved.
   - **After the team deadline** (`team_formation_closes_at`): every team is **frozen**. No creating, inviting, accepting or leaving. Only the ICPC admin can change a roster, and every change is audit-logged. Teams that are still incomplete at the deadline are flagged for the admin.
5. **Regional preferences.** The captain picks **2 regional sites** in order of preference. The site list is **hard-coded** in one backend constants file (`app/core/icpc_sites.py`: code, name, host, dates, official URL) and sent to the frontend through the API. When the sites change for a new season, we update that file in code. It can move into the database later if needed.

#### ICPC Registration Guide page (`/icpc/guide`)

A dedicated, public page that is also linked from the dashboard, from `/icpc`, and from every reminder email. Its content is **Markdown stored per season**, so the ICPC admin can update it each year without a code change. The default structure:

**Step 0. Check your eligibility.** A short rule summary for the current season (studies start year, birth year, past regionals and World Finals limits), a link to the official ICPC rules and eligibility page, and a small "Am I eligible?" helper that applies the same checks the backend uses. The helper asks for the year you started college, your birth year and your past participation, and it doesn't save any of them.

**Step 1. Create your icpc.global account.**
- You can use **any email** you can access: IITD or personal.
- Whatever email you use, it must be **exactly the same** email you enter in "My ICPC details" on the ANCC site. That is the email used to find you on icpc.global.
- If you already have an icpc.global account from an earlier year, **use that one**. Never create a second account.
- Link: icpc.global sign-up / login.

**Step 2. Complete your icpc.global profile.** Every required field must be filled (for example date of birth, sex, T-shirt size, photo and education). One incomplete member blocks the whole team.

**Step 3. Join or form a team on the ANCC site.** Link to `/icpc`.

**Step 4. Fill "My ICPC details" on the ANCC site.** These must be **identical** to your icpc.global profile.

**Step 5. Tick the checklist.** Optionally upload a screenshot of your completed icpc.global profile.

**Step 6. What happens next.** ANCC verifies your team, and the team is registered on icpc.global. You might get an email from ICPC. Log in to icpc.global and check that your team shows you as complete. If the ANCC site shows a **"Needs fix"** issue, fix it on icpc.global and click **"Mark fixed"**.

**Step 7. Prelims and regionals.** One card per hard-coded regional site, showing dates, the regional's official page, and fee information if any.

**Common mistakes:** a short checklist of the problems in the table above.

**Help:** contact for the ICPC admin (email/WhatsApp) and an FAQ.

**Useful links** (configured per season): icpc.global home and login, ICPC regional rules, the eligibility rules, the Asia West / India regional pages, past prelims problem sets, and the ANCC ICPC prep resources.

#### My ICPC details (`/icpc/details`)

This form stores the information needed to register the team on icpc.global, for one user in one season (`ICPCContestantDetails`):

| Field | Notes / validation |
|---|---|
| First name as on ID, Last name as on ID | Latin letters, spaces, `.`, `'` and `-` only. Shows a live preview: **"LAST NAME, First Name — this must match your ID and icpc.global profile"**. If it differs noticeably from the name we got from IITD, the student must tick "I've checked this matches my ID" |
| ID type used | Passport / Aadhaar / other |
| icpc.global account email | Any email, **typed twice** to confirm. Help text: "This must be the exact email on your icpc.global account" |
| Degree, major | B.Tech / Dual / M.Tech / M.Sc / PhD, plus department |
| First began post-secondary studies (month/year) | Used for the eligibility check. Help text explains this means *the first time you started college anywhere*, not just at IITD |
| Expected graduation (month/year) | |
| Previous ICPC regional years, previous World Finals | Counts, used for the eligibility check |

**Checklist** (each item is ticked by the student and timestamped):
`[ ] I have an icpc.global account` · `[ ] It uses the email above` · `[ ] My icpc.global name matches my ID and the name above` · `[ ] Profile, photo and degree info are complete on icpc.global` · `[ ] I have read the guide`

There is also an optional "Upload a screenshot of my icpc.global profile" field. Admins can make it required for a season.

**Eligibility result**, computed by the backend and shown to the student: ✅ *Eligible* / ⚠️ *Needs check* / ❌ *Not eligible*, with the reason.
- Since we don't collect date of birth, the backend checks the **studies start year** and past participation.
- If the start year is too early, the result is ⚠️ *Needs check* rather than ❌, because the student may still qualify through the birth-year rule or a petition. The ICPC admin confirms it with an eligibility override.
- Too many past regional years or World Finals gives ❌.

**Readiness:** a member is *ready* when all details are filled in, every checklist item is ticked, and the eligibility result is not ❌. When all 3 members are ready, the team automatically moves to `DETAILS_COMPLETE`. The team card shows **"2/3 members ready"** and names who is pending, and the captain can send them a reminder.

Details can be edited until the `details_deadline` and lock once the team is `VERIFIED`. After that, changes go through an issue raised by the admin, or a "Request change" button that notifies the admin.

#### ICPC admin page (`/admin/icpc`)

- **Season settings**: year, name, team formation open/close (the **team deadline**, after which rosters freeze), details deadline, coach name and email, max teams, **eligibility parameters** (minimum studies start year, minimum birth year for the guide text, maximum regional years, maximum World Finals), whether the screenshot is required, **guide Markdown**, and **help links**. Regional sites come from the hard-coded list.
- **Teams table**: status, readiness (e.g., "2/3"), eligibility flags, open issues, the two regional preferences, and a "frozen" badge after the deadline. Filter by status, "Needs fix" or "Not ready".
- **Team detail**:
  - Roster, with each member's ICPC details, checklist, screenshot and eligibility result.
  - **Verify** (→ `VERIFIED`) is only enabled at `DETAILS_COMPLETE` with no ❌ members and every ⚠️ member confirmed.
  - **Raise issue** for a member or the team. Choose a type (name mismatch, email not found on icpc.global, no icpc.global account, profile incomplete, degree info missing, photo missing, eligibility question, other) and write a message. The member gets an email and notification. They fix it and click **"Mark fixed"**, then the admin **resolves** it.
  - Status changes with a reason (`REJECTED` / `WITHDRAWN`). Each change notifies the team. Roster changes after the freeze are audit-logged.
  - **ICPC team ID** and the registered sites, entered once the team is registered.
  - **Progress entries**, e.g., `{ stage: "Online Prelims", date, rank, problems_solved, penalty, qualified, notes }`, plus regional, Asia West Finals and World Finals stages. These appear in the team's timeline.
  - Optional **fee status** (not needed / pending / paid by team / paid by club), if the season has fees.
- **Reminders**: one click emails every member who isn't ready ("You haven't confirmed your icpc.global account", etc.). This can also be scheduled automatically 3 days and 1 day before the details deadline and the team deadline.
- **Bulk progress import**: upload a CSV of prelims results.

#### Registering teams on icpc.global

There is no separate coach role on the site. The **ICPC admin** does all of this, either sitting with the coach or using the coach's icpc.global access as the coach agrees.

**Level 1: Registration worksheet (`/admin/icpc/registration`), the default.**
- Lists every `VERIFIED` team, laid out **in the same order as the icpc.global team-registration form**: institution, team name, then each member's icpc.global email and exact name.
- Every value has a **copy button**, so nobody retypes anything. That removes typos.
- Each team has a checklist: "Team created on icpc.global", "All 3 members found by email", "ICPC team ID", which is pasted in, and "Registered for site(s) ...".
- Saving the ICPC team ID moves the team to `REGISTERED`. If a member **can't be found by email**, the admin clicks **"Member not found"** on that row. That raises an `email_not_found` issue for the student straight away, so they are told to check their account, instead of a duplicate person being created.
- Later, the admin marks teams `ACCEPTED` once ICPC accepts them.
- **Export**: XLSX/CSV in the same layout (for example to send to the coach), plus JSON (used by Level 2).

**Level 2: Assisted autofill (optional, experimental).**
- A small **Playwright** script (`tools/icpc_autofill/`) run **on the coach's or admin's own machine**:
  - It opens a real browser window, and the person **logs in to icpc.global themselves**. The script never sees or stores a password.
  - It reads the exported JSON and fills the team-registration form for each team. It **pauses before the final submit**, so a person reviews and clicks *Register*.
  - Afterwards it records the created team IDs into a file that can be uploaded back to the ANCC site to bulk-mark teams `REGISTERED`.
- **Caveats**: we haven't found a public icpc.global API for registering teams, so this depends on the site's page layout and may break when that changes. Before using it, check icpc.global's terms of use and confirm with the coach. It is built only after Level 1 works.

**Level 3 (future):** if ICPC offers an official API or bulk import for coaches, swap the Playwright script for it.

#### Member `/icpc` page

- Tabs: **My Team**, **My ICPC Details**, **Guide**.
- **My Team** shows:
  - season banner with the team deadline and details deadline,
  - team card with members and each member's readiness, plus **Leave team** (until the deadline) or a "Frozen" badge,
  - pending invites, both sent and received,
  - **"Action needed" list**, e.g., "Fill your ICPC details", "Confirm your icpc.global account", "Fix: email not found on icpc.global",
  - the 2 regional preferences,
  - status timeline,
  - progress and results,
  - the coach's name.

---

### 1.6 About Us (`/about`)

- **Hero**: club name, logo and tagline.
- **About the club**: who we are and our history (Markdown, editable by super_admin).
- **Mission and what we do**: competitive programming training, contests, workshops, ICPC preparation, and so on.
- **Team members**: a grid of cards (photo, name, position, LinkedIn/GitHub/Codeforces links), grouped by **position category** (Overall Coordinators, Coordinators, Executives, Faculty In-charge). A **year switcher** shows past teams (e.g., "Team 2025–26", "Team 2024–25").
- **Sponsor section — Jump Trading**: logo, a paragraph about Jump Trading and its support for the club, and a link to their website or careers page. It is stored in a `Sponsor` table so that more sponsors can be added later and text can be edited without a code deploy.
- **Contact**: club email, Instagram/LinkedIn/Discord/WhatsApp community links.

All of this content is managed from `/admin/about`.

---

### 1.7 Add / Edit Event (Admin, `/admin/events/new`, `/admin/events/:id/edit`)

A multi-section form (React Hook Form + Zod) with a live preview.

**1. Basic info:** title, slug (auto-generated, editable), type, short description (shown on the card), full description (Markdown editor with preview), banner image upload.

**2. Schedule and location:** start and end date/time (IST), mode (online/offline/hybrid), venue (e.g., "LHC 108"), meeting link.

**3. Registration settings:**

- Registration mode: **Built-in form** (default) or **External link** (e.g., an Unstop URL), plus "No registration required".
- Registration opens and closes at.
- Max participants (optional) and enable waitlist.
- Individual or team event, with min/max team size.
- Allow cancellation until (defaults to the registration close time).

**4. Registration form builder** (shown when the mode is built-in):

- Add fields of these types: short text, long text, number, email, phone, URL, single select (dropdown/radio), multi select (checkboxes), yes/no, date, file upload.
- Per field: label, help text, required on/off, options (for selects), validation (min/max length, regex for handles).
- Drag to reorder, and duplicate or delete fields.
- Default fields like name, kerberos ID and email are **auto-filled from the profile** and don't need to be added.
- Template presets, e.g., "Contest" (Codeforces handle, experience level) and "Workshop" (laptop available?).

**5. Links and resources:** a repeatable list of `{ label, url, visibility: public | registered_only, reveal_at: optional datetime }`.

**6. ECA and CAIC:**

- **ECA title templates** for participants and winners, e.g., "Participated in {event}" and "Secured {position} position in {event}". These are used by the bulk "Add ECA records" action.
- **Counts for CAIC Trophy** (yes/no). If yes, a **CAIC points table**, e.g., `{ 1st: 50, 2nd: 30, 3rd: 20, participation: 5 }`, used by the bulk "Add CAIC entries" action. The values come from the CAIC rules for that event.

**7. Publish:** Save as **Draft**, **Publish**, or **Schedule publish**. Drafts are visible only to admins.

**After the event** (same page, "Results and media" tab):

- Mark the event **Completed**.
- **Results editor**: add rows `{ position, user (search) or team name, prize amount }`.
  - Adding a prize amount automatically creates a **Prize** payout for the winner(s), at step 1 (*Bank details required*), or step 2 if the student's bank details are already on file. It appears on their dashboard.
  - Saving results offers to create the winners' **ECA records** and **CAIC entries** from the templates and points table above. The admin reviews them before they are saved.
- **Photos**: paste the event's **Google Drive folder link** (from the ANCC Drive), click **Sync**, then reorder, caption or hide photos.
- Recap write-up and resources (slides, editorial, recording).
- **Registrations tab**: table of registrants with their answers, attendance checkboxes, CSV export, and the bulk **"Add ECA records" / "Add CAIC entries"** actions for attendees.

---

### 1.8 Prizes & Reimbursements (Finance)

Prizes and reimbursements are tracked **together** as **payouts**. They move through the same steps, which match the real process:

| Step | Status | What the student sees | Who moves it on |
|---|---|---|---|
| 0 | `submitted` *(reimbursements only)* | "Claim submitted: the club is checking your receipts" | Finance approves the receipts, or rejects with a reason |
| 1 | `bank_details_required` | "Mail your **passbook / cancelled cheque** details to **ancclub@iitd.ac.in**", with a mailto button and a prefilled subject (`Bank details – <payout title> – <kerberos ID>`) | Finance marks "Bank details received" once the mail arrives |
| 2 | `awaiting_professor_approval` | "Waiting for the professor's approval. The professor approves it and sends it to the accounts section" | Finance marks it approved once the professor signs |
| 3 | `accounts_processing` | "The accounts section is processing it. Expect about **21 days**" and an expected-by date (status date + 21 days, configurable) | Finance marks it done |
| 4 | `completed` | "Done ✓", with the date | — |

Plus `rejected` (with a reason) and `cancelled`.

- **Bank details are never stored on the site.** They are mailed to the club email only. Finance can tick **"Bank details on file"** on a user, and then that user's new payouts skip step 1 automatically.
- **Member side**:
  - `/dashboard/payouts` lists all their prizes and reimbursements with the tracker.
  - `/dashboard/payouts/new` is the reimbursement claim form: title, related event (optional), amount, description, and receipt uploads (PDF/JPG, several allowed). Submitting creates a payout at `submitted`.
- **Prizes** are created automatically from event results (see 1.7), or added manually by finance.
- **Finance admin** (`/admin/finance`):
  - One queue with tabs (All / Prizes / Reimbursements) and filters by step, plus receipt previews.
  - Actions to move a payout to the next step, reject, or cancel. **Bulk "move to next step"** is available, e.g., when the professor approves a batch.
  - Each step change notifies the student and is recorded in the payout's history.
  - A "stuck" filter shows payouts that have spent more than N days in one step.
  - Export to CSV for the accounts section.

---

### 1.9 Supporting features

- **Notifications**: in-app (bell icon with unread count) and email for invites, registration confirmations, status changes and payout steps.
- **Home page** (`/`): hero, the next 3 upcoming events, the latest results, quick stats (members, events held, ICPC teams), a sponsor strip and a call to action to log in.
- **File uploads**: banners, avatars, team photos, and documents (receipts, ICPC profile screenshots). Event photos come from Google Drive instead (see 1.3). Uploads are stored on the server's disk, or in S3-compatible storage if we outgrow it. The backend validates type and size and returns URLs. Receipts and screenshots are private: they are served only to the owner and the relevant admins, through authenticated URLs.
- **Search and pagination** on every admin table, done server-side.
- **Rate limiting** on auth and registration endpoints.
- **Responsive design**: the site is mobile-first, since most students will open it on their phones.

**Future / nice-to-have:** Codeforces rating sync from handles, a club leaderboard, a practice problem archive, a blog/editorials section, a calendar (ICS) export of events, and a Discord bot integration.

---

## 2. Tech Stack

### Backend

| Concern | Choice | Why |
|---|---|---|
| Language | **Python 3.12** | Team familiarity, FastAPI ecosystem |
| Framework | **FastAPI** | Async, automatic OpenAPI docs, Pydantic validation |
| ASGI server | **Uvicorn** (dev), **Gunicorn + Uvicorn workers** (prod) | Standard production setup |
| Database | **PostgreSQL 16** | Relational data (users, teams, records), JSONB for dynamic form responses, strong constraints |
| ORM | **SQLAlchemy 2.0 (async)** + **asyncpg** | Typed models, async queries |
| Migrations | **Alembic** | Versioned schema changes |
| Validation / settings | **Pydantic v2**, **pydantic-settings** | Request/response schemas, `.env` config |
| Auth | **Authlib** (OAuth2 client for IITD) + **PyJWT** (our JWTs) | Standard OAuth2 code flow, signed cookies |
| HTTP client | **httpx** | Async calls to the IITD OAuth endpoints |
| Google Drive | **google-api-python-client** + a service account | Lists event photos from the ANCC Drive |
| File storage | Server disk (default), S3-compatible via **boto3** if needed later | Banners, avatars, receipts |
| Email | **fastapi-mail** (SMTP) | Notifications |
| Background jobs | FastAPI `BackgroundTasks` (emails); **APScheduler** for invite expiry, team-deadline freeze, reminders and scheduled publish | Avoids needing Celery at this scale |
| Rate limiting | **slowapi** | Protects login/registration |
| Exports | Python `csv`, **openpyxl** (XLSX) | Registrations, ICPC worksheet, CAIC totals, payouts |
| Dependency management | **uv** (or pip + venv) | Fast, reproducible installs |
| Testing | **pytest**, **pytest-asyncio**, **httpx AsyncClient**, a test Postgres database | API and service tests |
| Code quality | **ruff** (lint + format), **mypy**, **pre-commit** | Consistency |

### Frontend

| Concern | Choice | Why |
|---|---|---|
| Framework | **React 18 + TypeScript** | Type safety across a large admin surface |
| Build tool | **Vite** | Fast dev server and builds |
| Routing | **React Router v6** (data routers) | Nested layouts for public, dashboard and admin |
| Server state | **TanStack Query (React Query)** | Caching, refetching, mutations, loading/error states |
| Client state | **Zustand** (small: auth user, UI) | Lightweight; most state is server state |
| HTTP | **Axios** with `withCredentials` + an interceptor for automatic token refresh on 401 | Cookie-based auth |
| API types | **openapi-typescript**, generated from FastAPI's `/openapi.json` | Frontend and backend types stay in sync |
| Styling | **Tailwind CSS** + **shadcn/ui** (Radix primitives) | Fast, accessible, consistent UI |
| Forms | **React Hook Form** + **Zod** | Event form, form builder, dynamic registration forms |
| Tables | **TanStack Table** | Sortable, paginated admin tables |
| Markdown | **react-markdown** + **remark-gfm**; **@uiw/react-md-editor** for editing | Event descriptions and About content |
| Drag and drop | **dnd-kit** | Form-builder field ordering, gallery ordering |
| Dates | **date-fns** + **date-fns-tz** (IST) | Formatting, countdowns |
| Icons | **lucide-react** | Matches shadcn |
| Toasts | **sonner** | Feedback on actions |
| Gallery | **yet-another-react-lightbox** | Event photos |
| Testing | **Vitest** + **React Testing Library**, **Playwright** for end-to-end | Unit and flow tests |
| Code quality | **ESLint**, **Prettier** | Consistency |

### Infrastructure / DevOps

**Do we need Docker? No.** The stack is just Python, Node and Postgres, so we don't need it.

- **Local development without Docker:**
  - Install Postgres 16 locally (Homebrew, apt, or the Windows installer). Anyone who already uses Docker can run Postgres alone with one `docker run` command instead; that's optional.
  - Backend: `uv sync`, then `alembic upgrade head`, then `uvicorn app.main:app --reload`.
  - Frontend: `npm install`, then `npm run dev`. The Vite dev server proxies `/api` to the backend.
  - A `Makefile` (or `scripts/dev.sh`) wraps these as `make dev`, `make migrate` and `make seed`. Emails are printed to the console in dev.
- **Production without Docker:**
  - One server (an IITD VM or a small VPS) runs **Postgres**, the **backend as a systemd service** (Gunicorn + Uvicorn workers in a virtualenv), and **Nginx**.
  - Nginx serves the built React app and proxies `/api` to FastAPI. Same origin, so there are no CORS or cookie problems.
  - HTTPS through Let's Encrypt.
  - Config files live in `deploy/`.
- **When Docker would be worth adding:** if the hosting provider only accepts containers, or the setup becomes painful across contributors' machines. The layout above doesn't block adding it later.
- **GitHub Actions CI** on each pull request: lint, type-check, backend tests (against a Postgres service in the CI job), and the frontend build.
- **Branch protection on `main`:**
  - Pull requests are required, and CI must pass.
  - **Only proelectro can merge into `main`.** This uses GitHub's "Restrict who can push to matching branches" setting, plus a `CODEOWNERS` file that requires proelectro's approval.
  - Nobody pushes directly to `main`.
- **Deploy** on merge to `main`: a deploy script (`deploy/deploy.sh`) does `git pull` → install dependencies → `alembic upgrade head` → build the frontend → `systemctl restart ancc-backend`. It runs from GitHub Actions over SSH, or by hand if the IITD server isn't reachable from GitHub.
- Database backups with a daily `pg_dump` cron job to off-site storage.
- Environments: `development` (dev login) and `production` (IITD OAuth).

---

## 3. Backend: Models, API and File Structure

### 3.1 Data models (SQLAlchemy)

Every table has `id` (UUID or bigint primary key), `created_at` and `updated_at`, unless noted otherwise.

#### `User`

| Field | Type | Notes |
|---|---|---|
| kerberos_id | str, unique, indexed | e.g., `cs1220123` (from IITD). The main identifier everywhere: invites, CSVs, search |
| name | str | From IITD |
| iitd_email | str, unique | From IITD |
| department | str | From IITD / derived from kerberos ID |
| year_of_study | int, nullable | |
| phone | str, nullable | |
| alt_email | str, nullable | |
| hostel | enum/str, nullable | List of IITD hostels; also used for CAIC |
| codeforces / codechef | str, nullable | Handles |
| avatar_url | str, nullable | |
| bank_details_on_file | bool | Set by finance; new payouts skip step 1 |
| is_active | bool | Admin can deactivate a user to ban them; their data is kept, not deleted |
| last_login_at | datetime | |

#### `UserRole`

`user_id` (FK), `role` (enum: `member`, `coordinator`, `icpc_admin`, `finance`, `super_admin`), `granted_by` (FK User), `granted_at`. Unique on `(user_id, role)`. Every user implicitly has `member`.

#### `RefreshToken`

`user_id`, `token_hash`, `expires_at`, `revoked_at`, `user_agent`, `ip`. Supports rotation and logout.

#### `EcaRecord` (one bullet point of ECA)

| Field | Type | Notes |
|---|---|---|
| user_id | FK User, indexed | |
| title | str | e.g., "Secured 1st position in Code Decode 2026" |
| kind | enum `winner`, `participation`, `attendance`, `organiser`, `other` | |
| event_id | FK Event, nullable | |
| date | date | |
| academic_year | str | e.g., `2026-27` |
| semester | enum `I` / `II` / `SUMMER` | |
| added_by | FK User | |
| revoked_at, revoked_by, revoke_reason | nullable | Revoked records are hidden from the student, not deleted |

#### `CaicScoreEntry` (numeric CAIC Trophy score)

| Field | Type | Notes |
|---|---|---|
| user_id | FK User, indexed | |
| hostel | str | Snapshot of the hostel represented at the time |
| event_id | FK Event | |
| basis | enum `position`, `participation`, `other` | |
| position | int, nullable | 1, 2, 3, ... |
| points | numeric(8,2) | Taken from the event's CAIC points table by default and editable |
| academic_year | str | |
| added_by | FK User | |
| revoked_at, revoked_by, revoke_reason | nullable | Revoked entries are excluded from totals |

Totals: the student's total is `SUM(points)` over active entries for the year. Hostel totals are the same sum grouped by `hostel`.

#### `Event`

| Field | Type | Notes |
|---|---|---|
| slug | str, unique | |
| title | str | |
| short_description | str | |
| description_md | text | |
| summary_md | text, nullable | Post-event recap |
| type | enum `contest`, `workshop`, `talk`, `hackathon`, `lecture`, `other` | |
| mode | enum `online`, `offline`, `hybrid` | |
| venue | str, nullable | |
| meeting_link | str, nullable | |
| banner_url | str, nullable | |
| start_at, end_at | datetime (timezone-aware) | |
| status | enum `draft`, `published`, `completed`, `cancelled` | "Ongoing" is computed from times |
| publish_at | datetime, nullable | Scheduled publish |
| registration_mode | enum `built_in`, `external`, `none` | |
| external_registration_url | str, nullable | |
| registration_opens_at, registration_closes_at | datetime, nullable | |
| cancellation_deadline | datetime, nullable | |
| max_participants | int, nullable | |
| waitlist_enabled | bool | |
| is_team_event | bool | |
| min_team_size, max_team_size | int, nullable | |
| eca_participation_template, eca_winner_template | str, nullable | e.g., "Participated in {event}" |
| counts_for_caic | bool | |
| caic_points_table | JSONB, nullable | e.g., `{"1": 50, "2": 30, "3": 20, "participation": 5}` |
| photos_drive_folder_url | str, nullable | Google Drive album link |
| photos_synced_at | datetime, nullable | |
| show_participant_count | bool | |
| created_by | FK User | |

#### `EventFormField`

`event_id`, `label`, `key` (slug, unique per event), `field_type` (enum: `short_text`, `long_text`, `number`, `email`, `phone`, `url`, `select`, `multi_select`, `boolean`, `date`, `file`), `options` (JSONB list), `required` (bool), `help_text`, `validation` (JSONB: min, max, regex), `order` (int).

#### `EventRegistration`

`event_id`, `user_id`, `status` (enum `registered`, `waitlisted`, `cancelled`, `attended`), `responses` (JSONB `{field_key: value}`), `team_name` (nullable), `waitlist_position` (nullable), `registered_at`, `cancelled_at`. Unique on `(event_id, user_id)`.

#### `EventTeamMember` (for team events)

`registration_id`, `user_id` (nullable), `kerberos_id`, `name`. Lets teammates see the registration on their own dashboards.

#### `EventLink`

`event_id`, `label`, `url`, `kind` (enum `contest`, `meeting`, `slides`, `editorial`, `recording`, `other`), `visibility` (enum `public`, `registered_only`), `reveal_at` (nullable), `order`.

#### `EventResult`

`event_id`, `position` (int), `user_id` (nullable), `team_name` (nullable), `display_name`, `prize_amount` (numeric, nullable), `remarks`.

#### `EventPhoto` (synced from Google Drive)

`event_id`, `drive_file_id` (unique per event), `name`, `thumbnail_url`, `view_url`, `caption` (nullable), `order`, `hidden` (bool). Rebuilt or updated on each sync. Captions, order and hidden flags are kept across syncs.

#### `Payout` (prizes and reimbursements together)

| Field | Type | Notes |
|---|---|---|
| user_id | FK User, indexed | |
| kind | enum `prize`, `reimbursement` | |
| event_id | FK Event, nullable | |
| event_result_id | FK EventResult, nullable | For prizes |
| title, description | str, text | |
| amount | numeric(10,2) | |
| status | enum `submitted`, `bank_details_required`, `awaiting_professor_approval`, `accounts_processing`, `completed`, `rejected`, `cancelled` | `submitted` is used only for reimbursements |
| status_changed_at | datetime | |
| expected_by | date, nullable | Set when the payout enters `accounts_processing` (+21 days by default) |
| bank_details_received_at | datetime, nullable | |
| completed_at | datetime, nullable | |
| review_note / reject_reason | text, nullable | |
| updated_by | FK User | |

#### `PayoutAttachment`

`payout_id`, `file_id` (FK `FileUpload`). Receipts for reimbursements.

#### `PayoutStatusHistory`

`payout_id`, `from_status`, `to_status`, `changed_by`, `note`, `changed_at`. Powers the step tracker and the "stuck" filter.

#### `ICPCSeason`

| Field | Type | Notes |
|---|---|---|
| year, name | int, str | e.g., 2026, "ICPC 2026–27" |
| team_formation_opens_at | datetime | |
| team_formation_closes_at | datetime | **Team deadline**: after this, rosters freeze |
| details_deadline | datetime | Deadline for members to complete ICPC details |
| is_active | bool | |
| coach_name, coach_email | str | ANCC faculty in-charge |
| max_teams | int, nullable | |
| min_studies_start_year | int | Eligibility parameter (e.g., 2021 for 2025–26) |
| min_birth_year | int | Shown in the guide and used by the "Am I eligible?" helper (not stored per student) |
| max_regional_years, max_world_finals | int | Defaults 5 and 2 |
| require_profile_screenshot | bool | |
| guide_md | text | Content of `/icpc/guide` |
| help_links | JSONB | `[{label, url}]`: icpc.global, rules, regional pages, etc. |

Regional sites are **not** a table for now. They live in `app/core/icpc_sites.py` as a constant list of `{code, name, host, prelims_date, regional_date, official_url, fee_info}`.

#### `ICPCTeam`

| Field | Type | Notes |
|---|---|---|
| season_id | FK | |
| name | str | Unique per season |
| captain_id | FK User | |
| status | enum `PENDING_INVITES`, `FORMED`, `DETAILS_COMPLETE`, `VERIFIED`, `REGISTERED`, `ACCEPTED`, `REJECTED`, `WITHDRAWN` | "Needs fix" (open issues) and "Frozen" (past the team deadline) are shown as badges, not statuses |
| status_reason | str, nullable | For REJECTED/WITHDRAWN |
| icpc_team_id | str, nullable | Team ID on icpc.global |
| fee_status | enum `not_applicable`, `pending`, `paid_by_team`, `paid_by_club`, nullable | |
| admin_notes | text | |
| formed_at, details_complete_at, verified_at, registered_at, accepted_at | datetime, nullable | |

#### `ICPCTeamSitePreference`

`team_id`, `site_code` (str; must be one of the codes in `icpc_sites.py`), `rank` (1 or 2), `registered` (bool: set once the team is registered for that site). Unique on `(team_id, site_code)` and `(team_id, rank)`, so each team has exactly 2 choices.

#### `ICPCTeamMember`

`team_id`, `user_id`, `season_id` (denormalized), `role` (enum `captain`, `member`), `joined_at`, `eligibility_override` (enum `none`, `approved`, `petition_filed`, nullable; set by an admin), `eligibility_notes`. Unique on `(team_id, user_id)` and on `(season_id, user_id)`, which enforces **one team per user per season**. A row is deleted when a member leaves before the deadline, and the leave is recorded in `ICPCStatusHistory`.

#### `ICPCContestantDetails` (one per user per season)

| Field | Type | Notes |
|---|---|---|
| user_id, season_id | FK | Unique together |
| first_name_on_id, last_name_on_id | str | Validated: Latin letters, spaces, `.`, `'` and `-` |
| id_document_type | enum `passport`, `aadhaar`, `other` | |
| name_mismatch_ack | bool | Required if the name differs noticeably from the IITD name |
| icpc_email | str | Any email; must be the one on their icpc.global account |
| degree, major | str | |
| studies_started_on | date (month/year) | The first time they started post-secondary studies anywhere |
| expected_graduation | date (month/year) | |
| previous_regional_years, previous_world_finals | int | |
| checklist | JSONB | `{has_account, email_matches, name_matches, profile_complete, read_guide}`, each storing a timestamp when ticked |
| profile_screenshot_file_id | FK FileUpload, nullable | |
| eligibility_result | enum `eligible`, `needs_check`, `ineligible` | Computed by `eligibility.py` on save |
| eligibility_reason | str | |
| is_ready | bool | Computed: all fields filled, checklist complete, not ineligible |
| locked_at | datetime, nullable | Set when the team is VERIFIED |

#### `ICPCIssue`

`team_id`, `user_id` (nullable, for team-level issues), `type` (enum `name_mismatch`, `email_not_found`, `no_icpc_account`, `profile_incomplete`, `degree_info_missing`, `photo_missing`, `eligibility_question`, `other`), `message`, `status` (enum `open`, `marked_fixed`, `resolved`), `raised_by`, `marked_fixed_at`, `resolved_by`, `resolved_at`.

#### `ICPCInvite`

`team_id`, `inviter_id`, `invitee_id` (nullable), `invitee_kerberos_id`, `status` (enum `pending`, `accepted`, `declined`, `cancelled`, `expired`), `expires_at`, `responded_at`.

#### `ICPCProgress`

`team_id`, `stage` (str, e.g., "Online Prelims", "Kanpur Regional"), `site_code` (nullable), `date`, `rank` (nullable), `problems_solved` (nullable), `penalty` (nullable), `qualified` (bool, nullable), `notes`, `created_by`.

#### `ICPCStatusHistory`

`team_id`, `from_status`, `to_status`, `event` (e.g., `member_left`, `member_joined`, `frozen`, `status_change`), `changed_by`, `reason`, `changed_at`. Powers the status timeline.

#### `ClubMember` (About page team)

`user_id` (nullable), `name`, `position` (e.g., "Overall Coordinator"), `position_group` (enum `faculty`, `overall_coordinator`, `coordinator`, `executive`, `other`), `tenure` (e.g., "2026-27"), `photo_url`, `linkedin`, `github`, `codeforces`, `order`, `is_current`.

#### `Sponsor`

`name` (e.g., "Jump Trading"), `tier` (e.g., "Title Sponsor"), `description_md`, `logo_url`, `website_url`, `is_active`, `order`.

#### `SiteContent`

`key` (unique, e.g., `about_intro`, `about_mission`, `contact_links`), `value_md` or `value_json`, `updated_by`. Editable blocks for the About page and the home page.

#### `Notification`

`user_id`, `type`, `title`, `body`, `link`, `read_at` (nullable).

#### `FileUpload`

`owner_id`, `storage_key`, `original_name`, `mime_type`, `size_bytes`, `visibility` (enum `public`, `private`), `purpose` (enum `banner`, `avatar`, `receipt`, `icpc_screenshot`, `team_photo`, `sponsor_logo`, `form_answer`).

#### `AuditLog`

`actor_id`, `action` (e.g., `eca.add`, `eca.revoke`, `caic.add`, `payout.status_change`, `icpc.status_change`, `role.grant`), `entity_type`, `entity_id`, `before` (JSONB), `after` (JSONB), `ip`, `created_at`.

#### Entity relationships (summary)

```
User 1─* UserRole
User 1─* EcaRecord *─0..1 Event
User 1─* CaicScoreEntry *─1 Event
User 1─* EventRegistration *─1 Event 1─* EventFormField
Event 1─* EventLink / EventResult / EventPhoto
EventResult 1─0..1 Payout(kind=prize) *─1 User
User 1─* Payout 1─* PayoutAttachment ─ FileUpload
Payout 1─* PayoutStatusHistory
ICPCSeason 1─* ICPCTeam 1─* ICPCTeamMember *─1 User
ICPCTeam 1─2 ICPCTeamSitePreference (site_code → icpc_sites.py)
ICPCSeason 1─* ICPCContestantDetails *─1 User
ICPCTeam 1─* ICPCInvite / ICPCIssue / ICPCProgress / ICPCStatusHistory
User 1─* Notification
```

### 3.2 API endpoints (`/api/v1`)

| Area | Method and path | Access |
|---|---|---|
| **Auth** | `GET /auth/config` | public |
| | `GET /auth/login` → redirect to IITD | public |
| | `GET /auth/callback` | public |
| | `POST /auth/dev-login` *(dev only)* | public (dev) |
| | `POST /auth/refresh`, `POST /auth/logout` | auth |
| | `GET /auth/me` | auth |
| **Profile** | `GET /users/me`, `PATCH /users/me`, `POST /users/me/avatar` | member |
| **Dashboard** | `GET /dashboard` (aggregate) | member |
| | `GET /users/me/eca?year=`, `GET /users/me/caic?year=` | member |
| | `GET /users/me/registrations` | member |
| **Payouts** | `GET /payouts/mine`, `GET /payouts/{id}` | member (owner) |
| | `POST /payouts/reimbursements` (claim + receipts) | member |
| **Events (public)** | `GET /events?status=&type=&q=&page=` | public |
| | `GET /events/{slug}` (links filtered by visibility, visible photos) | public |
| | `GET /events/{id}/form` | public |
| | `POST /events/{id}/register`, `DELETE /events/{id}/register` | member |
| | `GET /events/{id}/my-registration` | member |
| **Events (admin)** | `POST /admin/events`, `PATCH /admin/events/{id}`, `DELETE /admin/events/{id}` | coordinator |
| | `PUT /admin/events/{id}/form` (replace all fields) | coordinator |
| | `PUT /admin/events/{id}/links` | coordinator |
| | `GET /admin/events/{id}/registrations`, `GET .../registrations.csv` | coordinator |
| | `PATCH /admin/registrations/{id}` (attendance/status) | coordinator |
| | `PUT /admin/events/{id}/results` (also creates prize payouts) | coordinator |
| | `POST /admin/events/{id}/photos/sync` (Google Drive), `PATCH /admin/photos/{id}` (caption/order/hidden) | coordinator |
| | `POST /admin/events/{id}/eca/preview`, `POST /admin/events/{id}/eca` (bulk ECA records from templates) | coordinator |
| | `POST /admin/events/{id}/caic/preview`, `POST /admin/events/{id}/caic` (bulk CAIC entries from points table) | coordinator |
| | `GET /admin/events/{id}/caic.csv` (hostel-wise totals for CAIC) | coordinator |
| **Users, ECA, CAIC (admin)** | `GET /admin/users?q=&dept=&hostel=&role=&page=`, `GET /admin/users/{id}` | coordinator+ |
| | `POST /admin/users/{id}/eca`, `POST /admin/eca/{id}/revoke` | coordinator |
| | `POST /admin/users/{id}/caic`, `POST /admin/caic/{id}/revoke` | coordinator |
| | `POST /admin/records/bulk?type=eca\|caic` (CSV: preview + commit) | coordinator |
| | `GET /admin/caic/totals?year=` (hostel totals for ANCC events), `GET /admin/caic/totals.csv` | coordinator |
| | `PATCH /admin/users/{id}` (deactivate, bank details on file) | super_admin / finance |
| | `PUT /admin/users/{id}/roles` | super_admin |
| **Finance** | `GET /admin/payouts?kind=&status=&stuck=`, `GET /admin/payouts/{id}` | finance |
| | `POST /admin/payouts` (manual prize) | finance |
| | `POST /admin/payouts/{id}/advance`, `POST /admin/payouts/{id}/reject`, `POST /admin/payouts/{id}/cancel` | finance |
| | `POST /admin/payouts/bulk-advance`, `GET /admin/payouts.csv` | finance |
| **ICPC (member)** | `GET /icpc/season/current` (includes the hard-coded regional sites and deadlines) | public |
| | `GET /icpc/guide` (guide Markdown, help links, eligibility parameters) | public |
| | `POST /icpc/eligibility-check` ("Am I eligible?" helper; nothing stored) | public |
| | `GET /icpc/me/details`, `PUT /icpc/me/details`, `PUT /icpc/me/checklist`, `POST /icpc/me/screenshot` | member |
| | `POST /icpc/me/details/request-change` (after lock) | team member |
| | `GET /icpc/my-team`, `POST /icpc/teams` | member (before deadline) |
| | `POST /icpc/teams/{id}/invites`, `DELETE /icpc/invites/{id}` | captain (before deadline) |
| | `GET /icpc/invites/mine`, `POST /icpc/invites/{id}/accept`, `POST /icpc/invites/{id}/decline` | member (invitee, before deadline) |
| | `POST /icpc/teams/{id}/leave` | team member (before deadline) |
| | `POST /icpc/teams/{id}/remind` (nudge members who aren't ready) | team member |
| | `PUT /icpc/teams/{id}/site-preferences` (exactly 2, ranked) | captain (before deadline) |
| | `GET /icpc/my-team/issues`, `POST /icpc/issues/{id}/mark-fixed` | team member |
| **ICPC (admin)** | `POST /admin/icpc/seasons`, `PATCH /admin/icpc/seasons/{id}` (includes deadlines, guide, links, eligibility parameters) | icpc_admin |
| | `GET /admin/icpc/teams?season=&status=&needs_fix=&frozen=`, `GET /admin/icpc/teams/{id}` | icpc_admin |
| | `PATCH /admin/icpc/teams/{id}` (status, notes, roster, fee status, eligibility override) | icpc_admin |
| | `POST /admin/icpc/teams/{id}/issues`, `PATCH /admin/icpc/issues/{id}` (resolve or reopen) | icpc_admin |
| | `POST /admin/icpc/reminders` (email every member who isn't ready) | icpc_admin |
| | `POST /admin/icpc/teams/{id}/progress`, `PATCH/DELETE /admin/icpc/progress/{id}`, `POST /admin/icpc/progress/bulk` | icpc_admin |
| | `GET /admin/icpc/registration-worksheet?season=` | icpc_admin |
| | `POST /admin/icpc/teams/{id}/mark-registered` (`icpc_team_id`, sites), `POST /admin/icpc/teams/{id}/mark-accepted` | icpc_admin |
| | `POST /admin/icpc/teams/{id}/members/{user_id}/not-found` (raises an `email_not_found` issue) | icpc_admin |
| | `GET /admin/icpc/export?season=&status=VERIFIED&format=xlsx\|csv\|json`, `POST /admin/icpc/registered/bulk` | icpc_admin |
| **About / content** | `GET /about` (content, current team, sponsors), `GET /about/team?tenure=` | public |
| | CRUD `/admin/club-members`, `/admin/sponsors`, `PUT /admin/content/{key}` | super_admin |
| **Notifications** | `GET /notifications`, `POST /notifications/{id}/read`, `POST /notifications/read-all` | member |
| **Uploads** | `POST /uploads` (returns file id and URL), `GET /files/{id}` (private files, authenticated) | member / admin |
| **Audit** | `GET /admin/audit?entity=&actor=` | super_admin |
| **Health** | `GET /health` | public |

Permissions are enforced with a FastAPI dependency: `Depends(require_roles("coordinator"))`. `super_admin` passes every check. "Before deadline" rules are enforced in the ICPC services, not just in the UI.

### 3.3 Backend file structure

```
backend/
├── app/
│   ├── main.py                     # create_app(): routers, CORS, middleware, exception handlers, startup checks
│   ├── core/
│   │   ├── config.py               # Settings (pydantic-settings): ENV, AUTH_MODE, DB URL, IITD OAuth creds, JWT secret,
│   │   │                           # SMTP, Google service account, payout processing days (21)
│   │   ├── database.py             # async engine, async_sessionmaker, Base
│   │   ├── security.py             # JWT encode/decode, cookie helpers, refresh rotation
│   │   ├── deps.py                 # get_db, get_current_user, get_optional_user, require_roles(...)
│   │   ├── permissions.py          # Role enum, permission helpers
│   │   ├── icpc_sites.py           # hard-coded regional sites (update each season)
│   │   ├── exceptions.py           # AppError types + handlers
│   │   ├── rate_limit.py           # slowapi limiter
│   │   └── logging.py
│   ├── models/
│   │   ├── __init__.py             # import all models (for Alembic autogenerate)
│   │   ├── base.py                 # Base, TimestampMixin, UUID pk mixin
│   │   ├── user.py                 # User, UserRole, RefreshToken
│   │   ├── records.py              # EcaRecord, CaicScoreEntry
│   │   ├── event.py                # Event, EventFormField, EventLink, EventResult, EventPhoto
│   │   ├── registration.py         # EventRegistration, EventTeamMember
│   │   ├── payout.py               # Payout, PayoutAttachment, PayoutStatusHistory
│   │   ├── icpc.py                 # ICPCSeason, ICPCTeam, ICPCTeamSitePreference, ICPCTeamMember,
│   │   │                           # ICPCContestantDetails, ICPCIssue, ICPCInvite, ICPCProgress, ICPCStatusHistory
│   │   ├── about.py                # ClubMember, Sponsor, SiteContent
│   │   ├── notification.py         # Notification
│   │   ├── file.py                 # FileUpload
│   │   └── audit.py                # AuditLog
│   ├── schemas/                    # Pydantic request/response models (mirrors models/)
│   │   ├── common.py               # Page[T], Message, enums re-exported
│   │   ├── auth.py
│   │   ├── user.py
│   │   ├── dashboard.py
│   │   ├── records.py              # ECA + CAIC
│   │   ├── event.py
│   │   ├── form.py                 # form field schemas + dynamic response validator builder
│   │   ├── registration.py
│   │   ├── payout.py
│   │   ├── icpc.py
│   │   ├── about.py
│   │   └── notification.py
│   ├── api/
│   │   └── v1/
│   │       ├── router.py           # includes all routers; mounts dev_auth only if AUTH_MODE=dev
│   │       ├── auth.py
│   │       ├── dev_auth.py
│   │       ├── users.py
│   │       ├── dashboard.py
│   │       ├── events.py
│   │       ├── payouts.py
│   │       ├── icpc.py
│   │       ├── about.py
│   │       ├── notifications.py
│   │       ├── uploads.py
│   │       └── admin/
│   │           ├── users.py
│   │           ├── records.py      # ECA / CAIC add, revoke, bulk, totals
│   │           ├── events.py
│   │           ├── payouts.py
│   │           ├── icpc.py
│   │           ├── content.py
│   │           └── audit.py
│   ├── services/                   # business logic; routers stay thin
│   │   ├── auth/
│   │   │   ├── iitd_oauth.py       # authorize URL, code→token exchange, fetch profile
│   │   │   └── session.py          # upsert user, issue/rotate tokens
│   │   ├── user_service.py
│   │   ├── dashboard_service.py
│   │   ├── eca_service.py          # add/revoke records, templates, bulk
│   │   ├── caic_service.py         # add/revoke entries, points table, totals per student and hostel, bulk
│   │   ├── event_service.py        # CRUD, status computation, link visibility filtering
│   │   ├── registration_service.py # validation, capacity, waitlist promotion
│   │   ├── drive_service.py        # list photos in a Google Drive folder, sync EventPhoto
│   │   ├── payout_service.py       # step machine, auto-create prizes, skip step 1 if bank details on file, expected_by
│   │   ├── icpc/
│   │   │   ├── team_service.py     # create team, invites, accept/decline, leave (before deadline), captaincy hand-over
│   │   │   ├── deadlines.py        # is_frozen(season), freeze job, guards used by all mutations
│   │   │   ├── details_service.py  # ICPC details + checklist, name/email validation, lock at VERIFIED
│   │   │   ├── eligibility.py      # pure functions: season parameters + details → eligible / needs_check / ineligible
│   │   │   ├── readiness.py        # member/team readiness, auto-move to DETAILS_COMPLETE
│   │   │   ├── state_machine.py    # allowed status transitions + history + notifications
│   │   │   ├── issue_service.py    # raise / mark fixed / resolve, "member not found"
│   │   │   └── registration_service.py # worksheet, mark registered/accepted, export (xlsx/csv/json)
│   │   ├── notification_service.py # in-app + email fan-out
│   │   ├── storage_service.py      # disk / S3 backends
│   │   ├── export_service.py       # CSV / XLSX generation
│   │   └── audit_service.py
│   ├── tasks/
│   │   ├── scheduler.py            # APScheduler setup
│   │   └── jobs.py                 # expire invites, freeze ICPC teams at deadline, reminders, scheduled publish
│   ├── emails/
│   │   └── templates/              # Jinja2 email templates (invite, registration, payout step, ICPC issue)
│   └── utils/
│       ├── slug.py
│       ├── time.py                 # IST helpers, academic year/semester derivation
│       └── kerberos.py             # parse department/entry year from kerberos ID
├── alembic/
│   ├── env.py
│   └── versions/
├── scripts/
│   ├── seed.py                     # sample users (each role), events, teams, payouts for local dev
│   └── create_superadmin.py        # bootstrap the first super_admin in prod by kerberos ID
├── tests/
│   ├── conftest.py                 # test DB, async client, user/role factories
│   ├── test_auth.py
│   ├── test_events.py
│   ├── test_registration.py
│   ├── test_eca_caic.py            # templates, points table, revoke, hostel snapshot, totals
│   ├── test_payouts.py             # step order, skip step 1, expected_by, rejections
│   ├── test_icpc_teams.py          # invite rules, one-team-per-season, leave, captaincy, freeze at deadline
│   ├── test_icpc_eligibility.py    # start-year / past-participation cases, needs_check
│   └── test_icpc_readiness.py      # checklist + details → DETAILS_COMPLETE, issues, locking
├── alembic.ini
├── pyproject.toml
└── .env.example

deploy/
├── ancc-backend.service            # systemd unit (Gunicorn + Uvicorn workers)
├── nginx.conf                      # serves frontend build, proxies /api
├── deploy.sh                       # pull → install → migrate → build → restart
└── backup.sh                       # daily pg_dump (cron)

tools/
└── icpc_autofill/                  # optional (Level 2): Playwright script run locally
    ├── autofill.py                 # reads export JSON, fills icpc.global forms, pauses before submit
    └── README.md                   # how to run, safety notes (no stored credentials, a person clicks submit)

.github/
├── workflows/ci.yml                # lint, type-check, tests (Postgres service), frontend build
├── workflows/deploy.yml            # on merge to main → deploy.sh over SSH
└── CODEOWNERS                      # proelectro owns everything → required approval for main

Makefile                            # make dev / migrate / seed / test
```

**Layering rule:** routers (`api/`) handle HTTP and permissions → services hold the business rules → models handle persistence. The ICPC state machine and deadline freeze, the payout steps, registration rules and the ECA/CAIC logic all live in services, so they can be unit-tested.

---

## 4. Frontend Structure

### 4.1 Routes and pages

| Route | Page | Access |
|---|---|---|
| `/` | `HomePage`: hero, upcoming events, latest results, stats, sponsor strip | public |
| `/events` | `EventsPage`: tabs (upcoming/ongoing/past), filters, search, event cards | public |
| `/events/:slug` | `EventDetailPage`: info, links, registration form/status, results, Drive photo gallery | public (register needs login) |
| `/about` | `AboutPage`: about, mission, team (year switcher), Jump Trading sponsor section, contact | public |
| `/login` | `LoginPage`: "Login with IITD" button, or the dev login form in dev mode | public |
| `/auth/callback` | `AuthCallbackPage`: loads `/auth/me`, then redirects | public |
| `/dashboard` | `DashboardPage`: profile (top), ECA records, CAIC score, prizes & reimbursements, ICPC, my events | member |
| `/dashboard/eca-caic` | `EcaCaicPage`: full ECA list and CAIC breakdown, with a year filter | member |
| `/dashboard/events` | `MyEventsPage` | member |
| `/dashboard/payouts` | `MyPayoutsPage`: prizes and reimbursements with the step tracker (+ `/new` → `NewReimbursementPage`) | member |
| `/profile` | `ProfilePage`: edit contact, hostel, handles (onboarding mode on first login) | member |
| `/icpc/guide` | `ICPCGuidePage`: step-by-step icpc.global registration guide, common mistakes, eligibility helper, regional site cards, help links | public |
| `/icpc` | `ICPCPage`: tabs for My Team (team card, readiness, leave/frozen, invites, action needed, site preferences, timeline, progress), My ICPC Details and Guide | member |
| `/icpc/details` | `ICPCDetailsPage`: ICPC details form, checklist, screenshot upload, eligibility result, open issues | member |
| `/notifications` | `NotificationsPage` | member |
| `/admin` | `AdminOverviewPage`: counts, pending queues, quick links | any admin role |
| `/admin/users` | `AdminUsersPage` | coordinator+ |
| `/admin/users/:id` | `AdminUserDetailPage`: profile, ECA records, CAIC entries (add/revoke), payouts, roles | coordinator+ |
| `/admin/records/bulk` | `BulkRecordsPage`: choose ECA or CAIC, CSV upload, preview, commit | coordinator |
| `/admin/caic` | `CaicTotalsPage`: hostel totals for ANCC events, export | coordinator |
| `/admin/events` | `AdminEventsPage`: all events including drafts | coordinator |
| `/admin/events/new`, `/admin/events/:id/edit` | `EventEditorPage`: tabs for Details, Registration and Form Builder, Links, ECA & CAIC, Results and Photos | coordinator |
| `/admin/events/:id/registrations` | `EventRegistrationsPage`: table, attendance, export, bulk ECA/CAIC | coordinator |
| `/admin/finance` | `PayoutsAdminPage`: combined queue (All / Prizes / Reimbursements), step filters, stuck filter, bulk advance, export | finance |
| `/admin/icpc` | `ICPCAdminPage`: season selector, teams table, reminders | icpc_admin |
| `/admin/icpc/teams/:id` | `ICPCTeamAdminPage`: roster, details, eligibility, issues, status, progress | icpc_admin |
| `/admin/icpc/seasons`, `/admin/icpc/seasons/:id` | `ICPCSeasonsPage`, `ICPCSeasonEditorPage`: deadlines, eligibility parameters, guide editor, help links | icpc_admin |
| `/admin/icpc/registration` | `RegistrationWorksheetPage`: verified teams in icpc.global form order, copy buttons, "member not found", mark registered/accepted, export | icpc_admin |
| `/admin/about` | `AboutAdminPage`: content blocks, club members, sponsors | super_admin |
| `/admin/audit` | `AuditLogPage` | super_admin |
| `*` | `NotFoundPage`, plus a `ForbiddenPage` for 403 | — |

### 4.2 Layouts and route guards

- **`PublicLayout`**: `Navbar` (logo, Events, About, ICPC, Login/avatar menu) and `Footer` (socials, sponsor, contact).
- **`DashboardLayout`**: Navbar plus a side navigation (Dashboard, ECA & CAIC, My Events, Prizes & Reimbursements, ICPC, Profile). On mobile it collapses into a drawer.
- **`AdminLayout`**: an admin sidebar that shows only the sections the user's roles allow, with breadcrumbs.
- **`<RequireAuth>`**: redirects to `/login?next=...` if the user is not logged in.
- **`<RequireRole roles={[...]}>`**: shows `ForbiddenPage` if the user lacks the role. The backend still enforces permissions on its own; this guard is for user experience only.

### 4.3 Components

```
components/
├── ui/                    # shadcn/ui primitives: Button, Card, Dialog, Input, Select, Tabs, Badge, Table, Toast, Skeleton, ...
├── layout/                # Navbar, Footer, UserMenu, NotificationBell, DashboardSidebar, AdminSidebar, PageHeader, Breadcrumbs
├── common/                # DataTable (TanStack Table + server pagination), EmptyState, ErrorState, ConfirmDialog,
│                          # StatusBadge, StepTracker, MarkdownRenderer, MarkdownEditor, FileUploader, ImageUploader,
│                          # UserSearchCombobox, DateTimePicker, Countdown, CsvUploadPreview, ExportButton, CopyField
├── auth/                  # LoginWithIITDButton, DevLoginForm, RequireAuth, RequireRole
├── events/                # EventCard, EventGrid, EventFilters, EventStatusBadge, EventMeta (date/venue/mode),
│                          # EventLinksList, RegistrationPanel (chooses state: login / form / registered / closed / full),
│                          # DynamicRegistrationForm (renders EventFormField[] → RHF + Zod), DynamicField,
│                          # TeamMembersInput, ResultsPodium, ResultsTable, DrivePhotoGallery (grid + lightbox + "open in Drive")
├── dashboard/             # ProfileSummaryCard, ProfileIncompleteBanner, EcaRecordsCard, CaicScoreCard,
│                          # PayoutsCard, ICPCStatusCard, MyUpcomingEventsCard
├── records/               # EcaRecordList (bullets grouped by year/semester), CaicBreakdownTable, HostelTotalsTable
├── profile/               # ProfileForm, HostelSelect, AvatarUpload
├── payouts/               # PayoutItem (title, amount, StepTracker, "what to do now"), BankDetailsMailButton,
│                          # ReimbursementForm, ReceiptPreview,
│                          # admin/: PayoutsTable, AdvanceStepDialog, RejectDialog, BulkAdvanceBar
├── icpc/                  # SeasonBanner, CreateTeamForm, TeamCard, MemberList, InviteMemberForm, SentInvitesList,
│                          # ReceivedInviteCard (accept/decline), LeaveTeamButton, FrozenBadge,
│                          # TeamStatusTimeline, ProgressTable, SitePreferencePicker (exactly 2, ranked),
│                          # TeamReadinessBar, ActionNeededList, IssueBanner, IssueList,
│                          # guide/: GuideRenderer, GuideStep, CommonMistakes, EligibilityHelper, RegionalSiteCard, HelpLinks,
│                          # details/: ICPCDetailsForm, NameOnIdFields (+ live NamePreview), ConfirmEmailField,
│                          #           ReadinessChecklist, ProfileScreenshotUpload, EligibilityResultBadge,
│                          # admin/: TeamsTable, TeamStatusUpdateDialog, MemberDetailsPanel, RaiseIssueDialog,
│                          #         ProgressEntryForm, SeasonForm, GuideEditor, SendRemindersButton,
│                          #         RegistrationWorksheet, WorksheetTeamCard, MemberNotFoundButton, MarkRegisteredDialog
├── admin/
│   ├── users/             # UsersTable, UserFilters, AddEcaRecordDialog, AddCaicEntryDialog, RevokeDialog, RoleManager
│   ├── events/            # EventForm (sections), RegistrationSettings, FormBuilder, FormFieldEditor, FieldTypePicker,
│   │                      # LinksEditor, EcaCaicSettings (templates + CAIC points table), ResultsEditor,
│   │                      # DrivePhotosManager (folder link, sync, caption/hide/order), RegistrationsTable,
│   │                      # BulkEcaDialog, BulkCaicDialog
│   ├── about/             # ContentBlockEditor, ClubMembersManager, SponsorEditor
│   └── AuditLogTable
└── about/                 # AboutHero, MissionSection, TeamGrid, TeamMemberCard, TenureSwitcher, SponsorSection, ContactSection
```

**Key component details:**

- **`DynamicRegistrationForm`** takes the event's `EventFormField[]` and builds a Zod schema at runtime (required, type and regex rules). It renders a `DynamicField` for each field type and submits `{ responses }`. The backend validates the same rules again.
- **`FormBuilder`** is a list of fields reorderable with dnd-kit. Each row opens a `FormFieldEditor` (label, type, options, required, validation). There is a live **preview** pane that renders `DynamicRegistrationForm` with the draft fields.
- **`RegistrationPanel`** is a small state machine for the user's side of an event: `not_logged_in | not_open_yet | open | registered | waitlisted | full | closed | external`.
- **`StepTracker`** is a generic horizontal or vertical stepper. `PayoutItem` uses it for the 4 payout steps and shows the current step's instruction, such as the bank-details mail button.
- **`BulkEcaDialog` / `BulkCaicDialog`** show a preview table, with editable titles or points per student, before saving.
- **`NameOnIdFields`** has separate first and last name inputs with a live preview (`LAST NAME, First Name`), checks the allowed characters, and shows a soft warning with a required acknowledgement when the name differs from the IITD name.
- **`RegistrationWorksheet`** shows each team in the same order as the icpc.global registration form, with a `CopyField` (one-click copy) for every value, so nobody retypes names or emails.
- **`TeamStatusTimeline`** reads `ICPCStatusHistory` and progress entries, and shows each stage as done, current or upcoming.
- **`DataTable`** is a generic table with server-side pagination, sort and filter wired to query params, so admin views can be bookmarked.

### 4.4 State, data fetching and API layer

- **`api/client.ts`**: an Axios instance with `baseURL=/api/v1` and `withCredentials: true`. A response interceptor catches 401, calls `/auth/refresh` once, then retries the request. If the refresh fails, the user is sent to `/login`.
- **`api/types.ts`**: generated by `openapi-typescript` from the backend (`npm run gen:api`).
- **`api/*.ts`**: typed functions per domain (`events.ts`, `icpc.ts`, ...).
- **`hooks/queries/*`**: TanStack Query hooks, e.g., `useEvents(filters)`, `useEvent(slug)`, `useRegisterForEvent()`, `useDashboard()`, `useMyPayouts()`, `useMyTeam()`, `useLeaveTeam()`, `useAdminUsers(params)`. Query keys are centralized in `queryKeys.ts`. Mutations invalidate the relevant keys.
- **`store/auth.ts`** (Zustand): `user`, `roles` and `hasRole()`, filled from `/auth/me` when the app starts.
- **Config**: `GET /auth/config` decides whether to show IITD login or dev login.
- All dates are shown in **IST** through `lib/date.ts`.

### 4.5 Frontend file structure

```
frontend/
├── public/                     # favicon, logo, og-image
├── src/
│   ├── main.tsx                # React root, QueryClientProvider, RouterProvider, Toaster
│   ├── router.tsx              # route tree with layouts and guards (lazy-loaded admin routes)
│   ├── api/
│   │   ├── client.ts
│   │   ├── types.ts            # generated
│   │   ├── auth.ts  users.ts  dashboard.ts  records.ts  events.ts  payouts.ts  icpc.ts  about.ts  notifications.ts  uploads.ts
│   │   └── admin/  users.ts  records.ts  events.ts  payouts.ts  icpc.ts  content.ts  audit.ts
│   ├── hooks/
│   │   ├── queries/            # TanStack Query hooks per domain
│   │   ├── queryKeys.ts
│   │   ├── useAuth.ts
│   │   └── useDebounce.ts
│   ├── store/
│   │   └── auth.ts
│   ├── layouts/
│   │   ├── PublicLayout.tsx
│   │   ├── DashboardLayout.tsx
│   │   └── AdminLayout.tsx
│   ├── pages/
│   │   ├── public/             # HomePage, EventsPage, EventDetailPage, AboutPage, ICPCGuidePage, LoginPage,
│   │   │                       # AuthCallbackPage, NotFoundPage, ForbiddenPage
│   │   ├── member/             # DashboardPage, EcaCaicPage, MyEventsPage, MyPayoutsPage, NewReimbursementPage,
│   │   │                       # ProfilePage, ICPCPage, ICPCDetailsPage, NotificationsPage
│   │   └── admin/              # AdminOverviewPage, AdminUsersPage, AdminUserDetailPage, BulkRecordsPage, CaicTotalsPage,
│   │                           # AdminEventsPage, EventEditorPage, EventRegistrationsPage,
│   │                           # PayoutsAdminPage,
│   │                           # ICPCAdminPage, ICPCTeamAdminPage, ICPCSeasonsPage, ICPCSeasonEditorPage,
│   │                           # RegistrationWorksheetPage, AboutAdminPage, AuditLogPage
│   ├── components/             # (see 4.3)
│   ├── lib/
│   │   ├── date.ts             # IST formatting, countdown helpers
│   │   ├── zod-from-fields.ts  # builds Zod schema from EventFormField[]
│   │   ├── payout-steps.ts     # step labels + student instructions per status
│   │   ├── roles.ts
│   │   └── utils.ts            # cn(), formatCurrency (₹), etc.
│   ├── styles/
│   │   └── globals.css         # Tailwind layers, theme tokens (light/dark)
│   └── tests/                  # Vitest + RTL tests
├── e2e/                        # Playwright: login (dev), register for event, add ECA/CAIC from results,
│                               # payout steps, ICPC invite → leave → details → freeze → verify → mark registered
├── index.html
├── vite.config.ts              # dev proxy /api → http://localhost:8000
├── tailwind.config.ts
├── tsconfig.json
└── package.json
```

### 4.6 UI and design notes

- **Brand**: club colours and logo. Dark mode is supported (developers like it). Code-style accents such as a monospace font for headings and terminal-like highlights fit the club's identity.
- **Mobile-first**: event cards stack, dashboard cards become a single column (profile first), and admin tables scroll horizontally with sticky first columns.
- **Accessibility**: Radix/shadcn primitives (keyboard and screen reader friendly), sufficient contrast, labelled form controls.
- **Feedback**: skeleton loaders, toasts for every mutation, and confirmation dialogs for destructive or important actions (leave team, revoke a record, reject a payout, change ICPC status, bulk actions).

---

## 5. Permission Matrix (summary)

| Action | member | coordinator | icpc_admin | finance | super_admin |
|---|:-:|:-:|:-:|:-:|:-:|
| View events / About / ICPC guide | ✓ | ✓ | ✓ | ✓ | ✓ |
| Register for events, edit own profile | ✓ | ✓ | ✓ | ✓ | ✓ |
| Create / join / leave ICPC team (before deadline) | ✓ | ✓ | ✓ | ✓ | ✓ |
| Submit reimbursement claim, track own payouts | ✓ | ✓ | ✓ | ✓ | ✓ |
| Create/edit events, forms, results, Drive photos | | ✓ | | | ✓ |
| View/export registrations | | ✓ | | | ✓ |
| Add/revoke ECA records and CAIC entries, CAIC totals | | ✓ | | | ✓ |
| Manage ICPC seasons, guide, teams, status, issues | | | ✓ | | ✓ |
| Registration worksheet, mark registered/accepted, export | | | ✓ | | ✓ |
| Move payouts through steps, mark bank details on file | | | | ✓ | ✓ |
| Assign roles, deactivate users, edit About content, audit log | | | | | ✓ |

---

## 6. Development Phases

| Phase | Scope | Outcome |
|---|---|---|
| **0. Setup** | Repo (monorepo `backend/` + `frontend/`), local Postgres, `Makefile`, CI, branch protection (only proelectro merges to `main`), lint/format, base layouts, shadcn setup | Skeleton app running locally |
| **1. Auth and users** | User/Role models, dev login, JWT cookies, `/auth/me`, profile page, onboarding; IITD OAuth integration (needs the client from CSC) | Login works in dev; IITD login ready to test |
| **2. Events (core)** | Event CRUD, events list and detail, form builder, dynamic registration, my registrations, CSV export | Coordinators can run an event end to end |
| **3. Dashboard, ECA and CAIC** | ECA records, CAIC entries with hostel snapshot, templates and points table, bulk from events and CSV, revoke, CAIC totals, admin users pages, dashboard aggregate | Members see ECA and CAIC; admins manage them |
| **4. Prizes & reimbursements** | Payout model and 4-step flow, claims with receipts, prizes from results, bank-details mail flow, finance queue, bulk advance | Students can track payouts |
| **5. ICPC** | Seasons + hard-coded sites, registration guide page, team creation, invites, leave, deadline freeze, ICPC details + checklist + eligibility check, readiness, issues loop, state machine, admin pages, registration worksheet + export, reminders, progress | ICPC season can run on the site with far fewer registration errors |
| **5b. ICPC autofill (optional)** | Playwright autofill tool, bulk upload of registered team IDs | Saves registration time, if icpc.global terms allow it |
| **6. Post-event and About** | Results, Google Drive photo gallery, recap; About page, team members, Jump Trading sponsor section, content admin | Public site complete |
| **7. Polish and launch** | Notifications (email and in-app), audit log UI, rate limits, end-to-end tests, performance, SEO/OG tags, `deploy/` scripts, backups, HTTPS | Production launch |

---

## 7. Open Questions / To Confirm

1. **IITD OAuth client**: who will request it from CSC, and what are the redirect URI and production domain (e.g., `ancc.iitd.ac.in`)? We need to confirm which fields the IITD resource endpoint returns (kerberos ID, name, department, category).
2. **CAIC scoring**: what points does each ANCC event give per position and for participation, and does CAIC want results in a specific format (per student or per hostel)? The points table is configurable per event, so this only affects the defaults and the export.
3. **ECA**: is there an official format or certificate that ECA records need to be exported to, or is the list on the dashboard enough?
4. **ICPC with the coach**:
   - Walk through this season's icpc.global team form to confirm the field order for the worksheet.
   - Who logs in to icpc.global to create teams: the coach, or the ICPC admin with the coach's agreement?
   - Is assisted autofill acceptable, and allowed by icpc.global's terms?
   - Who pays any regional fees: the team or the club?
5. **Payout timings**: is 21 days the right default for the accounts section? Which professor approves: always the faculty in-charge?
6. **Google Drive**: is the ANCC Drive a Google Workspace or Gmail account? We need a service account (or an API key with "anyone with the link" folders) to list photos.
7. **Hosting**: an IITD server or an external VPS, and is SSH from GitHub Actions allowed for automatic deploys?
8. **Past data**: should past events, results, ECA records and team members be imported at launch? A seed or import script can be written for this.
