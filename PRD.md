# Product Requirements Document (PRD)

## GeoTime — Geofence-Based Employee Time Tracking & Workforce Management

**Version:** 1.0  
**Author:** AZ Team  
**Date:** March 25, 2026  
**Status:** Draft

---

## 1. Executive Summary

GeoTime is a mobile-first time tracking application that uses GPS geofencing to automatically clock employees in and out when they enter or leave a job site — without requiring internet connectivity. The platform supports team management, multi-job assignments, employee transfers with categorized reasoning, and full QuickBooks Online integration for bank feeds, invoicing, and estimates.

**Core Differentiator:** Fully offline geofence-based auto clock-in/out with background OS-level tracking, eliminating manual time entry and buddy punching.

---

## 2. Problem Statement

Small to mid-size businesses with field teams, multiple job sites, or hourly employees face three interconnected problems:

1. **Inaccurate time tracking** — Manual punch systems lead to time theft, buddy punching, and human error. Employers risk FLSA non-compliance penalties (up to $2,515 per willful violation as of 2025).
2. **No visibility into team deployment** — Managers cannot see which employees are at which job site in real time, making workforce allocation reactive instead of strategic.
3. **Disconnected financial workflows** — Time data lives in one system, invoicing in another, and bank transactions in a third. This creates reconciliation overhead and delays payroll.

---

## 3. Target Users

| Persona | Description | Primary Needs |
|---------|-------------|---------------|
| **Field Employee** | Hourly worker at one or more job sites | Effortless clock-in, view own hours, request time off |
| **Team Lead** | Manages 5–20 direct reports on-site | See team attendance, approve timesheets, flag issues |
| **Operations Manager** | Oversees multiple teams/jobs | Assign teams to jobs, transfer employees, run reports |
| **Business Owner / Admin** | Company owner or office manager | Payroll processing, invoicing, financial overview, FLSA compliance |

---

## 4. Product Architecture

### 4.1 System Overview

```
┌─────────────────────────────────────────────────────────┐
│                    MOBILE APP (React Native)             │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐              │
│  │Geofence  │  │ Offline  │  │  Employee │              │
│  │Engine    │  │ Storage  │  │  UI       │              │
│  └────┬─────┘  └────┬─────┘  └──────────┘              │
│       │              │                                   │
│       └──────┬───────┘                                   │
│              │ Sync when online                          │
└──────────────┼───────────────────────────────────────────┘
               │
               ▼
┌──────────────────────────────────────────────────────────┐
│                   BACKEND (Supabase)                      │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐               │
│  │ Auth     │  │ Postgres │  │ Realtime │               │
│  │ (OAuth)  │  │ Database │  │ Subscr.  │               │
│  └──────────┘  └──────────┘  └──────────┘               │
│                      │                                    │
│              ┌───────┴───────┐                            │
│              ▼               ▼                            │
│  ┌──────────────┐   ┌──────────────┐                     │
│  │  QBO API     │   │  Admin       │                     │
│  │  Integration │   │  Dashboard   │                     │
│  │  (Node.js)   │   │  (Next.js)   │                     │
│  └──────────────┘   └──────────────┘                     │
└──────────────────────────────────────────────────────────┘
```

### 4.2 Tech Stack

| Layer | Technology | Justification |
|-------|-----------|---------------|
| Mobile App | React Native + Expo | Single codebase for iOS/Android, large ecosystem |
| Geofencing | `react-native-background-geolocation` (Transistor Software) | OS-level geofencing, offline support, battery-optimized |
| Local DB | WatermelonDB | High-performance offline-first reactive database |
| Backend | Supabase (PostgreSQL + Auth + Realtime + Edge Functions) | Open-source, real-time subscriptions, row-level security |
| API Layer | Supabase Edge Functions (Deno) + Node.js microservice for QBO | Serverless, auto-scaling |
| Admin Dashboard | Next.js 14 (App Router) | SSR, file-based routing, React Server Components |
| QuickBooks Integration | Intuit QBO API (OAuth 2.0) + Codat/Rutter for Bank Feeds | Direct API for invoices/estimates; middleware required for bank feeds |
| Push Notifications | Firebase Cloud Messaging (FCM) | Cross-platform, free tier |
| File Storage | Supabase Storage | Selfie photos for clock verification, exports, attachments |

---

## 5. Feature Requirements

### 5.1 Geofence Engine (P0 — Core Differentiator)

#### 5.1.1 Geofence Configuration
- Admin defines geofence zones per job site with center coordinates (lat/lng) and radius (50m–500m configurable)
- Support for up to 20 simultaneous geofences per employee (OS limit)
- Geofences sync to device on app install and update silently when changed server-side
- Visual map interface for admins to draw/adjust geofence boundaries

