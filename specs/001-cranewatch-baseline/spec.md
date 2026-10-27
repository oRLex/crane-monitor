# Feature Specification: CraneWatch Platform Baseline

**Feature Branch**: `001-cranewatch-baseline`

**Created**: 2026-10-27

**Status**: Draft

**Input**: User description: "generate a specity kit on whole project"

> **Scope note**: This is a *baseline* specification. It describes the product CraneWatch already is (live monitoring of tower cranes, alarms, video, documents, an AI assistant and shift reports for several customer organisations), so later feature specs can refer to it and extend it. It records the current behaviour; it does not propose new behaviour.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Watch the fleet live (Priority: P1)

A control-room operator opens CraneWatch and sees every crane they are responsible for on one overview: each crane's status (operating, idle, in alarm, offline), current wind, load and load moment. Values update on their own while the page is open. Selecting a crane opens a detail view with its live readings, recent history and, where permitted, its site camera.

**Why this priority**: Live situational awareness is the reason the product exists; every other capability builds on knowing what each crane is doing now.

**Independent Test**: Sign in as an operator with telemetry flowing, open the overview and confirm that every crane appears with a status and that the readings change without reloading the page.

**Acceptance Scenarios**:

1. **Given** an operator is signed in and cranes are reporting, **When** they open the fleet overview, **Then** they see every crane they may access, each with its current status and key readings.
2. **Given** the overview is open, **When** a crane reports new readings, **Then** the displayed values and status update without a page reload.
3. **Given** a crane stops reporting, **When** the next status evaluation runs, **Then** that crane is shown as offline.
4. **Given** a user who may view video opens a crane's detail, **When** the crane has a camera, **Then** the live video is shown; **Given** a user who may not view video, **Then** no video and no video address are shown or delivered.

---

### User Story 2 - Get alerted to unsafe conditions and acknowledge them (Priority: P1)

When wind or load reaches unsafe levels, or a crane loses its connection, the system raises an alarm with a severity. People with the right permission acknowledge the alarm to show it is being handled. Alarms clear on their own when the condition ends, and they escalate if the condition gets worse.

**Why this priority**: Safety alerting is the core business value; a missed overload or high-wind event can cause an accident.

**Independent Test**: Feed readings that cross each threshold and confirm the alarm is raised, then escalated or cleared; acknowledge it as a permitted user and confirm that a viewer cannot.

**Acceptance Scenarios**:

1. **Given** wind reaches 14 m/s, **When** the reading is processed, **Then** a *warning* wind alarm is raised; at 20 m/s or above it is *critical*.
2. **Given** load moment reaches 90 % of rated, **When** processed, **Then** a *warning* overload alarm is raised; at 100 % or above it is *critical*.
3. **Given** a crane loses its telemetry link, **When** processed, **Then** a *critical* offline alarm is raised.
4. **Given** an open warning alarm, **When** the same condition worsens to critical, **Then** the warning is cleared and a new critical alarm is raised.
5. **Given** an open alarm, **When** the condition no longer holds, **Then** the alarm is cleared automatically.
6. **Given** a user with acknowledge permission, **When** they acknowledge an open alarm, **Then** the alarm records who acknowledged it and when; **Given** a user without it, **Then** the action is refused.

---

### User Story 3 - Each customer sees only their own data (Priority: P1)

CraneWatch serves several customer organisations (tenants). Customer users see only their own organisation's sites, cranes, alarms, documents, reports and AI answers. Platform staff (admin, operator) see all tenants. What each person may *do* is decided by permissions attached to their role, and can be narrowed further for an individual access token.

**Why this priority**: Any data shared across tenants is a contractual and security failure; the product cannot ship without this.

**Independent Test**: Sign in as each demo role and confirm the set of visible cranes, available actions and AI tools matches the role matrix; asking for another tenant's record by ID returns "not found".

**Acceptance Scenarios**:

