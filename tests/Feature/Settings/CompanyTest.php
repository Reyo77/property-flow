<?php

use App\Jobs\BuildCompanyDataExport;
use App\Livewire\Settings\Company;
use App\Models\Company as CompanyModel;
use App\Models\DataExportRequest;
use App\Models\Plan;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('redirects guests to the login page', function () {
    get(route('settings.company'))->assertRedirect(route('login'));
});

it('forbids company members without the manage-settings permission', function () {
    actingAs(memberWithoutRole());

    Livewire::test(Company::class)->assertForbidden();
});

it('updates the company name, brand color and logo', function () {
    Storage::fake('local');

    $admin = companyAdmin();

    actingAs($admin);

    Livewire::test(Company::class)
        ->set('name', 'Harbour Property Group')
        ->set('brand_color', '#1D4ED8')
        ->set('logo', UploadedFile::fake()->image('logo.png'))
        ->call('saveBranding')
        ->assertHasNoErrors();

    $company = $admin->company->fresh();

    expect($company)
        ->name->toBe('Harbour Property Group')
        ->brand_color->toBe('#1D4ED8')
        ->logo_disk_path->not->toBeNull();

    Storage::disk('local')->assertExists($company->logo_disk_path);
});

it('rejects an invalid brand color', function () {
    actingAs(companyAdmin());

    Livewire::test(Company::class)
        ->set('name', 'Harbour Property Group')
        ->set('brand_color', 'blue')
        ->call('saveBranding')
        ->assertHasErrors('brand_color');
});

it('removes the logo', function () {
    Storage::fake('local');
    Storage::disk('local')->put('logos/1/existing.png', 'fake image');

    $admin = companyAdmin();
    $admin->company->update(['logo_disk_path' => 'logos/1/existing.png']);

    actingAs($admin);

    Livewire::test(Company::class)
        ->call('removeLogo')
        ->assertHasNoErrors();

    expect($admin->company->fresh()->logo_disk_path)->toBeNull();
    Storage::disk('local')->assertMissing('logos/1/existing.png');
});

it('shows plan usage', function () {
    $plan = Plan::factory()->create(['max_communities' => 5, 'max_units' => 100, 'max_team_members' => 10]);
    $admin = companyAdmin(CompanyModel::factory()->create(['plan_id' => $plan->id]));

    actingAs($admin);

    Livewire::test(Company::class)
        ->assertSee('5')
        ->assertSee('100')
        ->assertSee('10');
});

it('queues a data export request', function () {
    Queue::fake();

    $admin = companyAdmin();

    actingAs($admin);

    Livewire::test(Company::class)->call('requestExport');

    $export = DataExportRequest::sole();

    expect($export)
        ->company_id->toBe($admin->company_id)
        ->requested_by_id->toBe($admin->id);

    Queue::assertPushed(BuildCompanyDataExport::class, fn (BuildCompanyDataExport $job) => $job->dataExportRequestId === $export->id);
});

it('returns 404 for another company\'s export', function () {
    $export = DataExportRequest::factory()->ready()->create();

    actingAs(companyAdmin());

    get(route('data-exports.download', $export))->assertNotFound();
});

it('downloads a ready export', function () {
    Storage::fake('local');
    Storage::disk('local')->put('company-exports/test.zip', 'zip contents');

    $admin = companyAdmin();
    $export = DataExportRequest::factory()->for($admin->company)->ready()->create(['disk_path' => 'company-exports/test.zip']);

    actingAs($admin);

    get(route('data-exports.download', $export))->assertOk();
});

it('serves the company logo publicly, without signing in', function () {
    Storage::fake('local');
    $company = CompanyModel::factory()->create(['logo_disk_path' => 'logos/1/logo.png']);
    Storage::disk('local')->put('logos/1/logo.png', 'fake image bytes');

    get(route('companies.logo', $company))->assertOk();
});

it('returns 404 for a company with no logo', function () {
    $company = CompanyModel::factory()->create();

    get(route('companies.logo', $company))->assertNotFound();
});
