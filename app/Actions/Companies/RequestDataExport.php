<?php

namespace App\Actions\Companies;

use App\Enums\DataExportStatus;
use App\Jobs\BuildCompanyDataExport;
use App\Models\Company;
use App\Models\DataExportRequest;
use App\Models\User;

class RequestDataExport
{
    public function handle(Company $company, User $requestedBy): DataExportRequest
    {
        $export = new DataExportRequest;
        $export->forceFill([
            'company_id' => $company->id,
            'requested_by_id' => $requestedBy->id,
            'status' => DataExportStatus::Pending,
            'requested_at' => now(),
        ])->save();

        BuildCompanyDataExport::dispatch($export->id);

        return $export;
    }
}
