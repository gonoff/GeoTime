# GeoTime SaaS Architecture Design

**Date:** 2026-03-28
**Status:** Approved
**Context:** Redesign of GeoTime from a single-tenant app to a multi-tenant SaaS platform

---

## 1. Overview

GeoTime is a geofence-based employee time tracking and workforce management SaaS. This spec defines the architecture changes needed to convert the original PRD (which assumed Supabase + Next.js) into a multi-tenant SaaS using Laravel + PostgreSQL + Inertia/Vue, with a React Native mobile app.

The core product features (geofencing, time tracking, teams, transfers, jobs, FLSA compliance, QBO integration) remain unchanged. This spec covers infrastructure, multi-tenancy, billing, and the technology stack.

---

## 2. Technology Stack

| Layer | Technology | Rationale |
|---|---|---|
| Backend | Laravel (PHP 8.3) | Team expertise, mature SaaS ecosystem, built-in queues/scheduling/auth |
| Database | PostgreSQL 16 + PostGIS | Geospatial queries for geofences, JSONB for flexible config, TIMESTAMPTZ for timezone-safe time tracking |
| Admin Dashboard | Laravel + Inertia.js + Vue 3 | Team expertise with Inertia/Vue, single deployment unit with backend |
| Mobile App | React Native + Expo | Single codebase for iOS/Android, access to Transistor Software geofencing library |
| Geofencing | `react-native-background-geolocation` (Transistor Software) | OS-level geofencing, offline support, battery-optimized. Industry gold standard. |
| Offline DB | WatermelonDB | High-performance offline-first reactive database for React Native |
| API Auth | Laravel Sanctum | Session auth for dashboard, API tokens for mobile app |
| WebSockets | Laravel Reverb | First-party Laravel WebSocket server, no external dependencies |
| Cache/Queue | Redis 7 | Queue backend, cache, broadcast driver for Reverb |
| File Storage | DigitalOcean Spaces (S3-compatible) | Selfie photos, exports, attachments |
| Billing | Laravel Cashier + Stripe | Subscription management, per-employee pricing |
| Push Notifications | Firebase Cloud Messaging (FCM) | Cross-platform, free tier, React Native standard |
| CI/CD | GitHub Actions | Automated build and deploy via Docker Compose |

---

## 3. Multi-Tenant Architecture

### 3.1 Tenancy Model

Shared database with tenant isolation via `tenant_id` column on every tenant-scoped table.

- One PostgreSQL database serves all tenants
- Every query is automatically scoped via a Laravel global scope that applies `WHERE tenant_id = ?`
- Middleware resolves the tenant from the authenticated user's `tenant_id`
- No cross-tenant data access is possible through the application layer

### 3.2 Tenant Lifecycle

1. Business owner signs up → `tenants` row created → Stripe customer created → admin user created
2. 14-day free trial begins (Business tier features)
3. Trial expires → choose plan → Stripe Checkout → subscription active
4. Ongoing: admin invites employees → employees download mobile app → enter company code or invite link → join tenant

### 3.3 Tenant Table

The existing `companies` table from the PRD becomes the `tenants` table. It retains all company-level configuration (timezone, overtime rules, rounding rules, workweek start day, QBO credentials) and adds:

