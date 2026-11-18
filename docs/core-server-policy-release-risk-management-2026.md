# Core-server 2026 statutory-policy release — risk management

**Release scope:** Correct the employer SSS contribution share to include the
Employees' Compensation Program (ECP) in the 2026 Philippine statutory policy.
The release contains migration `20261118000027_add_sss_ecp_to_2026_policy.php`
and the related 2026 seeder correction.  It does not authorize clearing
transactions, reseeding a live database, or replacing master data.

**System:** WBPMS on `core-server`  
**Owner:** Release manager / authorised payroll owner  
**Prepared:** 2026-11-18  
**Status:** Hold until the pre-release gates below are evidenced and approved.

## Decision and release boundary

Use a new, clean database only for verification and demonstrations.  The
production database is an accounting record and must be preserved.  The live
policy change is a versioned, forward-only correction: the migration creates
`PH-STATUTORY-2026` version 3, retires the previous approved version, and leaves
its historical bracket rows intact.  It must never be combined with
`clear_transactional_data.sql` or any broad delete operation.

The normal `deploy.sh` is **not sufficient for this change by itself**.  It
packages the current working tree (including uncommitted files) and does not
create a database backup.  More importantly, its container rebuild starts
`docker/entrypoint.sh`, which automatically runs **every pending** Phinx
migration.  Therefore an application restart is also a database-change step;
a release operator must confirm the exact pending set before deploying.

## Risk register

| ID | Risk and affected area | Likelihood | Impact | Required control and release evidence | Owner | Stop / rollback trigger |
|---|---|---:|---:|---|---|---|
| R1 | A production database is cleared, reseeded, or pointed at the clean database.  This would corrupt payroll, attendance, audit, and employee history. | Low | Critical | Separate database names and credentials; record `DB_NAME` without exposing secrets; prohibit cleanup and seed commands; confirm backup and row counts before release. | DBA / release manager | Any cleanup/seed command proposed for the live database: stop. Restore the verified backup if a write occurred. |
| R2 | The normal deploy archive carries unreviewed/uncommitted files, overwrites server-only configuration, or starts an automatic migration. | Medium | High | Commit only reviewed release files on `WBPMS-dev`; use a clean `git status`; preserve remote `.env`; compare release manifest and commit SHA; confirm the exact pending Phinx set before rebuilding containers. | Release manager | Dirty source tree, unexpected file in manifest, unexpected remote diff, or any unapproved pending migration: stop before upload. |
| R3 | Incorrect contribution brackets change employee deductions or employer liabilities. | Medium | Critical | Verify the exact four checkpoint brackets after migration: MSC 5,000 (250 / 510), 14,250 (725 / 1,460), 14,750 (750 / 1,530), and 34,750 (1,750 / 3,530), employee / employer. Have payroll owner sign off. | Payroll owner | Any checkpoint differs, policy dates are wrong, or more than one policy remains approved: stop and restore before payroll processing. |
| R4 | The migration changes the policy used by an in-progress or already calculated payroll run. | Medium | Critical | Schedule a maintenance window outside payroll calculation/approval; identify active runs and contribution records using the affected policy; do not proceed until payroll owner confirms the change window. | Payroll owner | Any payroll run is calculating, pending approval, or requires recalculation without written payroll decision. |
| R5 | Migration is applied against the wrong database or environment. | Medium | Critical | From the application container, record hostname, Compose project/service, `DB_NAME`, and `phinx status`; require the production target to be explicitly shown in the command. | DBA / release manager | Database identity does not match the approved production record. |
| R6 | Backup is unusable, incomplete, or stored only on the host that is being changed. | Medium | Critical | Create a timestamped, consistent database dump; calculate a checksum; copy it to approved off-host storage; perform a restore test to an isolated database before the change. | DBA | Backup or restore test fails: do not migrate. |
| R7 | Application/container rebuild causes an outage or version incompatibility. | Medium | High | Check container health and current image/commit; take a read-only endpoint smoke test before and after; keep the last known-good application artifact and documented compose rollback command. | Release manager | Containers unhealthy, error rate rises, or login/payroll screens fail: roll application back while preserving the database evidence. |
| R8 | Migration has unexpected data scope: it retires a policy and creates a replacement. | Low | High | Pre-query all `PH-STATUTORY-2026` policies, versions, statuses, dates, brackets, and referencing contribution records.  Confirm the migration is the only pending release migration. | DBA / payroll owner | Existing data does not match the migration's assumptions or there are extra pending migrations. |
| R9 | The automated test suite has unresolved failures and gives a false release signal. | High | Medium | Keep passing evidence separate from failures: 141 unit tests / 281 assertions passed; full suite has 21 pre-existing harness/fixture errors (connection authentication and `request_type` enum data).  Do not claim full-suite green.  Run targeted policy verification on the isolated clean database and core-server staging clone. | QA | Any policy-specific test or smoke check fails; investigate before release. |
| R10 | 2026 statutory policy is later superseded by an official circular. | Low | High | Retain the source evidence and effective-date decision with the release record; require payroll/legal owner to re-check SSS, PhilHealth, and Pag-IBIG notices before the effective payroll period. | Payroll compliance owner | Official update conflicts with the configured rates/effective date. Create a new version; do not edit approved history. |
| R11 | Credentials or customer data leak through terminal output, archives, logs, or the release report. | Medium | High | Never print `.env`, connection strings, backups, or row-level payroll data; restrict archive and backup permissions; publish only aggregate verification results. | Release manager / DBA | Secret or personal data appears in logs: stop sharing, rotate exposed secret, and follow incident procedure. |

