<?php

declare(strict_types=1);

namespace Wbpms\Application\Attendance;

use Wbpms\Domain\Attendance\Parsing\AttendanceFileParser;
use Wbpms\Domain\Attendance\Parsing\ParserContext;
use Wbpms\Domain\Attendance\Parsing\ParsedPunch;
use Wbpms\Domain\Attendance\Parsing\UploadedAttendanceFile;
use Wbpms\Domain\Attendance\TimesheetGenerator;

/**
 * B3 orchestration: parse, match, retain evidence, and generate daily records
 * in the single transaction provided by the shared persistence foundation.
 */
final readonly class AttendanceImportService
{
    public function __construct(
        private AttendanceFileParser $parser,
        private AttendanceImportGateway $gateway,
        private TimesheetGenerator $timesheetGenerator,
    ) {
    }

    public function import(UploadedAttendanceFile $file, ParserContext $context, int $uploadedByUserId): AttendanceImportSummary
    {
        if ($this->gateway->hasCompletedChecksum($file->sha256)) {
            throw new DuplicateAttendanceFileException();
        }
        $parsed = $this->parser->parse($file, $context);

        return $this->gateway->transactional(function () use ($context, $uploadedByUserId, $parsed): AttendanceImportSummary {
            $batchId = $this->gateway->createImportBatch(
                $context->deviceId,
                $uploadedByUserId,
                $context->sourceYear,
                $context->sourceMonth,
                $parsed->sha256,
                $parsed->parserVersion,
            );
            $groups = [];
            $byBranch = [];
            $matched = $unmatched = $duplicates = 0;

            foreach ($parsed->punches as $punch) {
                if ($this->gateway->isDuplicatePunch($punch)) {
                    $duplicates++;
                    continue;
                }
                $match = $this->gateway->match($punch);
                if ($match->status !== AttendancePunchMatch::MATCHED) {
                    $unmatched++;
                    $this->gateway->retainUnmatchedPunch($batchId, $punch, $match->status);
                    continue;
                }
                $matched++;
                $this->gateway->retainMatchedPunch($batchId, $punch, $match->employeeId, $match->branchId);
                $key = $match->employeeId . ':' . $punch->localTimestamp->format('Y-m-d');
                $groups[$key] ??= ['employeeId' => $match->employeeId, 'branchId' => $match->branchId, 'scheduleId' => $match->scheduleId, 'punches' => []];
                $groups[$key]['punches'][] = $punch;
            }

            $incomplete = $multiPunch = 0;
            foreach ($groups as $group) {
                /** @var list<ParsedPunch> $punches */
                $punches = $group['punches'];
                $date = $punches[0]->localTimestamp->format('Y-m-d');
                $attendance = $this->timesheetGenerator->generate(
                    $group['employeeId'],
                    $date,
                    $punches,
                    $this->gateway->effectiveSchedule($group['scheduleId'], $date),
                );
                $this->gateway->saveGeneratedAttendance($attendance);
                $branch = $group['branchId'];
                $byBranch[$branch] ??= ['matched' => 0, 'unmatched' => 0, 'duplicates' => 0, 'incomplete' => 0, 'multiPunch' => 0];
                $byBranch[$branch]['matched'] += count($punches);
                $incomplete += (int) $attendance->isIncomplete();
                $multiPunch += (int) in_array('MULTI_PUNCH_REVIEW', $attendance->flags, true);
                $byBranch[$branch]['incomplete'] += (int) $attendance->isIncomplete();
                $byBranch[$branch]['multiPunch'] += (int) in_array('MULTI_PUNCH_REVIEW', $attendance->flags, true);
            }
            $summary = new AttendanceImportSummary(count($parsed->punches), $matched, $unmatched, $duplicates, $incomplete, $multiPunch, $byBranch);
            $this->gateway->completeImportBatch($batchId, $summary);
            return $summary;
        });
    }
}
