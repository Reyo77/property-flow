<?php

namespace App\Http\Controllers;

use App\Enums\FinancialReport;
use App\Models\Community;
use App\Models\Invoice;
use App\Support\Finance\FinancialReports;
use App\Support\Finance\ReportExport;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FinancialReportExportController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(Request $request, Community $community, FinancialReports $reports): BinaryFileResponse
    {
        $this->authorize('viewAny', [Invoice::class, $community]);

        $validated = $request->validate([
            'report' => ['required', Rule::enum(FinancialReport::class)],
            'format' => ['required', Rule::in(['csv', 'xlsx'])],
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'account' => ['nullable', 'integer', Rule::exists('accounts', 'id')->where('community_id', $community->id)],
        ]);

        $report = FinancialReport::from($validated['report']);
        $table = $reports->build(
            $community,
            $report,
            CarbonImmutable::parse($validated['from']),
            CarbonImmutable::parse($validated['to']),
            isset($validated['account']) ? (int) $validated['account'] : null,
        );

        $filename = Str::slug("{$community->name} {$report->value} {$validated['to']}").'.'.$validated['format'];

        return Excel::download(new ReportExport($table, $community->name), $filename, $validated['format'] === 'csv' ? ExcelFormat::CSV : ExcelFormat::XLSX);
    }
}
