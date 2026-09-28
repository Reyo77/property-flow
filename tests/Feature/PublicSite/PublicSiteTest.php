<?php

use App\Actions\Announcements\PublishAnnouncement;
use App\Enums\AnnouncementAudience;
use App\Enums\DocumentVisibility;
use App\Enums\ResidencyType;
use App\Livewire\PublicSite\Contact;
use App\Models\Announcement;
use App\Models\Community;
use App\Models\ContactMessage;
use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\get;

beforeEach(function () {
    Storage::fake('local');
});

describe('home', function () {
    it('shows the community at its own subdomain', function () {
        $community = Community::factory()->create(['name' => 'Harbour Towers']);

        get($community->publicUrl())
            ->assertOk()
            ->assertSee('Harbour Towers');
    });

    it('returns 404 for an unknown subdomain', function () {
        get('https://no-such-community.property-flow.test/')->assertNotFound();
    });
});

describe('news', function () {
    it('shows published, community-wide, public announcements', function () {
        $community = Community::factory()->create();
        $announcement = Announcement::factory()->for($community)->create([
            'title' => 'Pool reopening',
            'audience_type' => AnnouncementAudience::Community,
            'is_public' => true,
        ]);
        app(PublishAnnouncement::class)->handle($announcement);

        get(route('public.news', $community->slug))
            ->assertOk()
            ->assertSee('Pool reopening');
    });

    it('hides announcements that are not marked public', function () {
        $community = Community::factory()->create();
        $announcement = Announcement::factory()->for($community)->create([
            'title' => 'Internal memo',
            'audience_type' => AnnouncementAudience::Community,
            'is_public' => false,
        ]);
        app(PublishAnnouncement::class)->handle($announcement);

        get(route('public.news', $community->slug))->assertDontSee('Internal memo');
    });

    it('hides public announcements targeted at a residency type, not the whole community', function () {
        $community = Community::factory()->create();
        $announcement = Announcement::factory()->for($community)->create([
            'title' => 'Owners only notice',
            'audience_type' => AnnouncementAudience::ResidencyType,
            'residency_type' => ResidencyType::Owner,
            'is_public' => true,
        ]);
        app(PublishAnnouncement::class)->handle($announcement);

        get(route('public.news', $community->slug))->assertDontSee('Owners only notice');
    });

    it('hides unpublished public announcements', function () {
        $community = Community::factory()->create();
        Announcement::factory()->for($community)->create([
            'title' => 'Draft notice',
            'audience_type' => AnnouncementAudience::Community,
            'is_public' => true,
            'published_at' => null,
        ]);

        get(route('public.news', $community->slug))->assertDontSee('Draft notice');
    });
});

describe('documents', function () {
    it('lists only public-visibility documents', function () {
        $community = Community::factory()->create();
        Document::factory()->for($community)->withVersion()->create(['title' => 'Bylaws', 'visibility' => DocumentVisibility::Public]);
        Document::factory()->for($community)->withVersion()->create(['title' => 'Board minutes', 'visibility' => DocumentVisibility::Board]);

        get(route('public.documents', $community->slug))
            ->assertOk()
            ->assertSee('Bylaws')
            ->assertDontSee('Board minutes');
    });

    it('downloads a public document', function () {
        $community = Community::factory()->create();
        $document = Document::factory()->for($community)->withVersion()->create(['visibility' => DocumentVisibility::Public]);
        Storage::disk('local')->put($document->currentVersion->disk_path, 'file contents');

        get(route('public.documents.download', [$community->slug, $document]))->assertOk();
    });

    it('returns 404 for a non-public document', function () {
        $community = Community::factory()->create();
        $document = Document::factory()->for($community)->withVersion()->create(['visibility' => DocumentVisibility::Board]);

        get(route('public.documents.download', [$community->slug, $document]))->assertNotFound();
    });

    it('returns 404 for a document from another community', function () {
        $community = Community::factory()->create();
        $otherCommunity = Community::factory()->create();
        $document = Document::factory()->for($otherCommunity)->withVersion()->create(['visibility' => DocumentVisibility::Public]);

        get(route('public.documents.download', [$community->slug, $document]))->assertNotFound();
    });
});

describe('contact', function () {
    it('submits a message that lands in the community\'s inbox', function () {
        $community = Community::factory()->create();

        Livewire::test(Contact::class, ['community' => $community])
            ->set('name', 'Jordan Rivera')
            ->set('email', 'jordan@example.com')
            ->set('message', 'Is the pool open this weekend?')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submitted', true);

        $message = ContactMessage::sole();

        expect($message)
            ->company_id->toBe($community->company_id)
            ->community_id->toBe($community->id)
            ->name->toBe('Jordan Rivera')
            ->email->toBe('jordan@example.com')
            ->message->toBe('Is the pool open this weekend?')
            ->read_at->toBeNull();
    });

    it('requires a name, email and message', function () {
        $community = Community::factory()->create();

        Livewire::test(Contact::class, ['community' => $community])
            ->call('submit')
            ->assertHasErrors(['name' => 'required', 'email' => 'required', 'message' => 'required']);

        expect(ContactMessage::count())->toBe(0);
    });

    it('throttles repeated submissions from the same visitor', function () {
        $community = Community::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            Livewire::test(Contact::class, ['community' => $community])
                ->set('name', 'Jordan Rivera')
                ->set('email', 'jordan@example.com')
                ->set('message', "Message number {$i}")
                ->call('submit')
                ->assertHasNoErrors();
        }

        Livewire::test(Contact::class, ['community' => $community])
            ->set('name', 'Jordan Rivera')
            ->set('email', 'jordan@example.com')
            ->set('message', 'One too many')
            ->call('submit')
            ->assertHasErrors('message');

        expect(ContactMessage::count())->toBe(5);
    });
});