1. **Given** a customer user, **When** they list cranes, alarms, documents or reports, **Then** only their own tenant's records appear.
2. **Given** a customer user, **When** they request another tenant's record directly, **Then** the system responds as if it does not exist (not "forbidden").
3. **Given** a platform operator or admin, **When** they list cranes, **Then** cranes of all tenants appear.
4. **Given** a token issued with fewer permissions than the user's role, **When** it is used, **Then** only the intersection of role and token permissions is allowed.
5. **Given** live updates are pushed to browsers, **When** a customer user subscribes, **Then** they receive updates only for their own tenant.

---

### User Story 4 - Ask the AI assistant about the fleet (Priority: P2)

From any page, a user opens an assistant panel and asks questions in plain language: "Which cranes are in alarm?", "Why did TC-2 trip at 14:00?", "What does the manual say about wind limits?". The assistant answers from live data and documents the user is allowed to see. It cites document passages it relied on, and it streams its answer as it writes. Every alarm has an "Explain" action that opens the assistant with that alarm as context. The assistant can propose acknowledging an alarm, but the user must confirm with a click.

**Why this priority**: The assistant is the main differentiator and shortens investigation time, but the product is still usable without it.

**Independent Test**: As each role, ask a fleet question and a documentation question; confirm that answers use only that user's data and cite sources, and that the assistant never acknowledges an alarm without the user's confirmation.

**Acceptance Scenarios**:

1. **Given** a user with AI permission, **When** they ask about fleet status, **Then** the answer reflects current data the user may see.
2. **Given** a documentation question, **When** answered, **Then** the answer cites the document passages used and each citation links to its source section.
3. **Given** a user lacking a permission (e.g. acknowledging alarms), **When** they use the assistant, **Then** the assistant is not offered capabilities requiring that permission.
4. **Given** the assistant suggests acknowledging an alarm, **When** the user has not confirmed, **Then** no change is made.
5. **Given** a user returns later, **When** they open the assistant, **Then** their earlier conversations are listed and can be continued until a conversation reaches its maximum length (60 messages).

---

### User Story 5 - Generate a shift report (Priority: P2)

A manager or operator requests a report for a crane covering the last N hours (1–24, default 8). The report shows exact figures: lifts, operating and offline time, peak load, load moment and wind, alarm counts by kind, median time to acknowledge, and notable alarms. It adds a written summary, the risks observed and recommendations. Figures are always calculated from recorded data, never estimated by the AI. Asking again for the same inputs returns the earlier report instead of generating a new one.

**Why this priority**: Shift reports are a recurring deliverable for site managers; high value, but not needed for real-time safety.

**Independent Test**: Request a report for a crane and period with known history; confirm every figure matches the recorded data and that the narrative mentions no event that did not happen.

**Acceptance Scenarios**:

1. **Given** a user with report permission, **When** they request a report, **Then** it is produced in the background and shown when ready, with its status visible meanwhile.
2. **Given** a completed report, **When** inspected, **Then** every figure equals the value calculated from recorded telemetry and alarms.
3. **Given** the AI narrative references an event absent from the facts, **When** validated, **Then** the report is rejected rather than published.
4. **Given** an identical request (same facts, same prompt version, same model), **When** submitted, **Then** the existing report is reused.
5. **Given** a viewer without report-write permission, **When** they try to create a report, **Then** the action is refused, though they may read existing reports.

---

### User Story 6 - Manage manuals and site documents (Priority: P3)

The platform keeps a shared library of crane manuals. Customer managers can upload their own documents (text or Markdown, up to 512 KB), which only their organisation can search or retrieve. Uploaded documents are indexed in the background and show a status (pending, ready, failed). Users can browse documents, read them, search them, and delete ones they own.

**Why this priority**: Documents make the assistant's answers grounded and site-specific, but the core monitoring flow does not depend on them.

**Independent Test**: As a customer manager, upload a document, wait for it to become ready, find it in search and assistant answers; confirm a user from another tenant cannot find it.

