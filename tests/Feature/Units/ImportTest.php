<?php

use App\Livewire\Units\Import;
use App\Models\Community;
use App\Models\Unit;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('forbids company members without a role', function () {
    $member = memberWithoutRole();
    $community = Community::factory()->for($member->company)->create();

    actingAs($member);

    get(route('communities.units.import', $community))->assertForbidden();
});

it('imports an uploaded file and returns to the unit list', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();

    actingAs($admin);

    Livewire::test(Import::class, ['community' => $community])
        ->set('file', UploadedFile::fake()->createWithContent('units.csv', "building,number\nTower A,101\n"))
        ->call('import')
        ->assertHasNoErrors()
        ->assertRedirect(route('communities.units.index', $community));

    expect($community->units()->count())->toBe(1);
});

it('shows the row errors when the file is invalid', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();

    actingAs($admin);

    Livewire::test(Import::class, ['community' => $community])
        ->set('file', UploadedFile::fake()->createWithContent('units.csv', "building,number\nTower A,\n"))
        ->call('import')
        ->assertNoRedirect()
        ->assertSee('Nothing was imported')
        ->assertSee('Row 2');

    expect(Unit::count())->toBe(0);
});

it('rejects files that are not spreadsheets', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();

    actingAs($admin);

    Livewire::test(Import::class, ['community' => $community])
        ->set('file', UploadedFile::fake()->create('units.pdf', 10, 'application/pdf'))
        ->call('import')
        ->assertHasErrors(['file' => 'mimes']);
});

it('offers a CSV template with the expected columns', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();

    actingAs($admin);

    Livewire::test(Import::class, ['community' => $community])
        ->call('downloadTemplate')
        ->assertFileDownloaded('units-template.csv');
});