#### 5.1.2 Auto Clock-In/Out
- When employee's device crosses INTO a geofence boundary, the OS triggers a clock-in event
- When employee's device crosses OUT of the geofence boundary, the OS triggers a clock-out event
- Events are timestamped (UTC) and stored locally with GPS coordinates
- Works without internet — GPS is satellite-based, no data connection required
- Runs as a background service at the OS level (survives app kill)

#### 5.1.2.1 Clock Verification Modes

Business owners can configure one of two verification modes at the tenant level:

| Mode | Behavior |
|------|----------|
| **Auto-Only** (default) | Geofence entry/exit triggers clock-in/out automatically. No additional confirmation required. |
| **Auto + Photo** | Geofence entry/exit triggers clock-in/out immediately, but the entry is flagged as `unverified`. The employee's phone vibrates and displays a push notification prompting them to open the app and take a selfie. Once submitted, the entry is marked `verified`. If no photo is submitted, the entry remains `unverified` for admin review. |

**Business Rules:**
- Mode is configured per tenant (all employees in the company use the same mode)
- Unverified entries are valid time records — they are not held or delayed
- Admins can view and filter unverified entries in the timesheet approval workflow
- Photos are stored in S3-compatible storage (DigitalOcean Spaces) with a retention policy matching time entry retention

#### 5.1.3 Offline-First Sync
- All time entries stored locally in WatermelonDB with sync status flag (`pending`, `synced`, `conflict`)
- When internet becomes available, pending entries push to Supabase
- Conflict resolution: server timestamp wins; conflicts flagged for admin review
- Sync indicator visible to employee ("Last synced: X minutes ago")

#### 5.1.4 Anti-Fraud Measures
- GPS coordinates captured at every clock event (stored as evidence)
- Selfie photo capture at clock-in/out (configurable per tenant via Auto + Photo mode). Photos stored as evidence alongside GPS coordinates. Facial recognition deferred to V2.
- Device binding: one primary device per employee (configurable by admin)
- Anomaly detection: flag if clock-in location is outside geofence (edge cases with GPS drift)
- Admin audit log of all manual time edits

#### 5.1.5 iOS/Android Considerations
- **iOS:** Requires "Always Allow" location permission. App Store review justification: "Employee time tracking for FLSA compliance requires background location to log work hours at designated job sites."
- **Android:** Uses `ACCESS_BACKGROUND_LOCATION` permission. Battery optimization whitelist prompt on first launch.
- Both: Graceful degradation if user denies background location — falls back to manual clock-in with GPS verification at punch time.

---

### 5.2 Team Management (P0)

#### 5.2.1 Team Structure
- **Company** → has many **Teams** → has many **Employees**
- Each team has one Team Lead and one or more members
- Teams are assigned to one or more **Jobs** (job sites)
- An employee belongs to exactly one team at any given time (history preserved)

#### 5.2.2 Team CRUD
- Create team with name, description, color tag, default job assignment
- Assign Team Lead (elevated permissions within team scope)
- Add/remove members
- Archive team (soft delete, preserves historical data)

#### 5.2.3 Employee Transfer (Team Swap)

When an employee is moved from one team to another, the system requires a **categorized reason** from the transfer history taxonomy:

| Category | Reason Codes | Description |
|----------|-------------|-------------|
| **Operational** | `WORKLOAD_BALANCE` | Redistributing headcount across teams |
| | `SKILL_MATCH` | Employee's skills better fit the target team |
| | `PROJECT_NEED` | Temporary or permanent need on a specific project |
| | `LOCATION_CHANGE` | Employee relocated or job site changed |
| **Performance** | `PERFORMANCE_IMPROVEMENT` | Move to a team better suited for development |
| | `PROMOTION` | Role change requiring different team |
| | `MENTOR_ASSIGNMENT` | Paired with a senior team member |
| **Employee Request** | `PERSONAL_REQUEST` | Employee initiated the transfer |
| | `SCHEDULE_ACCOMMODATION` | Better schedule fit on target team |
| | `CONFLICT_RESOLUTION` | Interpersonal issue requiring separation |
| **Administrative** | `TEAM_RESTRUCTURE` | Org-wide restructuring |
| | `TEAM_DISSOLUTION` | Source team being shut down |
| | `SEASONAL_ADJUSTMENT` | Seasonal staffing needs |
| | `OTHER` | Free-text required when selected |

**Transfer Record Fields:**
- `employee_id`
- `from_team_id`
- `to_team_id`
- `reason_category` (enum from above)
- `reason_code` (enum from above)
- `notes` (optional free text, required if `OTHER`)
- `effective_date`
- `initiated_by` (user ID of the admin/manager)
- `transfer_type` (`PERMANENT` | `TEMPORARY`)
- `expected_return_date` (if temporary)