**Acceptance Scenarios**:

1. **Given** a customer manager uploads a document, **When** indexing finishes, **Then** its status becomes ready and its content is searchable by their tenant only.
2. **Given** an admin uploads a document, **When** indexed, **Then** it joins the shared library visible to all tenants.
3. **Given** indexing fails, **When** the user views the document list, **Then** the document shows a failed status and the reason.
4. **Given** a user without document-write permission, **When** they attempt to upload or delete, **Then** the action is refused.

---

### User Story 7 - Oversee AI usage and cost (Priority: P3)

A platform admin opens an AI usage page showing, per tenant and over time: tokens used, cache efficiency, estimated cost, latency, and how calls ended (completed, refused, cut off). Each tenant has a daily token budget, and each user has a per-minute rate limit on AI requests.

**Why this priority**: Keeps the AI features financially sustainable and observable; an internal concern rather than a customer-facing one.

**Independent Test**: Make AI requests as several tenants, then open the usage page as admin and confirm the totals match; exhaust a tenant's daily budget and confirm further AI requests for that tenant are declined politely.

**Acceptance Scenarios**:

1. **Given** an admin, **When** they open AI usage, **Then** they see per-tenant usage, cost, cache hit rate and latency; non-admins cannot open it.
2. **Given** a tenant has used its daily token budget, **When** a user of that tenant makes an AI request, **Then** it is declined with a clear message until the budget resets.
3. **Given** a user sends more than 10 AI requests in a minute, **When** the next request arrives, **Then** it is throttled.

---

### User Story 8 - Use CraneWatch from external AI clients (Priority: P3)

A user connects an external AI client (for example a desktop or command-line assistant) to CraneWatch using a personal access token. The client gets the same tools as the in-app assistant, under the same permissions and tenant limits. A narrower token exposes fewer tools.

**Why this priority**: An integration channel for power users; it reuses existing capabilities.

**Independent Test**: Issue a read-only token for a customer user, connect a client and confirm it sees only read tools and only that tenant's data.

**Acceptance Scenarios**:

1. **Given** a read-only token, **When** an external client lists tools, **Then** only read tools are offered.
2. **Given** any token, **When** a tool is called, **Then** results are limited exactly as they would be in the in-app assistant for that user.

---

### Edge Cases

- **Wind hovering around a threshold** raises and clears alarms repeatedly. Hysteresis is a known limitation and out of scope for this baseline.
- **No AI provider configured**: all AI features still work in a deterministic fallback mode that answers from the same tools and data; document search still works with fallback indexing.
- **AI provider refuses or truncates** a response: the user gets a clear fallback message instead of a broken answer, and the outcome is recorded in usage.
- **Assistant conversation reaches 60 messages**: the user is asked to start a new conversation.
- **Report period without data** (crane offline the whole time): figures show zero or "not available"; the narrative must not invent activity.
- **Live connection drops in the browser**: the UI shows it is not live and recovers when the connection returns.
- **Document upload exceeding limits or of an unsupported type**: rejected with a validation message.
- **User signs out while a background job runs for them**: the job completes in that user's tenant context and does not leak into another tenant's data.

## Requirements *(mandatory)*

### Functional Requirements

**Identity & access**

- **FR-001**: System MUST authenticate users and issue them access tokens; tokens MAY be issued with a subset of the user's permissions.
- **FR-002**: System MUST support four roles: platform admin, control-room operator, customer manager, customer viewer. Each maps to a fixed set of permissions (see Key Entities: Role).
- **FR-003**: System MUST decide every action by permission (role permissions intersected with token permissions), never by role name.
- **FR-004**: System MUST restrict users without the all-tenants permission to their own tenant's data across every list, detail, search, live update, AI answer, report and document.
- **FR-005**: System MUST respond to a request for another tenant's record exactly as for a non-existent record.
- **FR-006**: System MUST never deliver a camera stream address to a user without video permission.
- **FR-007**: System MUST limit sign-in attempts (20 per minute) and general API use (120 requests per minute per user).

