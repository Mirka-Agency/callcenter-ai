<?php

namespace App\Support;

use App\DTOs\ReportFilter;
use App\Enums\ReportDatePreset;
use App\Services\EmployerContext;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportFilterFactory
{
    public static function fromRequest(Request $request, ?int $forceEmployeeId = null): ReportFilter
    {
        $preset = ReportDatePreset::tryFrom((string) $request->query('preset', ReportDatePreset::Last30->value))
            ?? ReportDatePreset::Last30;

        $employeeIds = $forceEmployeeId !== null
            ? [$forceEmployeeId]
            : array_values(array_unique(array_map(
                intval(...),
                (array) $request->query('employees', []),
            )));

        return ReportFilter::make(
            organizationId: EmployerContext::organizationId(),
            preset: $preset,
            customFrom: $request->filled('from') ? Carbon::parse($request->string('from')->toString()) : null,
            customTo: $request->filled('to') ? Carbon::parse($request->string('to')->toString()) : null,
            employeeIds: $employeeIds,
            compareMode: $request->boolean('compare'),
        );
    }
}
