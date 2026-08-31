---
inclusion: always
---

# Technology Stack

The source capstone documentation specified PHP for the server side. This
project deliberately deviates from that and uses **Node.js** instead —
everything else (MySQL, the 3-tier architecture, the data model) carries
over unchanged. Kiro should default to the choices below.

## Application layers

- **Server-side runtime/language:** Node.js 24.x LTS with **TypeScript** —
  handles payroll calculations, employee record management, and secure DB
  communication.
- **Web framework:** Express — routing, middleware (auth/RBAC guards),
  request validation.
- **ORM:** Sequelize (MySQL dialect) — maps directly onto the normalized
  schema in `design.md` (models = tables: `Employee`, `Attendance`,
  `Payroll`, `Salary`, etc.) and gives migrations for schema evolution.
- **Validation:** Zod for environment, request payload, and service-boundary
  validation.
- **Auth:** `bcrypt` for password hashing plus server-managed
  `express-session` sessions persisted in MySQL; role checks remain in
  middleware.
- **Client-side scripting:** small progressive JavaScript enhancements; no
  separate SPA in the MVP.
- **Markup/styling:** server-rendered EJS, HTML, and CSS.
- **Database:** MySQL 8.4 LTS (InnoDB, `utf8mb4`, strict SQL mode) — employee records,
  salary info, payroll history, user accounts. Schema unchanged from
  `design.md`.
- **Hosting/deployment:** any Node-compatible host (Render, Railway,
  a VPS with PM2 + Nginx reverse proxy, or similar) with a managed or
  self-hosted MySQL instance. (Replaces SmartASP.NET, which is
  PHP/ASP.NET-oriented.)
- **Report/PDF generation:** `pdfkit` or `puppeteer` (HTML→PDF) for
  payslips, payroll summaries, and exportable reports.
- **IDE:** Visual Studio Code (unchanged).

## Architecture style

Three-tier architecture, unchanged in shape:

1. **Presentation Tier** — browser UI (HTML/CSS/JS), role-specific views.
2. **Application Tier** — Node.js/Express: routers → controllers →
   services, holding authentication, payroll computation, request
   workflows, and report generation logic.
3. **Database Tier** — MySQL via Sequelize models, stores and serves
   persistent data.

Requests flow: Presentation → Application → Database, and results (computed
salaries, reports, payslips) flow back the same path in reverse.

## External/peripheral integration

- **Biometric device** — attendance data arrives as the supplied legacy
  monthly `.xls` daily-log workbook and is manually uploaded by the HR Head
  (no live device connection/API integration). A SheetJS-backed parser
  expands its employee/date matrix into immutable raw punches before
  generating a timesheet. See ADR-0001 and the attendance design.
- **Email/OTP** — password recovery flow, sent via a mail provider (e.g.,
  Nodemailer + SMTP, or a transactional email API).

## Environments and browsers

- OS target for the Node server: any (Linux recommended for hosting);
  Windows 10+ for developer/admin workstations.
- Supported browsers: Chrome, Firefox, Edge.
- Minimum client hardware: Intel i5+, 8GB RAM, SSD storage, 1080p monitor
  (per the source hardware requirements table) — relevant mainly for
  on-prem developer/admin workstations, not for typical employee access.

## Common commands

```bash
# Install dependencies
npm install

# Local dev server (with auto-reload)
npm run dev

# Run DB migrations (Sequelize CLI)
npx sequelize-cli db:migrate

# Seed reference data (roles, request types, contribution brackets)
npx sequelize-cli db:seed:all

# Run tests
npm test

# Build for production / start
npm run build && npm start
```

## Frozen MVP choices

- Package manager: npm.
- Tests: Vitest plus Supertest.
- Local database: MySQL 8.4 through Docker Compose, with separate development
  and test databases.
- Business timezone: `Asia/Manila`; store instants in UTC and business dates
  as MySQL `DATE`.
- API success envelope: `{ "data": ..., "meta": ... }`; error envelope:
  `{ "error": { "code": "...", "message": "...", "fields": {}, "requestId": "..." } }`.
- Required environment variables: `NODE_ENV`, `PORT`, `APP_BASE_URL`,
  `SESSION_SECRET`, `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and
  `DB_PASSWORD`.

See [`ADR-0001`](../../docs/adr/0001-development-baseline.md) for the complete decision
record and production caveats.

## Non-functional constraints to design against

- Data retrieval: within 1 second on a stable connection.
- Dashboard load: within 5 seconds.
- Payroll computation: within 5 seconds.
- Report generation: within 5 seconds.
- Only authenticated, role-authorized users may access protected actions.
- English language, `MM/DD/YY` dates, 24-hour time format.