**Telemetry & live monitoring**

- **FR-008**: System MUST ingest crane readings (online state, wind speed, load, radius, load moment as % of rated) continuously from the site.
- **FR-009**: System MUST derive a crane status by priority: offline > alarm > operating (load > 0.2 t) > idle.
- **FR-010**: System MUST push live readings, status changes and alarm changes to connected users of the affected tenant, and to platform users, without page reloads.
- **FR-011**: System MUST keep hourly aggregates per crane (lifts, operating and offline time, peak load, peak load moment, peak and average wind) for history and reports.
- **FR-012**: System MUST provide a snapshot of current fleet state for clients that connect mid-stream.

**Alarms**

- **FR-013**: System MUST raise wind alarms at ≥ 14 m/s (warning) and ≥ 20 m/s (critical); overload alarms at ≥ 90 % (warning) and ≥ 100 % (critical) of rated load moment; and a critical offline alarm on link loss.
- **FR-014**: System MUST escalate an open alarm when its condition worsens (clear the old alarm, raise the higher-severity one) and clear alarms automatically when the condition ends.
- **FR-015**: Users with acknowledge permission MUST be able to acknowledge an alarm; the system records who acknowledged it and when.
- **FR-016**: Users MUST be able to list alarms filtered to what they may see, with kind, severity, message, raised/cleared/acknowledged times.

**AI assistant**

- **FR-017**: Users with AI permission MUST be able to hold persistent conversations with an assistant, available on every page, with answers streamed as they are generated.
- **FR-018**: The assistant MUST act only through a defined set of capabilities (fleet status, crane status, hourly statistics, alarm list, document search, alarm acknowledgement), each executed with the requesting user's permissions and tenant limits.
- **FR-019**: The assistant MUST NOT be offered a capability the user lacks permission for.
- **FR-020**: Any state-changing action proposed by the assistant MUST require explicit user confirmation.
- **FR-021**: Answers based on documents MUST cite the passages used, and each citation MUST link to its source section.
- **FR-022**: Conversation history MUST be append-only; conversations are capped at 60 messages.
- **FR-023**: Each alarm MUST offer an "Explain" entry point that opens the assistant with that alarm as context.

**Knowledge base**

- **FR-024**: System MUST provide a shared document library visible to all tenants and private per-tenant document collections.
- **FR-025**: Users with document-write permission MUST be able to upload Markdown or text documents (title ≤ 160 characters, file ≤ 512 KB or pasted text ≤ 200,000 characters) and delete documents in their scope.
- **FR-026**: Uploaded documents MUST be indexed in the background, repeatably without duplication, with visible status (pending / ready / failed + reason).
- **FR-027**: Document search MUST combine meaning-based and keyword matching and return only documents the user may see; queries are limited to 500 characters.

**Shift reports**

- **FR-028**: Users with report-write permission MUST be able to request a shift report for a crane over the last 1–24 hours (default 8); users with telemetry-read permission MUST be able to list and read reports.
- **FR-029**: All figures in a report MUST be calculated from recorded data; the AI MUST only write summary, risks and recommendations.
- **FR-030**: Report narratives MUST be validated against the calculated facts; a narrative referencing events not in the facts MUST be rejected.
- **FR-031**: An identical request (same facts, prompt version and model) MUST reuse the existing report.

**AI governance**

- **FR-032**: System MUST record every AI call: tenant, user, tokens, cache reads, estimated cost, latency, outcome.
- **FR-033**: System MUST enforce a daily AI token budget per tenant (default 400,000) and a per-user AI rate limit (10 requests per minute).
- **FR-034**: Platform admins MUST be able to view AI usage, cost, cache efficiency, latency and outcomes per tenant.
- **FR-035**: All AI features MUST remain functional, in a deterministic fallback mode, when no external AI or embedding provider is configured.
- **FR-036**: System MUST provide an offline quality evaluation (retrieval accuracy and assistant scenarios) that can be run before changing prompts, capabilities, document indexing or models.

