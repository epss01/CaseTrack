<?php

namespace App\Services;

use App\Models\CaseModel;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;

class ChrMonitoringExportService
{
    /** Extra people occupy parallel rows, never a Cartesian product. */
    public function rows(CaseModel $case): array
    {
        $rows = [];
        $timeline = $case->timeline;
        $victims = $case->victims->sortBy('id')->values();
        $respondents = $case->respondents->sortBy('id')->values();
        $complainants = $case->complainants->sortBy('id')->values();
        $count = max(1, $victims->count(), $respondents->count(), $complainants->count());

        for ($i = 0; $i < $count; $i++) {
            $victim = $victims->get($i);
            $respondent = $respondents->get($i);
            $rows[] = [
                null, $case->docket_no, $case->case_title, $complainants->get($i)?->name,
                $victim?->name, $victim?->age, $victim?->status, $victim?->sector,
                $respondent?->name, $respondent?->age, $respondent?->status, $respondent?->sector,
                $case->incident_details, $case->source_info,
                $timeline?->date_of_docket?->format('Y-m-d'),
                $timeline?->date_submission_rop?->format('Y-m-d'),
                $timeline?->date_extension_30_days_requested?->format('Y-m-d'),
                $timeline?->date_submission_60th_day_rorp?->format('Y-m-d'),
                $timeline?->date_submission_120th_day_rop?->format('Y-m-d'),
                $timeline?->target_date_fir?->format('Y-m-d'),
                $timeline?->date_fir_submitted?->format('Y-m-d'),
                $timeline?->date_submitted_to,
            ];
        }

        return $rows;
    }

    public function workbook(Collection $cases): Spreadsheet
    {
        $book = IOFactory::load(resource_path('templates/chr-monitoring.xlsx'));
        $template = clone $book->getActiveSheet();
        $book->removeSheetByIndex(0);
        $groups = $cases->groupBy('investigator_id');
        if ($groups->isEmpty()) {
            $groups = collect(['' => collect()]);
        }

        foreach ($groups as $investigatorId => $records) {
            $sheet = clone $template;
            $investigator = $records->first()?->investigator;
            // The ID guarantees unique sheet names even when staff share a name.
            $name = preg_replace('~[\\\\/?*\[\]:]~u', '-', $investigator?->full_name ?? 'No matching cases');
            $name = trim($name, " '\t\n\r");
            $suffix = $investigatorId === '' ? '' : ' ('.$investigatorId.')';
            $sheet->setTitle(mb_substr($name, 0, 31 - mb_strlen($suffix)).$suffix);
            $book->addSheet($sheet);
            // A username also resolves unambiguously when full names collide at import.
            $sheet->setCellValueExplicit('A6', 'NAME OF INVESTIGATOR: '.($investigator?->username ?? ''), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('B4', 'Office: '.($investigator?->office_region ?? ''), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('A7', $investigator?->full_name ?? 'No cases match these filters.', DataType::TYPE_STRING);
            $rowNumber = 12;

            foreach ($records as $index => $case) {
                $firstRow = $rowNumber;
                foreach ($this->rows($case) as $values) {
                    $values[0] = $index + 1;
                    foreach ($values as $column => $value) {
                        // Excel cannot auto-fit merged text; show long case fields once, unmerged.
                        if ($rowNumber > $firstRow && in_array($column, [2, 12, 13, 21], true)) {
                            $value = null;
                        }
                        $coordinate = Coordinate::stringFromColumnIndex($column + 1).$rowNumber;
                        if ($value !== null && $column >= 14 && $column <= 20) {
                            $sheet->setCellValueExplicit($coordinate, Date::PHPToExcel(new \DateTimeImmutable($value)), DataType::TYPE_NUMERIC);
                            $sheet->getStyle($coordinate)->getNumberFormat()->setFormatCode('yyyy-mm-dd');
                        } else {
                            $sheet->setCellValueExplicit($coordinate, $value ?? '', DataType::TYPE_STRING);
                        }
                    }
                    $sheet->getRowDimension($rowNumber)->setRowHeight(-1);
                    $rowNumber++;
                }
                if ($rowNumber - $firstRow > 1) {
                    foreach (['A', 'B', 'O', 'P', 'Q', 'R', 'S', 'T', 'U'] as $column) {
                        $sheet->mergeCells($column.$firstRow.':'.$column.($rowNumber - 1));
                    }
                }
            }

            $lastRow = max(12, $rowNumber - 1);
            $sheet->getStyle('A12:V'.$lastRow)->getAlignment()->setWrapText(true)->setVertical('top');
            $sheet->getStyle('A12:V'.$lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->freezePane('E12');
            $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 11);
            $sheet->getPageSetup()->setPrintArea('A1:V'.$lastRow)->setFitToWidth(1)->setFitToHeight(0);
        }

        $book->setActiveSheetIndex(0);

        return $book;
    }
}