**Business Rules:**
- Only Admins and Operations Managers can initiate transfers
- Team Leads can _request_ transfers (requires admin approval)
- Transfer history is immutable — no deletions, only new entries
- Active geofences update automatically when team assignment changes (if team is tied to a different job site)
- Temporary transfers auto-revert on `expected_return_date` with system notification

---

### 5.3 Job / Job Site Management (P0)

#### 5.3.1 Job Definition
- **Job** represents a project, client site, or work location
- Fields: `name`, `client_name`, `address`, `geofence_id`, `status` (active/completed/on-hold), `start_date`, `end_date`, `budget_hours`, `hourly_rate`
- One job can have one or more geofence zones (e.g., large campus with multiple entry points)
- Teams are assigned to jobs; individual employees inherit the assignment

#### 5.3.2 Multi-Job Support
- An employee can be assigned to multiple jobs simultaneously
- When employee enters a geofence, the system matches it to the correct job
- If geofences overlap, system prompts employee to select which job they're working on

#### 5.3.3 Job Costing
- Track hours per job → calculate labor cost (hours × employee hourly rate)
- Compare actual hours vs. budgeted hours per job
- Feed into QuickBooks estimates and invoices

---

### 5.4 Time Tracking Features (P0)

#### 5.4.1 Clock Methods
1. **Auto (Geofence)** — Primary method, fully automatic
2. **Manual** — Employee taps clock-in/out button (GPS captured for verification)
3. **Kiosk Mode** — Shared tablet at job site, employee enters PIN or uses facial recognition

#### 5.4.2 Break Tracking
- Configurable break types: `PAID_REST` (≤20 min), `UNPAID_MEAL` (≥30 min)
- Auto-deduct meal breaks (configurable: 30 min after 6 hours worked)
- Employee can manually start/end breaks
- Break interruption handling: if employee clocks activity during meal break, break is voided and counts as worked time
- FLSA compliance: system warns if meal break is less than 30 minutes uninterrupted

#### 5.4.3 Overtime Calculation
- Automatic calculation: hours > 40/week = 1.5× rate
- Configurable for state-specific rules (e.g., California daily overtime > 8 hours)
- Real-time alerts: push notification to employee and manager at 35, 38, and 40 hours
- Overtime authorization workflow (optional): require manager pre-approval for overtime

#### 5.4.4 Time Rounding
- Configurable rounding intervals: exact (no rounding), nearest 5 min, nearest 6 min (1/10 hour), nearest 15 min
- Rounding must be neutral (FLSA compliant — cannot consistently favor employer)
- Rounding applied at display/payroll level; raw timestamps always preserved

#### 5.4.5 Timesheet Management
- Weekly timesheet view per employee
- Editable by employee (with reason required for changes)
- All edits create audit trail entries
- Approval workflow: Employee submits → Team Lead reviews → Admin approves → Payroll ready

#### 5.4.6 PTO / Time Off
- Request time off (type: vacation, sick, personal, unpaid)
- Approval workflow: Employee requests → Manager approves/denies
- PTO balance tracking (accrual rules configurable)
- Calendar view of team availability

---

### 5.5 FLSA Compliance Module (P0)

#### 5.5.1 Required Records (per non-exempt employee)
The system must store and make available:
- Full name and Social Security Number (encrypted at rest, AES-256)
- Home address including ZIP code
- Date of birth (if under 19)
- Sex and occupation
- Workweek start day and time
- Daily hours worked
- Total hours worked each workweek
- Pay basis and regular hourly rate
- Straight-time earnings per pay period
- Overtime earnings per pay period
- Additions to or deductions from wages
- Total wages paid each pay period
- Pay period dates

#### 5.5.2 Record Retention
- Time records: retained minimum 2 years (configurable up to 7)
- Payroll records: retained minimum 3 years
- Records exportable at any time for DOL inspection
- Automated data retention policy with archival to cold storage

#### 5.5.3 Compliance Alerts
- Missing clock-out (employee entered geofence but no exit detected)
- Missed meal break (worked > 6 hours without 30-min break)
- Overtime threshold approaching
- Off-the-clock work detected (activity outside scheduled shift from GPS)
- Employee classification mismatch warning

---

### 5.6 Employee Self-Service (P1)

#### 5.6.1 Mobile Dashboard
- Today's status: clocked in/out, current hours, break status
- Weekly hours summary with visual progress bar toward 40 hours
- Historical timesheets (searchable by date range)
- Active job assignment and team info
- PTO balance and request history

#### 5.6.2 Notifications
- Clock-in/out confirmation
- Break reminder
- Overtime approaching (35h, 38h, 40h)
- Timesheet approval status
- Transfer notification
- Schedule changes

