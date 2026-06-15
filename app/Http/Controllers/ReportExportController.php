<?php

namespace App\Http\Controllers;

use App\Models\AcademicRiskEvent;
use App\Models\AiDiagnostic;
use App\Models\Appointment;
use App\Models\CounselingSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReportExportController extends Controller
{
    public function export(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole('admin')) {
            return response()->json(['message' => 'Admin access required'], 403);
        }

        $validated = $request->validate([
            'report' => 'required|in:overview,risk_trends,counselor_utilization,faculty_summary',
            'format' => 'required|in:csv,xlsx,pdf',
            'days' => 'sometimes|integer|min:7|max:365',
        ]);

        $report = $validated['report'];
        $format = $validated['format'];
        $days = (int) ($validated['days'] ?? 180);
        [$title, $headers, $rows] = $this->buildReportRows($report, $days);

        return match ($format) {
            'csv' => $this->streamCsv($report, $headers, $rows),
            'xlsx' => $this->streamXlsx($report, $headers, $rows),
            'pdf' => $this->streamPdf($title, $report, $headers, $rows),
        };
    }

    private function buildReportRows(string $report, int $days): array
    {
        return match ($report) {
            'overview' => $this->buildOverviewRows(),
            'risk_trends' => $this->buildRiskTrendRows($days),
            'counselor_utilization' => $this->buildCounselorUtilizationRows($days),
            'faculty_summary' => $this->buildFacultySummaryRows($days),
            default => ['Report', ['Metric', 'Value'], []],
        };
    }

    private function buildOverviewRows(): array
    {
        $totalUsers = User::query()->count();
        $students = User::query()->whereHas('roles', fn ($q) => $q->where('role', 'student')->where('approved', true))->count();
        $counselors = User::query()->whereHas('roles', fn ($q) => $q->where('role', 'counselor')->where('approved', true))->count();
        $sessions = CounselingSession::query()->count();
        $activeSessions = CounselingSession::query()->where('status', 'active')->count();
        $appointments = Appointment::query()->count();
        $diagnostics = AiDiagnostic::query()->count();

        return [
            'AUCMS Overview',
            ['Metric', 'Value'],
            [
                ['Total Users', $totalUsers],
                ['Students', $students],
                ['Counselors', $counselors],
                ['Total Sessions', $sessions],
                ['Active Sessions', $activeSessions],
                ['Total Appointments', $appointments],
                ['Total Diagnostics', $diagnostics],
            ],
        ];
    }

    private function buildRiskTrendRows(int $days): array
    {
        $since = now()->subDays($days);
        $rows = AiDiagnostic::query()
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as period, risk_level, COUNT(*) as total")
            ->where('created_at', '>=', $since)
            ->whereNotNull('risk_level')
            ->groupBy('period', 'risk_level')
            ->orderBy('period')
            ->get()
            ->map(fn ($row) => [
                $row->period,
                (string) $row->risk_level,
                (int) $row->total,
            ])
            ->values()
            ->all();

        return [
            'Risk Trends',
            ['Period', 'Risk Level', 'Count'],
            $rows,
        ];
    }

    private function buildCounselorUtilizationRows(int $days): array
    {
        $since = now()->subDays($days);
        $rows = User::query()
            ->whereHas('roles', fn ($q) => $q->where('role', 'counselor')->where('approved', true))
            ->withCount([
                'counselorSessions as sessions_in_window_count' => fn ($q) => $q->where('created_at', '>=', $since),
                'appointmentsAsCounselor as appointments_in_window_count' => fn ($q) => $q->where('created_at', '>=', $since),
            ])
            ->orderByDesc('sessions_in_window_count')
            ->get()
            ->map(fn ($c) => [
                'Counselor #'.str_pad((string) $c->id, 4, '0', STR_PAD_LEFT),
                (int) $c->sessions_in_window_count,
                (int) $c->appointments_in_window_count,
            ])
            ->values()
            ->all();

        return [
            'Counselor Utilization',
            ['Counselor', 'Sessions', 'Appointments'],
            $rows,
        ];
    }

    private function buildFacultySummaryRows(int $days): array
    {
        $since = now()->subDays($days);
        $rows = AcademicRiskEvent::query()
            ->select('faculty', 'risk_type', DB::raw('COUNT(*) as total'))
            ->where('created_at', '>=', $since)
            ->groupBy('faculty', 'risk_type')
            ->orderBy('faculty')
            ->orderBy('risk_type')
            ->get()
            ->map(fn ($row) => [
                (string) ($row->faculty ?: 'Unknown'),
                (string) $row->risk_type,
                (int) $row->total,
            ])
            ->values()
            ->all();

        return [
            'Faculty Risk Summary',
            ['Faculty', 'Risk Type', 'Count'],
            $rows,
        ];
    }

    private function streamCsv(string $report, array $headers, array $rows)
    {
        $tmp = tempnam(sys_get_temp_dir(), 'aucms-report-');
        $file = fopen($tmp, 'wb');
        if (! $file) {
            return response()->json(['message' => 'Unable to generate CSV export'], 500);
        }

        fputcsv($file, $headers);
        foreach ($rows as $row) {
            fputcsv($file, $row);
        }
        fclose($file);

        $fileName = sprintf('%s-%s.csv', Str::slug($report), now()->format('Ymd-His'));

        return response()->download($tmp, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ])->deleteFileAfterSend(true);
    }

    private function streamXlsx(string $report, array $headers, array $rows)
    {
        if (! class_exists(\ZipArchive::class)) {
            return response()->json(['message' => 'XLSX export requires ZipArchive extension'], 500);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'aucms-report-');
        $zip = new \ZipArchive;
        if ($zip->open($tmp, \ZipArchive::OVERWRITE) !== true) {
            return response()->json(['message' => 'Unable to generate XLSX export'], 500);
        }

        $sheetRows = array_merge([$headers], $rows);
        $sheetXml = $this->buildSheetXml($sheetRows);

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->rootRelsXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelsXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->close();

        $fileName = sprintf('%s-%s.xlsx', Str::slug($report), now()->format('Ymd-His'));

        return response()->download($tmp, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private function streamPdf(string $title, string $report, array $headers, array $rows)
    {
        $lines = [];
        $lines[] = $title.' - '.now()->toDateTimeString();
        $lines[] = implode(' | ', array_map('strval', $headers));
        foreach ($rows as $row) {
            $lines[] = implode(' | ', array_map('strval', $row));
        }

        $pdfBinary = $this->buildSimplePdf($lines);
        $tmp = tempnam(sys_get_temp_dir(), 'aucms-report-');
        file_put_contents($tmp, $pdfBinary);

        $fileName = sprintf('%s-%s.pdf', Str::slug($report), now()->format('Ymd-His'));

        return response()->download($tmp, $fileName, [
            'Content-Type' => 'application/pdf',
        ])->deleteFileAfterSend(true);
    }

    private function buildSheetXml(array $rows): string
    {
        $xmlRows = [];
        foreach ($rows as $rowIndex => $row) {
            $cells = [];
            foreach (array_values($row) as $colIndex => $value) {
                $cellRef = $this->xlsxCellRef($rowIndex + 1, $colIndex + 1);
                if (is_numeric($value)) {
                    $cells[] = sprintf('<c r="%s" t="n"><v>%s</v></c>', $cellRef, (string) $value);
                } else {
                    $safe = htmlspecialchars((string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
                    $cells[] = sprintf('<c r="%s" t="inlineStr"><is><t>%s</t></is></c>', $cellRef, $safe);
                }
            }
            $xmlRows[] = sprintf('<row r="%d">%s</row>', $rowIndex + 1, implode('', $cells));
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetData>'.implode('', $xmlRows).'</sheetData>'
            .'</worksheet>';
    }

    private function xlsxCellRef(int $row, int $column): string
    {
        $letters = '';
        while ($column > 0) {
            $mod = ($column - 1) % 26;
            $letters = chr(65 + $mod).$letters;
            $column = intdiv($column - 1, 26);
        }

        return $letters.$row;
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'</Types>';
    }

    private function rootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Report" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
    }

    private function workbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'</Relationships>';
    }

    private function buildSimplePdf(array $lines): string
    {
        $maxLines = 40;
        $safeLines = array_slice($lines, 0, $maxLines);
        $content = "BT\n/F1 10 Tf\n50 800 Td\n";
        foreach ($safeLines as $index => $line) {
            $lineText = $this->pdfEscape((string) $line);
            if ($index > 0) {
                $content .= "T*\n";
            }
            $content .= "({$lineText}) Tj\n";
        }
        $content .= 'ET';

        $objects = [];
        $objects[] = '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj';
        $objects[] = '2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj';
        $objects[] = '3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 612 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >> endobj';
        $objects[] = '4 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj';
        $objects[] = '5 0 obj << /Length '.strlen($content)." >> stream\n{$content}\nendstream endobj";

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object."\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= 'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    private function pdfEscape(string $value): string
    {
        return str_replace(
            ['\\', '(', ')', "\r", "\n", "\t"],
            ['\\\\', '\\(', '\\)', ' ', ' ', ' '],
            $value
        );
    }
}
