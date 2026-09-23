<?php

use App\Enums\CompanyRole;
use App\Enums\DocumentVisibility;
use App\Enums\ResidencyType;
use App\Livewire\Documents\Index;
use App\Models\Community;
use App\Models\Document;
use App\Models\DocumentFolder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    Storage::fake('local');
});

it('lists documents and folders at the root', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    DocumentFolder::factory()->for($community)->create(['name' => 'Bylaws']);
    Document::factory()->for($community)->withVersion()->create(['title' => 'Welcome Package']);

    actingAs($admin);

    get(route('communities.documents.index', $community))
        ->assertOk()
        ->assertSee('Bylaws')
        ->assertSee('Welcome Package');
});

it('navigates into a folder and shows only its contents', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $folder = DocumentFolder::factory()->for($community)->create(['name' => 'Bylaws']);
    Document::factory()->for($community)->withVersion()->create(['folder_id' => $folder->id, 'title' => 'Inside Folder']);
    Document::factory()->for($community)->withVersion()->create(['title' => 'At Root']);

    actingAs($admin);

    $component = Livewire::test(Index::class, ['community' => $community])->set('folderId', $folder->id);

    expect($component->instance()->documents()->pluck('title')->all())->toBe(['Inside Folder']);
});

it('uploads a document with its first version', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])
        ->call('createDocument')
        ->set('title', 'Welcome Package')
        ->set('visibility', DocumentVisibility::Residents->value)
        ->set('file', UploadedFile::fake()->create('welcome.pdf', 100, 'application/pdf'))
        ->call('saveDocument')
        ->assertHasNoErrors();

    $document = Document::sole();

    expect($document)
        ->community_id->toBe($community->id)
        ->title->toBe('Welcome Package')
        ->visibility->toBe(DocumentVisibility::Residents)
        ->uploaded_by_id->toBe($admin->id);

    expect($document->currentVersion)
        ->version_number->toBe(1)
        ->original_filename->toBe('welcome.pdf');

    Storage::disk('local')->assertExists($document->currentVersion->disk_path);
});

it('requires a file when creating a document but not when only editing details', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $document = Document::factory()->for($community)->withVersion()->create();

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])
        ->set('title', 'No File')
        ->set('visibility', DocumentVisibility::Residents->value)
        ->call('saveDocument')
        ->assertHasErrors(['file' => 'required']);

    Livewire::test(Index::class, ['community' => $community])
        ->call('editDocument', $document->id)
        ->set('title', 'Renamed Only')
        ->call('saveDocument')
        ->assertHasNoErrors();

    expect($document->refresh()->title)->toBe('Renamed Only');
});

it('rejects disallowed file types and oversized files', function (UploadedFile $file) {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])
        ->set('title', 'Bad File')
        ->set('visibility', DocumentVisibility::Residents->value)
        ->set('file', $file)
        ->call('saveDocument')
        ->assertHasErrors('file');
})->with([
    'exe file' => fn () => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload'),
    'too large' => fn () => UploadedFile::fake()->create('big.pdf', 21000, 'application/pdf'),
]);

it('uploads a new version and keeps the old one downloadable', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $document = Document::factory()->for($community)->withVersion()->create();
    $firstVersion = $document->currentVersion;
    Storage::disk('local')->put($firstVersion->disk_path, 'v1 contents');

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])
        ->call('openNewVersion', $document->id)
        ->set('newVersionFile', UploadedFile::fake()->create('v2.pdf', 50, 'application/pdf'))
        ->call('saveNewVersion')
        ->assertHasNoErrors();

    $document->refresh();

    expect($document->versions)->toHaveCount(2)
        ->and($document->currentVersion->version_number)->toBe(2)
        ->and($document->currentVersion->id)->not->toBe($firstVersion->id);

    Storage::disk('local')->assertExists($firstVersion->disk_path);
    Storage::disk('local')->assertExists($document->currentVersion->disk_path);
});

it('creates a nested folder and deletes an empty one', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $parent = DocumentFolder::factory()->for($community)->create();

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])
        ->set('folderId', $parent->id)
        ->call('createFolder')
        ->set('folder_name', 'Minutes')
        ->set('folder_visibility', DocumentVisibility::Board->value)
        ->call('saveFolder')
        ->assertHasNoErrors();

    $child = DocumentFolder::where('name', 'Minutes')->sole();

    expect($child->parent_id)->toBe($parent->id);

    Livewire::test(Index::class, ['community' => $community])->call('deleteFolder', $child->id);

    expect($child->fresh()->trashed())->toBeTrue();
});