#### 5.6.3 Missed Punch Resolution
- Employee can request a missed punch correction
- Must include: date, time, reason, supporting notes
- Routed to manager for approval
- Audit trail created regardless of approval/denial

---

### 5.7 Admin Dashboard — Web (P1)

#### 5.7.1 Real-Time Overview
- Map view: all active geofences with employee pins (who's clocked in where)
- Team status cards: each team showing clocked-in count, break count, absent count
- Alerts panel: compliance warnings, missed punches, overtime flags
- Today's activity feed (clock events, approvals, transfers)

#### 5.7.2 Reports
| Report | Description | Export Formats |
|--------|-------------|----------------|
| Payroll Summary | Hours, overtime, PTO, gross wages per employee per pay period | CSV, PDF, QBO sync |
| Attendance Report | Daily clock-in/out times, lates, absences, early departures | CSV, PDF |
| Overtime Report | Employees approaching or exceeding 40h/week | CSV, PDF |
| Job Costing Report | Hours and labor cost per job/client | CSV, PDF, QBO sync |
| Team Utilization | Hours per team, capacity analysis | CSV, PDF |
| Transfer History | All employee transfers with reasons, dates, approvers | CSV, PDF |
| Compliance Audit | All FLSA-required records for a date range | CSV, PDF |
| Geofence Activity | Clock events with GPS coordinates and geofence match data | CSV |

#### 5.7.3 User Management
- CRUD employees with: name, email, phone, role, team, hourly rate, start date, SSN (encrypted)
- Role-based access: Employee, Team Lead, Manager, Admin, Super Admin
- Bulk import via CSV
- Employee onboarding flow: invite → download app → set permissions → assign team/job

---

### 5.8 QuickBooks Online Integration (P1)

#### 5.8.1 Authentication
- OAuth 2.0 flow via Intuit Developer Portal
- Admin connects QBO account from the admin dashboard
- Token refresh handled automatically (access tokens expire every 60 min)
- Sandbox environment for testing before production go-live

#### 5.8.2 Bank Feeds

**Important Technical Constraint:** QuickBooks Online does not expose uncategorized bank feed transactions via their API. Direct bank feed access requires partnership through middleware providers (Codat or Rutter). The integration works as follows:

**Architecture:**
```
┌──────────┐      ┌──────────┐      ┌──────────┐
│ GeoTime  │─────▶│  Codat   │─────▶│   QBO    │
│ Backend  │      │  or      │      │  Bank    │
│          │◀─────│  Rutter   │◀─────│  Feeds   │
└──────────┘      └──────────┘      └──────────┘
```

**Capabilities via Middleware (Codat/Rutter):**
- Push transactions from GeoTime into QBO as bank feed entries
- Transactions appear in the QBO "For Review" tab for user categorization
- Supported data per transaction: date, amount, description, type (debit/credit)
- Daily sync schedule (QBO default), with manual refresh available in QBO

**Capabilities via Direct QBO API:**
- Read categorized/matched transactions (already processed by the user in QBO)
- Query transaction history for reporting
- Reconciliation status check

**What Is NOT Possible:**
- Pulling raw, uncategorized bank feed line items from QBO via API
- Auto-categorizing transactions within QBO from an external app (user must do this in QBO)
- Bypassing Plaid/Yodlee bank connection layer

**Implementation Plan:**
- Phase 1: Integrate via Rutter Bank Feeds API (single integration covers QBO + QBO Desktop + Quicken)
- Phase 2: Build transaction push pipeline — labor costs from GeoTime payroll → QBO bank feeds
- Phase 3: Reconciliation dashboard showing pushed vs. matched transactions

#### 5.8.3 Estimates

**Direct QBO API — Full CRUD Support**

| Operation | Endpoint | Description |
|-----------|----------|-------------|
| Create | `POST /v3/company/{id}/estimate` | Generate estimate from job data |
| Read | `GET /v3/company/{id}/estimate/{id}` | Retrieve estimate details |
| Update | `POST /v3/company/{id}/estimate` | Modify existing estimate |
| Delete | `POST /v3/company/{id}/estimate` (set Active=false) | Soft delete |
| Query | `GET /v3/company/{id}/query?query=SELECT * FROM Estimate` | List/filter estimates |

**GeoTime → QBO Estimate Flow:**
1. Admin creates a job in GeoTime with budgeted hours and hourly rate
2. Admin clicks "Generate Estimate" → system calculates: `budgeted_hours × rate = estimated_total`
3. Estimate pushed to QBO via API with line items mapped to QBO service items
4. Estimate appears in QBO for client delivery
5. Status syncs back: Pending → Accepted → Converted to Invoice

**Estimate Fields Mapped:**
- `CustomerRef` → Job client (synced from QBO customer list)
- `Line[]` → Service line items (labor hours per team/role)
- `TxnDate` → Estimate creation date
- `ExpirationDate` → Configurable (default: 30 days)
- `BillEmail` → Client email for delivery
- `CustomField[]` → Job name, GeoTime job ID for reference

#### 5.8.4 Invoices

**Direct QBO API — Full CRUD Support**

| Operation | Endpoint | Description |
|-----------|----------|-------------|
| Create | `POST /v3/company/{id}/invoice` | Generate invoice from actual hours |
| Read | `GET /v3/company/{id}/invoice/{id}` | Retrieve invoice |
| Update | `POST /v3/company/{id}/invoice` | Modify invoice |
| Send | `POST /v3/company/{id}/invoice/{id}/send` | Email invoice to client |
| Query | `GET /v3/company/{id}/query?query=SELECT * FROM Invoice` | List/filter invoices |
| PDF | `GET /v3/company/{id}/invoice/{id}/pdf` | Download invoice PDF |

**GeoTime → QBO Invoice Flow:**
1. Pay period closes or job milestone reached
2. Admin reviews actual hours worked per job in GeoTime
3. Admin clicks "Generate Invoice" → system calculates: `actual_hours × rate = invoice_total`
4. Invoice pushed to QBO with line items, terms, and client info
5. Admin can review/edit in QBO before sending to client
6. Payment status syncs back to GeoTime (Unpaid → Partial → Paid)
7. Webhook listener catches QBO invoice events for real-time status updates

**Invoice Fields Mapped:**
- `CustomerRef` → Job client
- `Line[]` → Actual hours per service type, with description including date range and team
- `DueDate` → Based on payment terms (Net 15, Net 30, etc.)
- `BillEmail` → Client email
- `SalesTermRef` → Payment terms from QBO
- `CustomField[]` → GeoTime job ID, pay period reference

**Estimate → Invoice Conversion:**
- One-click conversion of accepted QBO estimate to invoice
- GeoTime compares estimated vs. actual hours, flags variance > 10%
- Admin can adjust before pushing final invoice

#### 5.8.5 Additional QBO Sync Points
- **Customers** — Bidirectional sync between GeoTime clients and QBO customers
- **Employees** — Sync employee records for payroll reference
- **Service Items** — Map GeoTime job types to QBO service/item catalog
- **Payments** — Read payment receipts from QBO to mark invoices paid in GeoTime
- **Reports** — Pull Profit & Loss, Balance Sheet data for GeoTime financial dashboard

#### 5.8.6 QBO API Rate Limits & Pricing
- Core API calls (create/update): free and unlimited
- CorePlus API calls (read/query): metered per Intuit App Partner Program tier
- Builder tier (free): 500,000 monthly CorePlus credits
- Rate limit: 500 requests per minute per QBO company
- Batch API available for bulk operations (up to 30 operations per batch request)
- Webhooks for real-time event notifications (invoice created, payment received, etc.)

---

## 6. Database Schema (Core Tables)

### 6.1 Entity Relationship Summary

```
companies ──┬── teams ──┬── employees
             │           └── team_assignments (history)
             │
             ├── jobs ──┬── geofences
             │          └── job_team_assignments
             │
             ├── time_entries
             ├── breaks
             ├── transfer_records
             │
             ├── qbo_estimates
             ├── qbo_invoices
             └── qbo_bank_feed_transactions
```

### 6.2 Key Tables

**companies**
| Column | Type | Notes |
|--------|------|-------|
| id | UUID | PK |
| name | VARCHAR(255) | |
| timezone | VARCHAR(50) | Default timezone for the company |
| workweek_start_day | ENUM(0-6) | 0=Sunday, 1=Monday, etc. |
| overtime_rule | JSONB | `{ "weekly_threshold": 40, "daily_threshold": null, "multiplier": 1.5 }` |
| rounding_rule | ENUM | `EXACT`, `NEAREST_5`, `NEAREST_6`, `NEAREST_15` |
| qbo_realm_id | VARCHAR(50) | QuickBooks company ID |
| qbo_refresh_token | TEXT | Encrypted |
| clock_verification_mode | ENUM | `AUTO_ONLY`, `AUTO_PHOTO`. Default: `AUTO_ONLY` |

**teams**
| Column | Type | Notes |
|--------|------|-------|
| id | UUID | PK |
| company_id | UUID | FK → companies |
| name | VARCHAR(100) | |
| description | TEXT | |
| color_tag | VARCHAR(7) | Hex color for UI |
| lead_employee_id | UUID | FK → employees |
| status | ENUM | `ACTIVE`, `ARCHIVED` |
| created_at | TIMESTAMPTZ | |

**employees**
| Column | Type | Notes |
|--------|------|-------|
| id | UUID | PK |
| company_id | UUID | FK → companies |
| current_team_id | UUID | FK → teams (denormalized for fast lookup) |
| first_name | VARCHAR(100) | |
| last_name | VARCHAR(100) | |
| email | VARCHAR(255) | Unique |
| phone | VARCHAR(20) | |
| role | ENUM | `EMPLOYEE`, `TEAM_LEAD`, `MANAGER`, `ADMIN`, `SUPER_ADMIN` |
| hourly_rate | DECIMAL(10,2) | |
| ssn_encrypted | TEXT | AES-256 encrypted |
| date_of_birth | DATE | |
| address | JSONB | `{ "street", "city", "state", "zip" }` |
| hire_date | DATE | |
| device_id | VARCHAR(255) | Bound device for anti-fraud |
| status | ENUM | `ACTIVE`, `INACTIVE`, `TERMINATED` |
| qbo_employee_id | VARCHAR(50) | Linked QBO record |

**jobs**
| Column | Type | Notes |
|--------|------|-------|
| id | UUID | PK |
| company_id | UUID | FK → companies |
| name | VARCHAR(255) | |
| client_name | VARCHAR(255) | |
| qbo_customer_id | VARCHAR(50) | Linked QBO customer |
| address | TEXT | |
| status | ENUM | `ACTIVE`, `COMPLETED`, `ON_HOLD` |
| budget_hours | DECIMAL(10,2) | |
| hourly_rate | DECIMAL(10,2) | Override rate for this job |
| start_date | DATE | |
| end_date | DATE | |

**geofences**
| Column | Type | Notes |
|--------|------|-------|
| id | UUID | PK |
| job_id | UUID | FK → jobs |
| name | VARCHAR(100) | e.g., "Main Entrance", "Parking Lot" |
| latitude | DECIMAL(10,7) | Center point |
| longitude | DECIMAL(10,7) | Center point |
| radius_meters | INTEGER | 50–500 |
| is_active | BOOLEAN | |

**time_entries**
| Column | Type | Notes |
|--------|------|-------|
| id | UUID | PK |
| employee_id | UUID | FK → employees |
| job_id | UUID | FK → jobs |
| team_id | UUID | FK → teams (team at time of entry) |
| clock_in | TIMESTAMPTZ | |
| clock_out | TIMESTAMPTZ | Nullable (still clocked in) |
| clock_in_lat | DECIMAL(10,7) | GPS at clock-in |
| clock_in_lng | DECIMAL(10,7) | |
| clock_out_lat | DECIMAL(10,7) | |
| clock_out_lng | DECIMAL(10,7) | |
| clock_method | ENUM | `GEOFENCE`, `MANUAL`, `KIOSK`, `ADMIN_OVERRIDE` |
| total_hours | DECIMAL(5,2) | Calculated field |
| overtime_hours | DECIMAL(5,2) | Calculated at payroll |
| status | ENUM | `ACTIVE`, `SUBMITTED`, `APPROVED`, `REJECTED`, `PAYROLL_PROCESSED` |
| sync_status | ENUM | `PENDING`, `SYNCED`, `CONFLICT` |
| device_id | VARCHAR(255) | Device used for this entry |
| verification_status | ENUM | `VERIFIED`, `UNVERIFIED`, `NOT_REQUIRED`. Default: `NOT_REQUIRED` |
| selfie_url | TEXT | Anti-fraud photo path |
| notes | TEXT | |

**breaks**
| Column | Type | Notes |
|--------|------|-------|
| id | UUID | PK |
| time_entry_id | UUID | FK → time_entries |
| type | ENUM | `PAID_REST`, `UNPAID_MEAL` |
| start_time | TIMESTAMPTZ | |
| end_time | TIMESTAMPTZ | |
| duration_minutes | INTEGER | Calculated |
| was_interrupted | BOOLEAN | |

**transfer_records**
| Column | Type | Notes |
|--------|------|-------|
| id | UUID | PK |
| employee_id | UUID | FK → employees |
| from_team_id | UUID | FK → teams |
| to_team_id | UUID | FK → teams |
| reason_category | ENUM | `OPERATIONAL`, `PERFORMANCE`, `EMPLOYEE_REQUEST`, `ADMINISTRATIVE` |
| reason_code | ENUM | See Section 5.2.3 |
| notes | TEXT | Required if reason_code = `OTHER` |
| transfer_type | ENUM | `PERMANENT`, `TEMPORARY` |
| effective_date | DATE | |
| expected_return_date | DATE | Nullable |
| initiated_by | UUID | FK → employees |
| approved_by | UUID | FK → employees (nullable if auto-approved) |
| status | ENUM | `PENDING`, `APPROVED`, `REJECTED`, `COMPLETED`, `REVERTED` |
| created_at | TIMESTAMPTZ | Immutable |

**audit_log**
| Column | Type | Notes |
|--------|------|-------|
| id | UUID | PK |
| entity_type | VARCHAR(50) | e.g., `time_entry`, `employee`, `transfer` |
| entity_id | UUID | |
| action | ENUM | `CREATE`, `UPDATE`, `DELETE`, `APPROVE`, `REJECT` |
| changed_by | UUID | FK → employees |
| old_value | JSONB | |
| new_value | JSONB | |
| ip_address | INET | |
| created_at | TIMESTAMPTZ | |

**qbo_sync_log**
| Column | Type | Notes |
|--------|------|-------|
| id | UUID | PK |
| entity_type | ENUM | `ESTIMATE`, `INVOICE`, `CUSTOMER`, `PAYMENT`, `BANK_FEED` |
| geotime_entity_id | UUID | |
| qbo_entity_id | VARCHAR(50) | |
| direction | ENUM | `PUSH`, `PULL` |
| status | ENUM | `SUCCESS`, `FAILED`, `PENDING` |
| error_message | TEXT | |
| payload | JSONB | Request/response data for debugging |
| created_at | TIMESTAMPTZ | |

---

## 7. Security & Privacy

### 7.1 Data Protection
- All data encrypted at rest (AES-256) and in transit (TLS 1.3)
- SSN and sensitive PII stored with application-level encryption (separate key management)
- Row-level security in Supabase: employees only see their own data; managers see team data
- API authentication via JWT with short-lived tokens (15 min access, 7-day refresh)

### 7.2 Location Privacy
- GPS coordinates stored only at clock events (not continuous tracking)
- Employees can view their own location history
- Location data auto-purged after retention period (configurable, default 2 years)
- Privacy policy clearly discloses: what's collected, how it's used, who can see it
- Comply with state-specific employee monitoring laws (California, Illinois, Connecticut, New York)

### 7.3 Access Control Matrix

| Action | Employee | Team Lead | Manager | Admin | Super Admin |
|--------|----------|-----------|---------|-------|-------------|
| View own time | ✅ | ✅ | ✅ | ✅ | ✅ |
| View team time | ❌ | ✅ | ✅ | ✅ | ✅ |
| View all time | ❌ | ❌ | ✅ | ✅ | ✅ |
| Approve timesheets | ❌ | ✅ (own team) | ✅ | ✅ | ✅ |
| Edit time entries | Own only | Own team | All | All | All |
| Manage teams | ❌ | ❌ | ✅ | ✅ | ✅ |
| Transfer employees | ❌ | Request only | ✅ | ✅ | ✅ |
| Manage jobs | ❌ | ❌ | ✅ | ✅ | ✅ |
| Configure geofences | ❌ | ❌ | ❌ | ✅ | ✅ |
| QBO integration | ❌ | ❌ | ❌ | ✅ | ✅ |
| Create invoices/estimates | ❌ | ❌ | ❌ | ✅ | ✅ |
| Manage billing | ❌ | ❌ | ❌ | ❌ | ✅ |
| View SSN | ❌ | ❌ | ❌ | ❌ | ✅ |

---

## 8. Non-Functional Requirements

| Requirement | Target | Notes |
|-------------|--------|-------|
| Uptime | 99.9% | Supabase SLA |
| API Response Time | < 200ms (p95) | For dashboard and mobile sync |
| Offline Duration | Unlimited | App must function indefinitely without internet |
| Sync Latency | < 30 seconds | After internet reconnects |
| Geofence Trigger Latency | < 5 seconds | OS-dependent |
| GPS Accuracy | ≤ 50m | Standard GPS (not A-GPS) |
| Concurrent Users | 10,000+ | Per company |
| Data Retention | 2–7 years configurable | FLSA minimum: 2 years for time, 3 years for payroll |
| Mobile Battery Impact | < 5% daily | Transistor library optimized for this |
| Supported Platforms | iOS 15+, Android 10+ | |

---

## 9. Release Phases

### Phase 1 — MVP (Weeks 1–10)
- Geofence engine with auto clock-in/out
- Offline-first local storage + sync
- Employee mobile app (clock, breaks, view hours)
- Basic team structure (create, assign members)
- Admin web dashboard (employee management, timesheet approval, basic reports)
- FLSA-compliant record keeping

### Phase 2 — Team & Job Management (Weeks 11–16)
- Full team management with transfer workflow and categorized reasons
- Job/job site management with multi-geofence support
- Job costing reports
- Scheduling (assign shifts, compare scheduled vs. actual)
- PTO management
- Push notifications

### Phase 3 — QuickBooks Integration (Weeks 17–22)
- QBO OAuth 2.0 connection
- Customer sync (bidirectional)
- Estimate generation from jobs
- Invoice generation from actual hours
- Estimate → Invoice conversion
- Payment status sync via webhooks

### Phase 4 — Bank Feeds & Advanced (Weeks 23–28)
- Bank Feeds integration via Rutter/Codat middleware
- Push labor cost transactions to QBO bank feeds
- Reconciliation dashboard
- Advanced reporting (custom report builder)
- Anti-fraud features (selfie capture, facial recognition)
- Kiosk mode for shared devices

### Phase 5 — Scale & Polish (Weeks 29+)
- AI anomaly detection (unusual clock patterns)
- Predictive scheduling (based on historical job data)
- Multi-company support
- White-label option
- API for third-party integrations
- App Store / Play Store submission and approval

---

## 10. Success Metrics

| Metric | Target | Measurement |
|--------|--------|-------------|
| Auto clock-in accuracy | > 95% of entries via geofence | Ratio of geofence vs. manual clock events |
| Time to first clock-in | < 5 minutes from app install | Onboarding funnel tracking |
| Payroll processing time reduction | 50% reduction | Pre/post comparison with customer baseline |
| Missed punch rate | < 2% of total entries | Entries requiring manual correction |
| Timesheet approval cycle | < 24 hours | Time from submission to approval |
| QBO sync success rate | > 99% | Successful API calls / total attempts |
| Employee app adoption | > 90% of workforce within 2 weeks | Active users / total employees |
| Battery impact complaints | < 5% of users | Support ticket categorization |

---

## 11. Open Questions & Risks

| Item | Type | Status |
|------|------|--------|
| iOS App Store rejection for "Always Allow" location | Risk | Mitigation: detailed privacy justification, usage description |
| QBO Bank Feeds API partnership requirement | Blocker | Decision: use Codat or Rutter as middleware (cost ~$500–2000/mo) |
| State-specific overtime rules (CA daily OT, etc.) | Scope | Need: compile state rule matrix for all 50 states |
| GDPR applicability for international expansion | Future | Deferred to Phase 5+ |
| Transistor Software license cost ($300/app) | Budget | One-time cost, justified by months of saved development |
| QBO App Partner Program pricing (CorePlus API calls) | Budget | Free tier (500K calls/mo) sufficient for MVP; monitor usage |
| Geofence accuracy in dense urban environments | Risk | Mitigation: configurable radius, manual fallback, Wi-Fi assist |
| Employee resistance to location tracking | Risk | Mitigation: transparent policy, self-service data access, GPS only at clock events |

---

## 12. Appendix

### A. Glossary

| Term | Definition |
|------|-----------|
| **Geofence** | A virtual boundary defined by GPS coordinates and a radius around a physical location |
| **Clock Event** | A timestamped record of an employee starting or stopping work |
| **FLSA** | Fair Labor Standards Act — federal law governing minimum wage, overtime, and recordkeeping |
| **QBO** | QuickBooks Online — Intuit's cloud accounting software |
| **Bank Feed** | Automatic import of bank/credit card transactions into accounting software |
| **Non-Exempt** | Employees entitled to overtime pay under FLSA (typically hourly workers) |
| **DXA** | Device-independent units used in document formatting (1440 DXA = 1 inch) |
| **PTO** | Paid Time Off |
| **Buddy Punching** | Fraudulent practice where one employee clocks in on behalf of another |

### B. Competitive Landscape

| Competitor | Geofence Auto-Clock | Offline Support | QBO Integration | Bank Feeds | Team Transfers |
|-----------|---------------------|-----------------|-----------------|------------|----------------|
| **Clockify** | ❌ | Partial | ✅ | ❌ | ❌ |
| **Hubstaff** | ✅ (basic) | ❌ | ✅ | ❌ | ❌ |
| **TSheets (QBO Workforce)** | ✅ | Partial | ✅ (native) | ❌ | ❌ |
| **Parim** | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Jibble** | ✅ (basic) | Partial | ✅ | ❌ | ❌ |
| **GeoTime (Ours)** | ✅ (primary) | ✅ (full) | ✅ | ✅ | ✅ |

### C. Referenced Standards & Regulations
- FLSA Recordkeeping Requirements (29 CFR Part 516)
- FLSA Overtime Provisions (29 CFR Part 778)
- Intuit QuickBooks Online API Documentation (developer.intuit.com)
- Intuit App Partner Program Pricing (2025)
- Rutter Bank Feeds API Documentation
- Codat Bank Feeds Integration Guide
- State-specific labor laws (to be compiled per Phase 2)
