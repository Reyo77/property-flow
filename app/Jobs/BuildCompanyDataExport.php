<?php

namespace App\Jobs;

use App\Enums\DataExportStatus;
use App\Models\Community;
use App\Models\Company;
use App\Models\DataExportRequest;
use App\Models\Resident;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\CompanyDataExportReady;
use App\Support\Tenancy\PermissionTeam;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Builds a zip of the company's core data as CSVs (privacy/portability request). Covers
 * communities, units, residents and team members; not every domain model, but the ones a
 * company is most likely to need for a portability or backup request.
 */
class BuildCompanyDataExport implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $dataExportRequestId) {}

    public function handle(): void
    {
        $request = DataExportRequest::query()->withoutGlobalScopes()->find($this->dataExportRequestId);

        if ($request === null || $request->status !== DataExportStatus::Pending) {
            return;
        }

        $request->forceFill(['status' => DataExportStatus::Processing])->save();

        $company = Company::query()->find($request->company_id);

        if ($company === null) {
            $request->forceFill(['status' => DataExportStatus::Failed, 'failure_reason' => __('The company no longer exists.'), 'completed_at' => now()])->save();

            return;
        }

        try {
            $diskPath = $this->build($company);

            $request->forceFill([
                'status' => DataExportStatus::Ready,
                'disk_path' => $diskPath,
                'completed_at' => now(),
            ])->save();

            $request->requestedBy?->notify(new CompanyDataExportReady($request));
        } catch (Throwable $exception) {
            $request->forceFill([
                'status' => DataExportStatus::Failed,
                'failure_reason' => Str::limit($exception->getMessage(), 500),
                'completed_at' => now(),
            ])->save();
        }
    }

    private function build(Company $company): string
    {
        $tempPath = sys_get_temp_dir().'/company-export-'.Str::uuid().'.zip';

        $zip = new ZipArchive;
        $zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $communities = Community::query()->withoutGlobalScopes()->where('company_id', $company->id)->get()
            ->map(fn (Community $community) => [$community->id, $community->name, $community->type->value, $community->address_line_1, $community->address_line_2, $community->city, $community->region, $community->postal_code, $community->country]);

        $zip->addFromString('communities.csv', $this->csv(
            ['id', 'name', 'type', 'address_line_1', 'address_line_2', 'city', 'region', 'postal_code', 'country'],
            $communities,
        ));

        $units = Unit::query()->withoutGlobalScopes()->where('company_id', $company->id)->get()
            ->map(fn (Unit $unit) => [$unit->id, $unit->community_id, $unit->building_id, $unit->number, $unit->floor, $unit->area, $unit->unit_factor, $unit->parking, $unit->locker]);

        $zip->addFromString('units.csv', $this->csv(
            ['id', 'community_id', 'building_id', 'number', 'floor', 'area', 'unit_factor', 'parking', 'locker'],
            $units,
        ));

        $residents = Resident::query()->withoutGlobalScopes()->where('company_id', $company->id)->get()
            ->map(fn (Resident $resident) => [$resident->id, $resident->name, $resident->email, $resident->phone]);

        $zip->addFromString('residents.csv', $this->csv(['id', 'name', 'email', 'phone'], $residents));

        $team = PermissionTeam::run(
            $company->id,
            fn () => User::query()->where('company_id', $company->id)->whereHas('roles')->get()
                ->map(fn (User $user) => [$user->id, $user->name, $user->email]),
        );

        $zip->addFromString('team.csv', $this->csv(['id', 'name', 'email'], $team));

        $zip->close();

        $diskPath = 'company-exports/'.$company->id.'/'.Str::uuid().'.zip';
        Storage::disk('local')->put($diskPath, (string) file_get_contents($tempPath));
        unlink($tempPath);

        return $diskPath;
    }

    /**
     * @param  list<string>  $headings
     * @param  iterable<int, list<mixed>>  $rows
     */
    private function csv(array $headings, iterable $rows): string
    {
        $handle = fopen('php://temp', 'w+');

        if ($handle === false) {
            throw new RuntimeException('Failed to open a temporary stream for the export CSV.');
        }

        fputcsv($handle, $headings);

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $content = (string) stream_get_contents($handle);
        fclose($handle);

        return $content;
    }
}
