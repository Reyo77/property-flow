<?php

use App\Enums\DataExportStatus;
use App\Jobs\BuildCompanyDataExport;
use App\Models\Community;
use App\Models\DataExportRequest;
use App\Models\Resident;
use App\Models\Unit;
use App\Notifications\CompanyDataExportReady;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

it('builds a zip of the company\'s data, marks the export ready and notifies the requester', function () {
    Storage::fake('local');
    Notification::fake();

    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create(['name' => 'Harbour Towers']);
    Unit::factory()->for($community)->create(['number' => '101']);
    Resident::factory()->for($admin->company)->create(['name' => 'Priya Patel']);

    $export = DataExportRequest::factory()->for($admin->company)->create(['requested_by_id' => $admin->id]);

    app(BuildCompanyDataExport::class, ['dataExportRequestId' => $export->id])->handle();

    $export->refresh();

    expect($export->status)->toBe(DataExportStatus::Ready)
        ->and($export->disk_path)->not->toBeNull()
        ->and($export->completed_at)->not->toBeNull();

    Storage::disk('local')->assertExists($export->disk_path);

    $zip = new ZipArchive;
    $zip->open(Storage::disk('local')->path($export->disk_path));

    expect($zip->getFromName('communities.csv'))->toContain('Harbour Towers')
        ->and($zip->getFromName('units.csv'))->toContain('101')
        ->and($zip->getFromName('residents.csv'))->toContain('Priya Patel');

    $zip->close();

    Notification::assertSentTo($admin, CompanyDataExportReady::class);
});

it('does nothing for a request that is no longer pending', function () {
    Notification::fake();

    $admin = companyAdmin();
    $export = DataExportRequest::factory()->for($admin->company)->ready()->create(['disk_path' => 'company-exports/already-done.zip']);

    app(BuildCompanyDataExport::class, ['dataExportRequestId' => $export->id])->handle();

    expect($export->fresh()->disk_path)->toBe('company-exports/already-done.zip');
    Notification::assertNothingSent();
});
