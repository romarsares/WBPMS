---
inclusion: always
---

# Technology Stack

The source capstone documentation specified PHP for the server side. This
project deliberately deviates from that and uses **Node.js** instead —
everything else (MySQL, the 3-tier architecture, the data model) carries
over unchanged. Kiro should default to the choices below.

## Application layers

- **Server-side runtime/language:** Node.js (LTS) with **TypeScript** —
  handles payroll calculations, employee record management, and secure DB
  communication.
- **Web framework:** Express — routing, middleware (auth/RBAC guards),
  request validation.
- **ORM:** Sequelize (MySQL dialect) — maps directly onto the normalized
  schema in `design.md` (models = tables: `Employee`, `Attendance`,
  `Payroll`, `Salary`, etc.) and gives migrations for schema evolution.
- **Validation:** `zod` or `express-validator` for request payload
  validation at the controller boundary.
- **Auth:** `bcrypt` for password hashing, `jsonwebtoken` (or
  session + `express-session`) for authenticated sessions, role claims
  checked in middleware.
- **Client-side scripting:** JavaScript (or a lightweight frontend
  framework such as React, if the team wants componentized views) — same
  role as before: dynamic UI without full page reloads.
- **Markup/styling:** HTML + CSS.
- **Database:** MySQL (relational, client-server) — employee records,
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

- **Biometric device** — attendance data arrives as a `.dat` file exported
  from the device and manually uploaded by the HR Head (no live device
  connection/API integration). The Application Tier parses the file and
  generates a timesheet — see `attendance` module in `design.md`.
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

## Non-functional constraints to design against

- Data retrieval: within 1 second on a stable connection.
- Dashboard load: within 5 seconds.
- Payroll computation: within 5 seconds.
- Report generation: within 5 seconds.
- Only authenticated, role-authorized users may access protected actions.
- English language, `MM/DD/YY` dates, 24-hour time format.
