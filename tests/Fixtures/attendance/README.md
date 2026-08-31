# Attendance test fixtures

`XlsFixture` builds the sanitized legacy `.xls` workbook at test time from
the data declared in the test. This avoids copying the private source biometric
exports into the test suite while still testing the exact `Reader\Xls` path.

`valid-daily-log.expected.json` is the reviewable golden parser output for the
valid/leading-zero/multi-token case. Additional tests must cover malformed
headers, formula cells, corrupt/renamed files, date-context mismatch, resource
limits, duplicate imports, unmatched enrollments, branch coverage, transfer
history, and transactional rollback once the shared PDO test foundation lands.
