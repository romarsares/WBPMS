# Class Diagram

Derived from actual PHP source files under `app/`. Only classes, interfaces,
enums, and key methods/properties that exist in the codebase are shown.
Namespace prefix `Wbpms\` is omitted for readability.

```mermaid
classDiagram
    %% ══════════════════════════════════════════════════════════════════
    %% INFRASTRUCTURE LAYER
    %% ══════════════════════════════════════════════════════════════════

    class Connection {
        +pdo() PDO
        +transactional(callable) mixed
    }

    class DatabaseSessionHandler {
        -Connection connection
        +open(path, name) bool
        +close() bool
        +read(id) string
        +write(id, data) bool
        +destroy(id) bool
        +gc(maxlifetime) int
    }
    DatabaseSessionHandler ..|> SessionHandlerInterface

    class AbstractRepository {
        #Connection connection
        #pdo() PDO
    }

    class EmployeeRepository {
        +findById(id) array
        +findAll(filters) array
        +create(data) int
        +update(id, data) void
        +archive(id, reason) void
        +findBranchAssignmentAt(employeeId, date) array
    }
    EmployeeRepository --|> AbstractRepository

    class EmployeeLifecycleRepository {
        +createEpisode(employeeId, data) int
        +closeEpisode(episodeId, endDate, reason) void
        +logEvent(employeeId, eventType, data) void
    }
    EmployeeLifecycleRepository --|> AbstractRepository

    class EmployeeDocumentRepository {
        +upload(employeeId, data) int
        +findByEmployee(employeeId) array
        +archive(documentId) void
        +verify(documentId, verifiedBy) void
    }
    EmployeeDocumentRepository --|> AbstractRepository

    class ScheduleRepository {
        +findEffective(employeeId, date) array
        +create(data) int
        +update(id, data) void
    }
    ScheduleRepository --|> AbstractRepository

    class PositionRepository {
        +findAll(activeOnly) array
        +create(data) int
        +update(id, data) void
        +toggle(id) void
    }
    PositionRepository --|> AbstractRepository

    class AuditRepository {
        +log(userId, eventType, action, tableAffected, recordId, description, ip, ua) void
    }
    AuditRepository --|> AbstractRepository

    class PdoAttendanceImportGateway {
        -Connection connection
        +transactional(callable) mixed
        +hasCompletedChecksum(sha256) bool
        +createImportBatch(deviceId, userId, year, month, sha256, parserVersion) int
        +match(ParsedPunch) AttendancePunchMatch
        +effectiveSchedule(scheduleId, date) WorkSchedule
        +isDuplicatePunch(ParsedPunch) bool
        +retainUnmatchedPunch(batchId, ParsedPunch, reason) void
        +retainMatchedPunch(batchId, ParsedPunch, empId, branchId) void
        +saveGeneratedAttendance(batchId, GeneratedAttendance) void
        +completeImportBatch(batchId, AttendanceImportSummary) void
    }
    PdoAttendanceImportGateway ..|> AttendanceImportGateway

    class PdfRenderer {
        +render(html) string
        +renderPayslip(payrollId, data) string
    }

    class Mailer {
        +sendPasswordResetEmail(email, otp, username) void
        +sendNotification(to, subject, body) void
    }

    %% ══════════════════════════════════════════════════════════════════
    %% APPLICATION LAYER — Services
    %% ══════════════════════════════════════════════════════════════════

    class AuthService {
        -Connection connection
        +attemptLogin(email, password, requestId, ip, ua) array|null
        +logout(sessionId) void
    }
    AuthService --> Connection

    class AuthenticationPolicy {
        +isPasswordStrong(password) bool
        +isAccountLockable(failedAttempts) bool
    }

    class PasswordResetService {
        -Connection connection
        -Mailer mailer
        +sendResetLink(email) void
        +verifyOtpAndReset(email, otp, newPassword) void
    }
    PasswordResetService --> Connection
    PasswordResetService --> Mailer

    class UserService {
        -Connection connection
        +list(includeArchived, position, username, status) array
        +findOrFail(userId) array
        +create(data, actorId) int
        +update(userId, data, actorId) void
        +toggleStatus(userId, actorId) string
        +archive(userId, actorId) void
        +roles() array
        +unlinkedEmployees() array
        +generateTemporaryPassword() string
    }
    UserService --> Connection

    class EmployeeLifecycleService {
        -EmployeeLifecycleRepository repository
        +archive(employeeId, lastWorkingDate, reason, note, archivedBy) void
        +rehire(employeeId, data, rehiredBy) int
    }
    EmployeeLifecycleService --> EmployeeLifecycleRepository

    class EmployeeDocumentService {
        -EmployeeDocumentRepository repository
        +upload(employeeId, file, data, uploadedBy) int
        +replace(documentId, file, data, uploadedBy) int
        +verify(documentId, verifiedBy) void
        +archive(documentId) void
        +findByEmployee(employeeId) array
        +view(documentId) array
    }
    EmployeeDocumentService --> EmployeeDocumentRepository

    class EmployeeSetupService {
        +createWithBranchAndSchedule(data, branchId, scheduleData, actorId) int
    }

    class SalaryService {
        -Connection connection
        +getCurrentRate(employeeId) array
        +createStructure(data, actorId) int
        +updateRate(employeeId, newRate, actorId) void
        +archive(salaryId, actorId) void
        +listByEmployee(employeeId) array
    }
    SalaryService --> Connection

    class RequestService {
        -Connection connection
        +submit(employeeId, typeId, payload) int
        +checkLeaveBalance(employeeId, leaveType) int
        +approve(requestId, reviewerId) void
        +cancel(requestId, reviewerId) void
        +archive(requestId, actorId) void
        +list(filters) array
        +findOrFail(requestId) array
        +adjustLeaveCredit(employeeId, delta, actorId, note) void
        +listCashAdvances(filters) array
    }
    RequestService --> Connection

    class PayrollService {
        -Connection connection
        -HolidayPayCalculator holidayCalc
        -ApprovedOvertimeCalculator approvedOvertimeCalc
        +createPeriod(startDate, actorId) int
        +createPayrollRun(periodId, branchId, actorId) int
        +computeRun(runId) void
        +submitForApproval(runId) void
        +ownerReview(runId, decision, note) void
        +cancel(runId, actorId) void
        +adjustPayroll(payrollId, data, actorId) void
        +compute13thMonthPay(employeeId, year) float
        +prepareDepositSlipList(runId) array
    }
    PayrollService --> Connection
    PayrollService --> HolidayPayCalculator
    PayrollService --> ApprovedOvertimeCalculator

    class PayrollAdjustmentService {
        -Connection connection
        +applyManualAdjustment(payrollId, data, actorId) void
    }
    PayrollAdjustmentService --> Connection

    class AttendanceAdjustmentService {
        -Connection connection
        +adjust(attendanceId, newValues, reason, adjustedBy) void
    }
    AttendanceAdjustmentService --> Connection

    class AttendancePolicyService {
        -Connection connection
        +evaluateDisciplinaryFlags(employeeId) array
        +reviewFlag(flagId, action, note, reviewedBy) void
    }
    AttendancePolicyService --> Connection

    %% ── Attendance Import ────────────────────────────────────────────
    class AttendanceImportGateway {
        <<interface>>
        +transactional(callable) mixed
        +hasCompletedChecksum(sha256) bool
        +createImportBatch(deviceId, userId, year, month, sha256, version) int
        +match(ParsedPunch) AttendancePunchMatch
        +effectiveSchedule(scheduleId, date) WorkSchedule
        +isDuplicatePunch(ParsedPunch) bool
        +retainUnmatchedPunch(batchId, ParsedPunch, reason) void
        +retainMatchedPunch(batchId, ParsedPunch, empId, branchId) void
        +saveGeneratedAttendance(batchId, GeneratedAttendance) void
        +completeImportBatch(batchId, AttendanceImportSummary) void
    }

    class AttendanceImportService {
        -AttendanceFileParser parser
        -AttendanceImportGateway gateway
        -TimesheetGenerator timesheetGenerator
        +import(deviceId, year, month, uploadedFile, uploadedBy) AttendanceImportSummary
    }
    AttendanceImportService --> AttendanceFileParser
    AttendanceImportService --> AttendanceImportGateway
    AttendanceImportService --> TimesheetGenerator

    class AttendanceImportSummary {
        +int parsed
        +int matched
        +int unmatched
        +int duplicatesSkipped
        +int incompleteDays
        +int multiPunchDays
    }

    class AttendancePunchMatch {
        +int|null employeeId
        +int|null branchId
        +string status
    }

    class EmployeeScope {
        +int employeeId
        +assertOwns(resourceEmployeeId) void
    }

    %% ══════════════════════════════════════════════════════════════════
    %% DOMAIN LAYER
    %% ══════════════════════════════════════════════════════════════════

    class AttendanceFileParser {
        <<interface>>
        +supports(UploadedAttendanceFile) bool
        +parse(UploadedAttendanceFile, ParserContext) ParsedAttendanceFile
    }

    class LdeXlsDailyLogParser {
        +supports(UploadedAttendanceFile) bool
        +parse(UploadedAttendanceFile, ParserContext) ParsedAttendanceFile
    }
    LdeXlsDailyLogParser ..|> AttendanceFileParser

    class ParserContext {
        +int sourceYear
        +int sourceMonth
        +int deviceId
        +string timezone
    }

    class UploadedAttendanceFile {
        +string temporaryPath
        +string originalName
        +string sha256
    }

    class ParsedAttendanceFile {
        +ParsedPunch[] punches
        +int year
        +int month
    }

    class ParsedPunch {
        +string deviceEmployeeCode
        +string sourceLocalAt
        +int workbookRow
        +string dateColumn
    }

    class AttendanceParseException {
        +string errorCode
        +string detail
    }

    class ParserError {
        +string code
        +string message
        +int row
        +string column
    }

    class WorkSchedule {
        +int scheduleId
        +string startTime
        +string endTime
        +int unpaidBreakMinutes
        +int standardMinutes
        +int graceMinutes
        +startOn(date, timezone) DateTimeImmutable
        +endOn(date, timezone) DateTimeImmutable
    }

    class TimesheetGenerator {
        +generate(matchedPunches, WorkSchedule) GeneratedAttendance[]
    }
    TimesheetGenerator --> WorkSchedule

    class GeneratedAttendance {
        +int employeeId
        +int branchId
        +string attendanceDate
        +string|null timeIn
        +string|null timeOut
        +int hoursWorkedMinutes
        +int lateMinutes
        +int undertimeMinutes
        +int overtimeMinutes
        +bool isIncomplete
        +bool isMultiPunch
    }

    class AttendancePolicyEvaluator {
        +evaluate(records) AttendancePolicyFlag[]
        +hasConsecutiveLates(records, threshold) bool
        +hasAbsenceRun(records, threshold) bool
    }

    class HolidayPayCalculator {
        +calculate(dailyRate, holidayRecord, worked, isRestDay, overtimeMinutes, stdMinutes) float
    }

    class HolidayRecord {
        +date holidayDate
        +HolidayType type
        +float payMultiplier
    }

    class HolidayType {
        <<enumeration>>
        Regular
        Special
        SpecialNonWorking
    }

    class ApprovedOvertimeCalculator {
        +calculate(dailyRate, overtimeMinutes, stdMinutes) float
    }

    %% ══════════════════════════════════════════════════════════════════
    %% HTTP LAYER — Controllers
    %% ══════════════════════════════════════════════════════════════════

    class AuthController {
        +showLogin() void
        +login() void
        +logout() void
        +showForgotPassword() void
        +sendResetLink() void
        +showResetLinkSent() void
        +showResetPassword() void
        +resetPassword() void
        +showChangePassword() void
        +changePassword() void
    }
    AuthController --> AuthService
    AuthController --> PasswordResetService

    class UserController {
        +index() void
        +create() void
        +store() void
        +edit(params) void
        +update(params) void
        +toggle(params) void
        +archive(params) void
        +resetPassword(params) void
    }
    UserController --> UserService

    class EmployeeController {
        +index() void
        +create() void
        +store() void
        +show(params) void
        +editForm(params) void
        +update(params) void
        +transferForm(params) void
        +transfer(params) void
        +archiveForm(params) void
        +archive(params) void
        +rehireForm(params) void
        +rehire(params) void
        +bankForm(params) void
        +saveBank(params) void
        +documents(params) void
        +uploadDocument(params) void
        +viewDocument(params) void
        +replaceDocument(params) void
        +verifyDocument(params) void
        +archiveDocument(params) void
    }
    EmployeeController --> EmployeeLifecycleService
    EmployeeController --> EmployeeDocumentService

    class AttendanceController {
        +index() void
        +import() void
        +upload() void
        +confirmImport() void
        +approveImport(params) void
        +cancelImport(params) void
        +adjustForm(params) void
        +adjust(params) void
        +frequencyReport() void
    }
    AttendanceController --> AttendanceImportService
    AttendanceController --> AttendanceAdjustmentService

    class AttendancePolicyController {
        +index() void
        +evaluate() void
        +review(params) void
    }
    AttendancePolicyController --> AttendancePolicyService

    class RequestsController {
        +index() void
        +create() void
        +store() void
        +show(params) void
        +approve(params) void
        +cancel(params) void
        +archive(params) void
        +leaveCredits() void
        +leaveCreditsEmployee(params) void
        +adjustLeaveCredit(params) void
        +cashAdvanceTracker() void
    }
    RequestsController --> RequestService

    class PayrollController {
        +index() void
        +create() void
        +store() void
        +show(params) void
        +compute(params) void
        +submit(params) void
        +cancel(params) void
        +adjustForm(params) void
        +adjust(params) void
        +printTimesheet(params) void
        +disbursementSummary(params) void
        +disbursementExport(params) void
    }
    PayrollController --> PayrollService
    PayrollController --> PayrollAdjustmentService

    class OwnerController {
        +payrollList() void
        +reviewForm(params) void
        +approve(params) void
        +returnRun(params) void
        +requestList() void
        +requestShow(params) void
        +requestApprove(params) void
        +requestReturn(params) void
        +requestCancel(params) void
    }
    OwnerController --> PayrollService
    OwnerController --> RequestService

    class ScheduleController {
        +index() void
        +store() void
        +assignForm() void
        +assign() void
        +editForm(params) void
        +update(params) void
    }
    ScheduleController --> ScheduleRepository

    class SalaryController {
        +index() void
        +create() void
        +store() void
        +edit(params) void
        +update(params) void
        +archive(params) void
    }
    SalaryController --> SalaryService

    class BenefitsController {
        +index() void
        +policies() void
        +approvePolicy(params) void
        +lockRecord(params) void
        +unlockRecord(params) void
    }

    class ReportsController {
        +index() void
        +preview(params) void
        +export() void
        +thirteenthMonth() void
        +transactionSlips() void
        +payslipsReport() void
        +bulkPrintPayslips() void
    }
    ReportsController --> PayrollService
    ReportsController --> PdfRenderer

    class EmployeePortalController {
        +attendance() void
        +requests() void
        +requestForm() void
        +storeRequest() void
        +cancelRequest(params) void
        +payslips() void
        +payslipDetail(params) void
        +payslipPrint(params) void
        +payslipPdf(params) void
        +notices() void
        +acknowledgeNotice(params) void
    }
    EmployeePortalController --> RequestService
    EmployeePortalController --> PdfRenderer
    EmployeePortalController --> EmployeeScope

    class DashboardController {
        +ownerDashboard() void
        +hrDashboard() void
        +empDashboard() void
    }

    class SettingsController {
        +index() void
        +positions() void
        +storePosition() void
        +togglePosition(params) void
        +updatePosition(params) void
        +periods() void
        +storePeriod() void
        +holidays() void
        +storeHoliday() void
        +editHoliday(params) void
        +updateHoliday(params) void
        +deleteHoliday(params) void
    }
    SettingsController --> PayrollService

    %% ── Middleware ──────────────────────────────────────────────────
    class AuthMiddleware {
        +handle(request) void
        +identity()$ array|null
    }

    class CsrfMiddleware {
        +generateToken()$ string
        +verify()$ void
    }

    %% ── Router ──────────────────────────────────────────────────────
    class Router {
        +add(method, path, handler, roles) void
        +dispatch(method, uri) void
    }
    Router --> AuthMiddleware
    Router --> CsrfMiddleware

    %% ══════════════════════════════════════════════════════════════════
    %% KEY CROSS-LAYER DEPENDENCIES
    %% ══════════════════════════════════════════════════════════════════

    PdoAttendanceImportGateway --> Connection
    PdfRenderer --> Connection
    AttendancePolicyEvaluator --> WorkSchedule
    HolidayPayCalculator --> HolidayRecord
    HolidayRecord --> HolidayType
    LdeXlsDailyLogParser --> ParserContext
    LdeXlsDailyLogParser --> ParsedPunch
    LdeXlsDailyLogParser --> ParsedAttendanceFile
    TimesheetGenerator --> GeneratedAttendance
    AttendanceImportService --> AttendanceImportSummary
    AttendanceImportService --> AttendancePunchMatch
```

## Notes

- `$` suffix on a method denotes `static` (PHP `static` method, e.g. `AuthMiddleware::identity()`).
- `SessionHandlerInterface` is PHP's native built-in interface; `DatabaseSessionHandler` implements it.
- Controllers have no direct inheritance; they are plain `final` classes wired by the router.
- `Connection` is not `final` (to allow test doubles via `getMockBuilder()`).
- Services not backed by a dedicated repository (e.g. `PayrollService`, `UserService`, `RequestService`) operate directly on the PDO handle from `Connection`.
- `StubController`, `UserManagementController`, and `HealthController` are omitted — they are scaffolds or trivial health-check handlers with no business logic.
- `EmployeeSetupGateway` interface mirrors `AttendanceImportGateway` pattern for Developer A's foundation layer; not shown to avoid duplication.