- `stripe_id` — Stripe customer ID
- `trial_ends_at` — trial expiration timestamp (subscription details live in Cashier's `subscriptions` table)
- `plan` — `starter` or `business`
- `status` — `active`, `trial`, `past_due`, `cancelled`, `suspended`

### 3.4 Platform Super Admin

A separate guard/role for platform-level administration (not accessible to tenant users):

- View/manage all tenants
- Billing overview and intervention
- Support access (impersonate tenant admin)
- System health and metrics

---

## 4. Authentication & API

### 4.1 Admin Dashboard (Inertia + Vue)

- Laravel session-based auth (standard web guard)
- Login via email/password + optional 2FA (TOTP)
- Tenant resolved from authenticated user's `tenant_id`

### 4.2 Mobile App (React Native)

- Laravel Sanctum API tokens
- Login via email/password → returns bearer token stored on device
- Token scoped to tenant automatically
- Device binding: token tied to `device_id` for anti-fraud

### 4.3 API Structure

```
/api/v1/auth/login
/api/v1/auth/register
/api/v1/time-entries
/api/v1/geofences
/api/v1/teams
/api/v1/jobs
/api/v1/sync              ← mobile offline sync endpoint
/api/v1/employees
/api/v1/transfers
/api/v1/reports/*
/api/v1/qbo/*             ← QuickBooks integration
```

All `/api/v1/*` routes go through tenant-scoping middleware. The mobile app and admin dashboard share the same API where applicable.

---

## 5. Mobile App ↔ Server Sync

### 5.1 On-Device Storage

WatermelonDB stores time entries, breaks, and geofence definitions locally. Geofence events are recorded with timestamp, GPS coordinates, and `sync_status: pending`.

### 5.2 Sync Flow

```
Device comes online
  → Pull: GET /api/v1/sync?last_synced_at=<timestamp>
    ← Server sends updated geofences, team assignments, job changes
  → Push: POST /api/v1/sync
    → Device sends all pending time entries, breaks
    ← Server validates, stores, returns sync confirmations
  → Device marks entries as synced
```

Single bulk endpoint for all entity types to minimize round trips on spotty connections.

### 5.3 Conflict Resolution

A conflict occurs when the server already has a time entry for the same employee, job, and overlapping time window as a device-submitted entry (e.g., an admin manually created an entry while the employee was offline). If no server-side record exists for the same event, the device entry is simply inserted as normal.

- When a conflict is detected, server timestamp wins by default
- Conflicts flagged in an `entry_conflicts` table for admin review
- Admin can accept device version or server version

### 5.4 Real-Time Geofence Updates

When admin changes a geofence on the dashboard, the change is pushed to devices on next sync. For online devices, Laravel broadcasts via Reverb → mobile app listens on a WebSocket channel per tenant.

---

## 6. Real-Time Features (Dashboard)

### 6.1 Technology

Laravel Reverb (first-party WebSocket server) with Laravel Echo on the Vue frontend.

### 6.2 Real-Time Events

- Employee clock in/out → map pins update, team status cards refresh
- Compliance alerts (missed punch, overtime threshold) → alerts panel
- Sync status per employee → "last seen" indicators

### 6.3 Channel Structure

Events broadcast on private tenant channels: `private-tenant.{tenant_id}.events`

### 6.4 Request-Based (Not Real-Time)

- Reports (generated on demand or queued)
- QBO sync operations
- Transfer approvals (notification-driven)

---

## 7. Infrastructure & Hosting

### 7.1 Fully Dockerized Stack

Replicates the proven cleaningsaas infrastructure pattern:

| Container | Role |
|---|---|
| **app** | PHP 8.3-FPM + Supervisor (runs PHP-FPM, queue worker, scheduler, Reverb). Single container is a deliberate choice for early-stage simplicity; Reverb can be split to its own container later if WebSocket load demands it. |
| **caddy** | Reverse proxy, auto-SSL via Let's Encrypt, static asset serving |
| **postgres** | PostgreSQL 16 + PostGIS, data persisted to `postgres_data` volume |
| **redis** | Redis 7, cache + queue + broadcast driver |

### 7.2 Hosting

- DigitalOcean Droplet (4GB RAM starter, scale as needed)
- DigitalOcean Spaces for S3-compatible file storage
- No Forge — Caddy + Supervisor + Docker Compose handles everything

### 7.3 Deployments

GitHub Actions → `docker compose build app && docker compose up -d`

### 7.4 SSL/TLS

Caddy handles cert issuance, renewal, and termination automatically.

---

## 8. SaaS Billing

### 8.1 Technology

Laravel Cashier (Stripe) for subscription management.

### 8.2 Pricing Model

Per-employee monthly pricing:

| Plan | Price | Includes |
|---|---|---|
| **Starter** | $8/user/mo | Geofencing, time tracking, team management, mobile app, basic reports |
| **Business** | $12/user/mo | Everything in Starter + QBO integration, advanced reports, job costing, bank feeds |

- 14-day free trial on Business tier
- Stripe subscription with `quantity` = number of active employees
- Adding/removing employees automatically updates subscription quantity (prorated)

### 8.3 Lapsed Subscription Behavior

- **Read-only mode** — tenant can log in, view data, export reports, but cannot create time entries or add employees
- Mobile app continues recording locally (offline-first) but won't sync until subscription is active
- When subscription resumes, all locally stored entries sync normally — no cap on historical entry age. The data was earned work time; rejecting it would create liability.
- Grace period of 7 days after failed payment before restricting access

### 8.4 Estimated Break-Even

Monthly fixed costs: ~$79/mo (droplet, DB, storage, licenses)

| Users | Net Revenue | Break-Even? |
|---|---|---|
| 7 (Business tier) | ~$79 | Yes |
| 11 (Starter tier) | ~$82 | Yes |

One small business with 10-15 employees covers infrastructure costs.

---

## 9. Database Schema Changes

The core schema from the PRD remains structurally the same. Key changes:

1. `companies` table renamed to `tenants` with added billing fields (`stripe_id`, `trial_ends_at`, `plan`, `status`)
2. All tenant-scoped tables use `tenant_id` FK instead of `company_id`
3. PostgreSQL-specific: `PostGIS` geometry columns available for geofence calculations on the server side
4. New `entry_conflicts` table for sync conflict tracking (employee_id, device_entry, server_entry, resolution status)
5. New `subscriptions` table (managed by Laravel Cashier)
6. New `subscription_items` table (managed by Laravel Cashier)

---

## 10. What Stays Unchanged from the PRD

All business logic and product features remain as specified in the PRD:

- Geofence engine (auto clock-in/out, offline-first, anti-fraud)
- Team management with transfer workflow and categorized reasons
- Job/job site management with multi-geofence support
- Time tracking (breaks, overtime, rounding, timesheets, PTO)
- FLSA compliance module
- Employee self-service (mobile dashboard, notifications, missed punch resolution)
- Admin dashboard features (real-time overview, reports, user management)
- QuickBooks Online integration (auth, bank feeds, estimates, invoices)
- Access control matrix (Employee → Team Lead → Manager → Admin → Super Admin)
- Non-functional requirements (uptime, latency, offline duration, battery impact)
- Release phases (adjusted for new stack but same feature sequencing)

---

## 11. Architecture Diagram

```
┌─────────────────────────────────────────────────────────────┐
│              MOBILE APP (React Native + Expo)                │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │ Geofence     │  │ WatermelonDB │  │ Employee UI  │      │
│  │ Engine       │  │ (Offline)    │  │ (Vue-like)   │      │
│  │ (Transistor) │  │              │  │              │      │
│  └──────┬───────┘  └──────┬───────┘  └──────────────┘      │
│         │                 │                                  │
│         └────────┬────────┘                                  │
│                  │ Sync when online                           │
└──────────────────┼───────────────────────────────────────────┘
                   │
                   ▼ REST API (Sanctum tokens)
┌──────────────────────────────────────────────────────────────┐
│                DOCKER HOST (DigitalOcean)                      │
│                                                                │
│  ┌────────────────────────────────────────────────────────┐   │
│  │              Caddy (Reverse Proxy + Auto-SSL)          │   │
│  └────────────────────────┬───────────────────────────────┘   │
│                           │                                    │
│  ┌────────────────────────▼───────────────────────────────┐   │
│  │              Laravel App Container                      │   │
│  │  ┌──────────┐  ┌──────────┐  ┌──────────┐             │   │
│  │  │ Sanctum  │  │ Inertia  │  │ Cashier  │             │   │
│  │  │ (Auth)   │  │ + Vue 3  │  │ (Stripe) │             │   │
│  │  └──────────┘  └──────────┘  └──────────┘             │   │
│  │  ┌──────────┐  ┌──────────┐  ┌──────────┐             │   │
│  │  │ Queues   │  │ Reverb   │  │ QBO      │             │   │
│  │  │ (Redis)  │  │ (WS)     │  │ Service  │             │   │
│  │  └──────────┘  └──────────┘  └──────────┘             │   │
│  │         Supervisor manages all processes                │   │
│  └────────────────────────────────────────────────────────┘   │
│                           │                                    │
│          ┌────────────────┼────────────────┐                  │
│          ▼                ▼                ▼                   │
│  ┌──────────────┐ ┌──────────────┐ ┌──────────────┐          │
│  │ PostgreSQL   │ │ Redis 7      │ │ DO Spaces    │          │
│  │ 16 + PostGIS │ │ Cache/Queue  │ │ (S3 Storage) │          │
│  └──────────────┘ └──────────────┘ └──────────────┘          │
└──────────────────────────────────────────────────────────────┘
```

---

## 12. Key Decisions Log

| Decision | Choice | Rationale |
|---|---|---|
| Multi-tenancy model | Shared DB with `tenant_id` | Simpler ops, proven pattern (cleaningsaas), sufficient isolation for target market |
| Backend framework | Laravel | Team expertise, mature ecosystem, built-in queues/auth/billing |
| Database | PostgreSQL + PostGIS | Geospatial queries, JSONB, TIMESTAMPTZ, better fit than MySQL for this domain |
| Admin dashboard | Inertia.js + Vue 3 | Team expertise, single deployment with backend |
| Mobile framework | React Native + Expo | Transistor Software geofencing library (gold standard), single codebase |
| WebSockets | Laravel Reverb | First-party, no external dependency, no message limits |
| Infrastructure | Dockerized on DigitalOcean | Replicates proven cleaningsaas stack, no Forge overhead |
| Billing | Stripe via Laravel Cashier, per-employee | Industry standard model, $8/user Starter, $12/user Business |
| Trial | 14-day free trial on Business tier | Shows full product value before conversion |
