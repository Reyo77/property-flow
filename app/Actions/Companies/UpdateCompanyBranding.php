<?php

namespace App\Actions\Companies;

use App\Models\Company;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class UpdateCompanyBranding
{
    public function handle(Company $company, string $name, ?string $brandColor, ?UploadedFile $logo): Company
    {
        $company->name = $name;
        $company->brand_color = $brandColor;

        if ($logo !== null) {
            $previousPath = $company->logo_disk_path;

            $diskPath = $logo->storeAs(
                "logos/{$company->id}",
                Str::uuid().'.'.$logo->extension(),
                'local',
            );

            if ($diskPath === false) {
                throw new RuntimeException('Failed to store the company logo.');
            }

            $company->logo_disk_path = $diskPath;

            if ($previousPath !== null) {
                Storage::disk('local')->delete($previousPath);
            }
        }

        $company->save();

        return $company;
    }
}