## Mandatory gates

All gates must be checked by name/date in the release record.

1. **Source gate** — The reviewed change is committed on `WBPMS-dev`; `git status --short` is clean; the release commit SHA is recorded.  No direct commit to `main`, production, staging, or test branches.
2. **Environment gate** — Read-only core-server preflight identifies the application directory, running Compose services, database name, current application SHA, and Phinx status.  No password or secret is copied into the release record.
3. **Database safety gate** — A restorable, off-host backup exists and its checksum and restore-test result are recorded.  The production database is not reset, seeded, cleaned, or otherwise bulk-deleted.
4. **Migration-scope gate** — The policy data pre-query is reviewed by payroll.  Migration `20261118000027` is the only pending schema/data change approved for this window, because the container entrypoint will run all pending migrations at startup.
5. **Business-window gate** — No payroll run is calculating or awaiting a decision affected by the policy.  Payroll owner approves the maintenance window and communication.
6. **Verification gate** — The four ECP checkpoints and one-approved-version invariant pass on a clean test database and an isolated core-server staging clone.
7. **Go/no-go gate** — Release manager, DBA, payroll owner, and QA explicitly approve proceeding.  Absence of any approver is a no-go.

## Controlled release runbook

### 1. Read-only preflight

- Connect to `core-server` using the approved account and confirm the expected
  `/srv/app/wbpms` deployment directory.
- Record only non-sensitive facts: host name, application commit SHA, clean/dirty
  worktree state, Compose service status, application health endpoint result,
  database name, and Phinx migration status.
- Confirm that `20261118000027_add_sss_ecp_to_2026_policy.php` is present and
  is the only approved pending migration.
- Query the current `PH-STATUTORY-2026` policy versions, their status/effective
  dates, bracket count, and the number of contribution records referencing each
  version.  Share aggregate counts only.

### 2. Backup and recovery rehearsal

- Place the application in the agreed maintenance state.
- Create a consistent MySQL dump of the exact production schema, with routines
  and triggers where applicable, using an account authorised by the DBA.
- Store a checksum and copy the encrypted dump to approved off-host storage.
- Restore that dump into an isolated database and verify table counts plus a
  simple WBPMS login/read-only smoke check.  Record the restore duration.
- Preserve the current application artifact/image and its commit SHA.

### 3. Deployment and migration

- Build and deploy the reviewed, committed source only.  Do not run the current
  `deploy.sh` from a dirty tree.  Immediately before the rebuild, record Phinx
  status and confirm the approved migration is the only pending item.
- Treat the application restart as the migration execution step: its entrypoint
  runs Phinx automatically.  Do **not** use `seed:run`, `migrate:fresh`,
  `clear_transactional_data.sql`, or deploy while any unreviewed migration is
  pending.
- Recheck container health and Phinx history after the application release.
- Capture the migration output in the restricted release record.

### 4. Post-migration validation

- Confirm `PH-STATUTORY-2026` version 3 is Approved, the prior approved version
  is Retired, the effective date is `2026-01-01`, and exactly 61 brackets exist.
- Verify the following selected brackets (employee / employer contribution):

| Salary-bracket starting amount | Expected employee | Expected employer |
|---:|---:|---:|
| 5,000 | 250 | 510 |
| 14,250 | 725 | 1,460 |
| 14,750 | 750 | 1,530 |
| 34,750 | 1,750 | 3,530 |

- Smoke-test authenticated read access, policy listing, and a non-persistent
  payroll preview where the payroll owner approves it.  Verify no historical
  payroll, attendance, employee, master, or audit records changed unexpectedly.
- End maintenance only after payroll owner accepts the results.

## Rollback and incident response

Do not use Phinx `rollback` blindly: the migration's down path refuses a
rollback once contribution records reference version 3, which protects payroll
history.  Use this decision order:

1. **Application-only failure:** deploy the preserved last-known-good artifact;
   leave the database unchanged and investigate.
2. **Migration fails before commit:** verify migration transaction/status, do
   not retry until the cause is understood; retain logs and backup.
3. **Migration commits but validation fails and no version-3 contribution is
   used:** have the DBA restore the tested backup or execute a reviewed,
   audited corrective migration after payroll approval.
4. **Any version-3 contribution/payslip is already created:** do not roll back
   or edit history.  Freeze affected processing, preserve evidence, and issue a
   new corrective policy version after payroll/compliance approval.
5. **Data loss, secret exposure, or uncertain database identity:** stop the
   release, isolate access as appropriate, notify the system owner, and follow
   the organisation's incident process.

## Evidence to retain

- Approval record and maintenance window.
- Release commit SHA, source manifest, remote preflight output with secrets
  redacted, and container health checks.
- Backup checksum, off-host storage confirmation, and isolated restore result.
- Migration status before/after and aggregate policy/bracket validation results.
- QA test result, known-test-failure record, payroll owner acceptance, and
  rollback/incident notes if used.
