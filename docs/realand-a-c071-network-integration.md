# Realand A-C071 Network Integration Design

> **Status:** Future enhancement — not implemented in the current WBPMS
> prototype. The existing `.xls` attendance-import path remains the supported
> workflow until this integration is built, tested, and approved.

## 1. Purpose

This document defines the proposed integration of the Realand A-C071 biometric
time-attendance terminal with the Web-Based Payroll Management System (WBPMS).
The goal is to collect attendance punches through the organisation's local
network, validate and store them in MySQL, and make them available to the
attendance and payroll workflows.

This design intentionally does **not** connect the biometric device directly
to MySQL or expose it to the public internet.

## 2. Supported device capabilities

The A-C071 documentation describes TCP/IP, USB, and USB-memory-disk
communications. It also describes real-time/push attendance collection.
The device's communication settings include a device ID, communication key,
TCP/IP address, subnet mask, gateway, and port; the manual lists `5005` as the
default TCP/IP port.

Before implementation, confirm the exact terminal firmware, enabled features,
and SDK/protocol supplied with the physical device. Realand hardware variants
and firmware may differ.

- [A-C071 product/manual reference](https://kenanaonline.com/files/0071/71742/AC-071.pdf)
- [Realand SDK download page](https://www.realandtec.com/download/sdk-download_c0005)

## 3. Target topology

```text
Realand A-C071 terminal
        |
        | Ethernet, private LAN, TCP/IP
        v
Local Device Integration Gateway
(Realand SDK/RAMS-compatible service)
        |
        | authenticated HTTPS requests
        v
WBPMS PHP application
        |
        v
MySQL database
```

The integration gateway is a small service installed on a trusted computer or
server in the same LAN as the device. It either receives device-pushed logs or
polls the terminal using the vendor-supported SDK/protocol. It sends validated
attendance events to WBPMS over HTTPS.

The device must not be reachable from the public internet. MySQL must accept
connections only from the WBPMS application/database network, not from the
terminal.

## 4. Responsibilities

| Component | Responsibility |
|---|---|
| A-C071 terminal | Captures fingerprint/card/password verification and retains its local enrollment templates and event log. |
| Integration gateway | Connects to the device, converts vendor records into a neutral event format, retries safely, and authenticates to WBPMS. |
| WBPMS API | Validates source device and payload, maps enrollment IDs to employees, de-duplicates punches, records audit information, and triggers attendance processing. |
| MySQL | Stores device configuration, import batches, raw attendance punches, attendance records, payslip records, and permitted image/document data. |

## 5. Data and privacy rules

WBPMS must receive and retain only the minimum attendance data needed for
payroll:

- device ID;
- employee enrollment ID (the terminal's user/enroll ID);
- punch timestamp;
- event type, if supplied by the terminal;
- source/import metadata and validation result.

Fingerprint templates, fingerprint images, and biometric matching data must
remain on the A-C071 unless the organisation has separately approved their
collection, retention, encryption, access controls, and privacy/legal basis.
They are not required for WBPMS attendance and payroll computation.

If the project stores non-biometric images or documents in MySQL, it should
store their MIME type, size, checksum, ownership, and access restrictions with
the binary data. Access must be role- and owner-scoped.

## 6. Required configuration

For each terminal, HR or an authorised administrator must record:

| Field | Requirement |
|---|---|
| Device ID | Unique terminal identifier; do not reuse it across devices. |
| Device name | Human-readable label, for example `Main Office A-C071`. |
| Attendance site | The physical location where the terminal is installed. |
| Network address | Reserved/static private LAN IP address, subnet mask, and gateway. |
| TCP port | Confirm from the actual terminal configuration; default documented value is `5005`. |
| Communication key | Non-default secret configured on the device and gateway; never display it in UI or logs. |
| Branch coverage | One or more valid branches served by this device. A device is not assumed to belong to only one branch. |
| Enrollment mapping | Effective-dated mapping between terminal enrollment ID and WBPMS employee. |

## 7. Attendance event contract

The gateway should send a normalized event to an authenticated, internal WBPMS
endpoint. The final route and authentication mechanism must be implemented as
part of the integration, but a representative payload is:

```json
{
  "device_code": "AC071-MAIN-01",
  "device_event_id": "optional-vendor-log-id",
  "enrollment_code": "000123",
  "punched_at": "2026-08-31T08:03:14+08:00",
  "event_type": "check-in",
  "received_at": "2026-08-31T08:03:20+08:00"
}
```

`enrollment_code` must be treated as a string. Leading zeroes are significant.
The server must not trust an employee ID supplied by the gateway; it must
resolve the employee through the effective `employee_biometric_enrollment`
record for the terminal and punch time.

## 8. Processing flow

1. An employee verifies on the A-C071 terminal.
2. The terminal stores the attendance event and either pushes it to the gateway
   or makes it available to a gateway polling job.
3. The gateway validates the device response and sends a normalized event to
   WBPMS over HTTPS.
4. WBPMS authenticates the gateway, verifies the registered device and its
   allowed branch coverage, and records the raw punch in MySQL.
5. WBPMS rejects or flags unmatched enrollment IDs, duplicate events, invalid
   timestamps, and devices not authorised for the received branch/site.
6. Attendance processing groups valid punches by employee and work date, then
   generates or updates the employee's attendance record under the approved
   schedule rules.
7. HR reviews exceptions such as one punch, unmatched enrollments, duplicate
   punches, late arrivals, overtime, and branch-coverage conflicts.

## 9. Idempotency and audit requirements

Network retries must never produce duplicate attendance. The database design
should enforce a unique event identity using the vendor event ID when reliable,
or a deterministic fingerprint such as:

```text
device ID + enrollment code + punch timestamp + event type
```

Every sync attempt must record the device, gateway request ID, received time,
record totals, rejected/duplicate totals, and error reason. Raw punches must
remain traceable to their source device and import/sync batch.

## 10. Security requirements

- Put the terminal and gateway on a protected LAN or VLAN.
- Use a static/reserved private address for each terminal.
- Change the terminal's default communication key and restrict firewall rules
  to the gateway only.
- Use HTTPS between the gateway and WBPMS, with a per-gateway credential or
  signed request. Rotate the credential when compromised.
- Rate-limit the ingest endpoint and log rejected requests without logging
  secrets or biometric templates.
- Synchronize device and gateway time using the organisation-approved time
  source; record timestamps with the Asia/Manila timezone policy.
- Require HR-authorized, auditable changes for device, site, coverage, and
  employee-enrollment mappings.

## 11. Implementation phases

### Phase 1 — preparation

1. Confirm the exact A-C071 firmware and obtain its permitted SDK/protocol
   documentation from the vendor.
2. Reserve a LAN IP address, set the communication key, configure the device
   ID and port, and confirm the gateway can reach the terminal.
3. Add the necessary device/site/branch/enrollment master-data management UI
   and migrations where missing.

### Phase 2 — gateway and ingestion API

1. Build a small gateway using the vendor-supported SDK. A Windows/.NET
   gateway may be appropriate if that is the SDK's supported environment.
2. Create a WBPMS authenticated ingestion endpoint.
3. Persist sync batches and raw punches transactionally; implement idempotency
   and error reporting.

### Phase 3 — attendance workflow

1. Resolve enrollment codes against effective employee records.
2. Generate attendance entries and apply the existing work-schedule rules.
3. Provide HR review and reconciliation screens for exceptions.

### Phase 4 — verification and rollout

1. Test on a non-production terminal and test employees first.
2. Compare device logs with WBPMS records over several payroll periods.
3. Retain the `.xls` upload process as a fallback until the network-sync
   workflow is accepted.

## 12. Acceptance criteria

The integration is ready only when all of the following pass:

- Gateway connects to the configured A-C071 on the LAN without public internet
  exposure.
- A valid punch arrives in WBPMS and is correctly mapped to the enrolled
  employee.
- Leading-zero enrollment codes remain unchanged.
- Repeated delivery of the same device event does not create duplicate punches.
- Unknown device and unmatched enrollment events are safely rejected or placed
  in an HR review queue.
- A device serving multiple branches is handled using configured coverage and
  effective employee branch assignment.
- One-punch and multi-punch days are correctly flagged for HR review.
- Device/network outages are visible in audit records and a later retry does
  not lose or duplicate attendance.
- Employees can access only their own resulting attendance records.

## 13. Capstone documentation statement

Until the integration is implemented and tested, use this wording in the
Capstone document:

> The proposed system supports future network-based integration with the
> Realand A-C071 biometric attendance terminal through a secured local gateway.
> The gateway transfers validated attendance events to WBPMS over HTTPS, while
> WBPMS stores attendance records in MySQL. The current prototype retains
> legacy `.xls` attendance import as the operational workflow and fallback.