**External AI clients**

- **FR-037**: System MUST expose the assistant's capabilities to external AI clients over a standard tool protocol, authenticated by the user's access token, with identical permission and tenant behaviour.

### Key Entities *(include if feature involves data)*

- **Tenant**: A customer organisation. Owns sites, cranes, users, private documents, reports, conversations and AI usage.
- **Site**: A construction site belonging to a tenant; groups cranes.
- **Crane**: A tower crane at a site. Name, model, rated load moment, maximum load, optional camera; current status derived from its latest readings.
- **Telemetry reading**: A point-in-time measurement from a crane: online, wind, load, radius, load moment %. Kept as a short live window, plus **hourly aggregates** for history.
- **Alarm**: A raised condition on a crane. Kind (wind / overload / offline), severity (warning / critical), message, raised, cleared and acknowledged times, acknowledging user.
- **User**: A person with exactly one role. Belongs to a tenant unless they are platform staff.
- **Role**: A named bundle of permissions:
  - *Admin*: all tenants, read everything, video, acknowledge, write documents, write reports, AI administration.
  - *Operator*: all tenants, read everything, video, acknowledge, write reports.
  - *Customer manager*: own tenant, read everything, video, acknowledge, write documents, write reports.
  - *Customer viewer*: own tenant, read only, no video.
  - All roles may use the AI assistant.
- **Permission**: An atomic ability (read cranes, read telemetry, view video, read alarms, acknowledge alarms, read documents, write documents, write reports, use AI, administer AI, access all tenants). Tokens carry a subset.
- **Document / Document passage**: A manual or site document (shared or tenant-private), split into sections for search and citation; has indexing status.
- **Conversation / Message**: A user's assistant dialogue; append-only messages including the capabilities used.
- **Shift report**: Crane, period, calculated facts, AI narrative (summary, risks, recommendations), status, and a fingerprint of the inputs used for reuse.
- **AI usage record**: One AI call's tenant, user, purpose, tokens, cache reads, cost, latency and outcome.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A change in crane readings is visible to watching users within 2 seconds of being received from the site.
- **SC-002**: 100 % of threshold crossings in a recorded test sequence produce the expected alarm (raise, escalate, clear) with the correct severity.
- **SC-003**: Zero cross-tenant disclosures across the full role × tenant test matrix (lists, details, search, live updates, AI answers, external client).
- **SC-004**: 100 % of figures in generated shift reports match the values calculated independently from recorded data.
- **SC-005**: Users see the start of an assistant answer within 3 seconds for 95 % of questions.
- **SC-006**: In the golden evaluation set, the correct document passage appears among the top 5 search results for at least 90 % of questions.
- **SC-007**: An operator can go from seeing an alarm to acknowledging it in under 10 seconds.
- **SC-008**: Repeated requests for an identical shift report cost no additional AI usage.
- **SC-009**: The demo, the automated tests and the evaluation all complete successfully with no external AI provider configured.

## Assumptions

- Telemetry arrives from an edge gateway or the bundled simulator; integrating real crane hardware is out of scope.
- Sign-in uses the built-in demo method; corporate single sign-on is a planned next step and out of scope.
- Video is delivered as an existing live stream per crane; CraneWatch shows it but does not record or process it.
- Raw high-frequency telemetry is kept only as a short live window; long-term history is the hourly aggregates.
- Alarm thresholds are fixed platform-wide (not per crane or per tenant).
- Uploaded documents are text or Markdown; PDFs and images are out of scope.
- The daily AI budget resets each calendar day (server time).
- One telemetry processor runs per environment; scaling it out is out of scope.
- Web browsers on desktop are the primary target; a dedicated mobile app is out of scope.