it('will not delete a folder that still has contents', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $folder = DocumentFolder::factory()->for($community)->create();
    Document::factory()->for($community)->withVersion()->create(['folder_id' => $folder->id]);

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])->call('deleteFolder', $folder->id);

    expect($folder->fresh()->trashed())->toBeFalse();
});

it('deletes a document', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $document = Document::factory()->for($community)->withVersion()->create();

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])->call('deleteDocument', $document->id);

    expect($document->fresh()->trashed())->toBeTrue();
});

describe('visibility', function () {
    it('shows owners-only documents to owners but not tenants', function () {
        $community = Community::factory()->create();
        $document = Document::factory()->for($community)->withVersion()->create(['visibility' => DocumentVisibility::Owners]);
        $owner = residentOf($community, ['type' => ResidencyType::Owner]);
        $tenant = residentOf($community, ['type' => ResidencyType::Tenant]);

        actingAs($owner->user);
        expect(Livewire::test(Index::class, ['community' => $community])->instance()->documents()->pluck('title')->all())
            ->toBe([$document->title]);

        actingAs($tenant->user);
        expect(Livewire::test(Index::class, ['community' => $community])->instance()->documents())->toBeEmpty();
    });

    it('never shows staff-only or board documents to residents', function (DocumentVisibility $visibility) {
        $community = Community::factory()->create();
        Document::factory()->for($community)->withVersion()->create(['visibility' => $visibility]);
        $owner = residentOf($community, ['type' => ResidencyType::Owner]);

        actingAs($owner->user);

        expect(Livewire::test(Index::class, ['community' => $community])->instance()->documents())->toBeEmpty();
    })->with([DocumentVisibility::Staff, DocumentVisibility::Board]);

    it('shows board documents to board members and property managers but not plain staff', function () {
        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();
        $document = Document::factory()->for($community)->withVersion()->create(['visibility' => DocumentVisibility::Board]);

        $board = teamMember(CompanyRole::BoardMember, $admin->company, [$community]);
        actingAs($board);
        expect(Livewire::test(Index::class, ['community' => $community])->instance()->documents()->pluck('title')->all())
            ->toBe([$document->title]);

        $staff = teamMember(CompanyRole::Staff, $admin->company, [$community]);
        actingAs($staff);
        expect(Livewire::test(Index::class, ['community' => $community])->instance()->documents())->toBeEmpty();
    });

    it('shows residents-level documents to every resident type and the team', function () {
        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();
        $document = Document::factory()->for($community)->withVersion()->create(['visibility' => DocumentVisibility::Residents]);
        $tenant = residentOf($community, ['type' => ResidencyType::Tenant]);

        actingAs($tenant->user);

        expect(Livewire::test(Index::class, ['community' => $community])->instance()->documents()->pluck('title')->all())
            ->toBe([$document->title]);
    });
});

describe('download', function () {
    it('downloads the current version through the authorized route', function () {
        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();
        $document = Document::factory()->for($community)->withVersion()->create();
        Storage::disk('local')->put($document->currentVersion->disk_path, 'file contents');

        actingAs($admin);

        get(route('communities.documents.download', [$community, $document]))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename='.$document->currentVersion->original_filename);
    });

    it('downloads a specific older version', function () {
        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();
        $document = Document::factory()->for($community)->withVersion()->create();
        $firstVersion = $document->currentVersion;
        Storage::disk('local')->put($firstVersion->disk_path, 'v1 contents');

        actingAs($admin);

        get(route('communities.documents.versions.download', [$community, $document, $firstVersion]))->assertOk();
    });

    it('refuses to download a document a resident cannot see', function () {
        $community = Community::factory()->create();
        $document = Document::factory()->for($community)->withVersion()->create(['visibility' => DocumentVisibility::Staff]);
        $resident = residentOf($community);

        actingAs($resident->user);

        get(route('communities.documents.download', [$community, $document]))->assertForbidden();
    });

    it('returns 404 when the document has no file yet', function () {
        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();
        $document = Document::factory()->for($community)->create();

        actingAs($admin);

        get(route('communities.documents.download', [$community, $document]))->assertNotFound();
    });
});

it('cannot browse another company\'s document library', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $foreignDocument = Document::factory()->withVersion()->create();

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])
        ->call('editDocument', $foreignDocument->id)
        ->assertNotFound();
});
