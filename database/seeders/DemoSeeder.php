<?php

namespace Database\Seeders;

use App\Actions\Announcements\PublishAnnouncement;
use App\Enums\AnnouncementAudience;
use App\Enums\CompanyRole;
use App\Enums\ContactCategory;
use App\Enums\DocumentVisibility;
use App\Enums\ResidencyType;
use App\Enums\RsvpStatus;
use App\Models\Announcement;
use App\Models\Building;
use App\Models\Community;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Document;
use App\Models\DocumentFolder;
use App\Models\DocumentVersion;
use App\Models\EmergencyContact;
use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\Pet;
use App\Models\Residency;
use App\Models\Resident;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * A realistic company for trying the app. Every demo login uses the password "password":
 * demo@ (company admin), manager@ (Harbour Towers only), board@, staff@ and resident@propertyflow.test.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::factory()->create(['name' => 'Maple Property Management']);

        User::factory()->for($company)->companyAdmin()->create(['name' => 'Demo Admin', 'email' => 'demo@propertyflow.test']);

        $condo = $this->seedCondo($company);
        $hoa = $this->seedHoa($company);

        $this->seedTeam($company, $condo, $hoa);
        $this->seedResidents($company, $condo, $hoa);
        $this->seedCommunication($condo);
    }

    /**
     * A two-tower condo whose unit factors add up to exactly 100%.
     */
    private function seedCondo(Company $company): Community
    {
        $community = Community::factory()->for($company)->create(['name' => 'Harbour Towers']);

        $towers = collect(['North Tower', 'South Tower'])->map(
            fn (string $name) => Building::factory()->for($community)->create(['name' => $name, 'floors' => 15]),
        );

        $unitsPerFloor = 4;
        $floors = 15;
        $unitCount = $towers->count() * $floors * $unitsPerFloor;
        $factor = bcdiv('100', (string) $unitCount, 6);
        $remainder = bcsub('100', bcmul($factor, (string) ($unitCount - 1), 6), 6);
        $created = 0;

        foreach ($towers as $tower) {
            foreach (range(1, $floors) as $floor) {
                foreach (range(1, $unitsPerFloor) as $position) {
                    $created++;

                    Unit::factory()->inBuilding($tower)->create([
                        'number' => sprintf('%d%02d', $floor, $position),
                        'floor' => $floor,
                        'unit_factor' => $created === $unitCount ? $remainder : $factor,
                        'parking' => 'P'.(($created % 3) + 1).'-'.$created,
                    ]);
                }
            }
        }

        return $community;
    }

    /**
     * An HOA of single-family homes, without buildings.
     */
    private function seedHoa(Company $company): Community
    {
        $community = Community::factory()->for($company)->hoa()->create(['name' => 'Maple Grove HOA']);

        foreach (range(1, 40) as $lot) {
            Unit::factory()->for($community)->create([
                'number' => sprintf('%d Maple Grove Lane', $lot * 2),
                'floor' => null,
                'area' => null,
            ]);
        }

        return $community;
    }

    private function seedTeam(Company $company, Community $condo, Community $hoa): void
    {
        $manager = User::factory()->for($company)->withRole(CompanyRole::PropertyManager)
            ->create(['name' => 'Priya Manager', 'email' => 'manager@propertyflow.test']);
        $manager->communities()->attach($condo);

        $board = User::factory()->for($company)->withRole(CompanyRole::BoardMember)
            ->create(['name' => 'Ben Board', 'email' => 'board@propertyflow.test']);
        $board->communities()->attach($condo);

        $staff = User::factory()->for($company)->withRole(CompanyRole::Staff)
            ->create(['name' => 'Sam Concierge', 'email' => 'staff@propertyflow.test']);
        $staff->communities()->attach([$condo->id, $hoa->id]);
    }

    /**
     * Residents in about 80% of units, with a few tenants, past residents, vehicles, pets and emergency contacts.
     */
    private function seedResidents(Company $company, Community $condo, Community $hoa): void
    {
        $units = Unit::query()->whereIn('community_id', [$condo->id, $hoa->id])->orderBy('id')->get();

        foreach ($units as $index => $unit) {
            if ($index % 5 === 4) {
                continue;
            }

            $owner = $index === 0
                ? Resident::factory()->for($company)->withLogin()->create(['name' => 'Rita Resident', 'email' => 'resident@propertyflow.test'])
                : Resident::factory()->for($company)->create();

            Residency::factory()->for($unit)->for($owner)->create([
                'moved_in_on' => now()->subMonths(6 + $index % 60)->toDateString(),
            ]);

            if ($index % 7 === 0) {
                Residency::factory()->for($unit)->tenant()->create(['is_primary' => false]);
            }

            if ($index % 9 === 0) {
                Residency::factory()->for($unit)->movedOut()->create([
                    'is_primary' => false,
                    'moved_in_on' => now()->subYears(5)->toDateString(),
                    'moved_out_on' => now()->subYears(1)->toDateString(),
                ]);
            }

            if ($index % 3 === 0) {
                Vehicle::factory()->for($owner)->create();
            }

            if ($index % 4 === 0) {
                Pet::factory()->for($owner)->create();
                EmergencyContact::factory()->for($owner)->create();
            }
        }
    }

    /**
     * Phone book contacts, events with RSVPs, a document library, and announcements in every state.
     */
    private function seedCommunication(Community $condo): void
    {
        $admin = User::where('email', 'demo@propertyflow.test')->sole();
        $staff = User::where('email', 'staff@propertyflow.test')->sole();
        $northTower = Building::where('community_id', $condo->id)->where('name', 'North Tower')->sole();
        $resident = Resident::where('email', 'resident@propertyflow.test')->sole();

        // Phone book
        Contact::factory()->for($condo)->create(['name' => 'Sam Concierge', 'title' => 'Concierge', 'category' => ContactCategory::Staff, 'phone' => '416-555-0100']);
        Contact::factory()->for($condo)->create(['name' => 'Building Superintendent', 'category' => ContactCategory::Staff, 'phone' => '416-555-0101']);
        Contact::factory()->for($condo)->emergency()->create(['name' => 'Fire / Police / Ambulance', 'phone' => '911']);
        Contact::factory()->for($condo)->staffOnly()->create(['name' => 'Alarm Monitoring Co.', 'category' => ContactCategory::Vendor]);

        // Events
        $bbq = Event::factory()->for($condo)->create([
            'title' => 'Summer Rooftop BBQ',
            'description' => 'Join your neighbours for burgers and drinks on the rooftop terrace.',
            'location' => 'Rooftop terrace',
            'created_by_id' => $admin->id,
        ]);
        EventRsvp::factory()->for($bbq)->create(['user_id' => $staff->id, 'status' => RsvpStatus::Going]);
        if ($resident->user_id !== null) {
            EventRsvp::factory()->for($bbq)->create(['user_id' => $resident->user_id, 'status' => RsvpStatus::Going]);
        }
        Event::factory()->for($condo)->past()->create(['title' => 'Annual General Meeting', 'created_by_id' => $admin->id]);

        // Documents
        $bylawsFolder = DocumentFolder::factory()->for($condo)->create(['name' => 'Bylaws & Rules', 'visibility' => DocumentVisibility::Residents]);
        $boardFolder = DocumentFolder::factory()->for($condo)->create(['name' => 'Board Documents', 'visibility' => DocumentVisibility::Board]);
        $this->seedDocument($condo, $bylawsFolder, 'Condo Declaration.pdf', DocumentVisibility::Residents, $admin);
        $this->seedDocument($condo, $bylawsFolder, 'Rules & Regulations.pdf', DocumentVisibility::Residents, $admin);
        $this->seedDocument($condo, $boardFolder, 'Reserve Fund Study.pdf', DocumentVisibility::Board, $admin);
        $this->seedDocument($condo, null, 'Welcome Package.pdf', DocumentVisibility::Residents, $admin);

        // Announcements: one of each state, so every part of the feature has something to show
        $publishedForEveryone = Announcement::factory()->for($condo)->pinned()->create([
            'title' => 'Elevator maintenance this Thursday',
            'body' => "The North Tower elevator will be out of service Thursday 9am-3pm for scheduled maintenance.\n\nWe apologize for the inconvenience.",
            'audience_type' => AnnouncementAudience::Buildings,
            'created_by_id' => $admin->id,
        ]);
        $publishedForEveryone->buildings()->attach($northTower);
        app(PublishAnnouncement::class)->handle($publishedForEveryone);

        $publishedForOwners = Announcement::factory()->for($condo)->create([
            'title' => 'AGM notice: reserve fund vote',
            'body' => "Owners are invited to the Annual General Meeting to vote on the reserve fund top-up.\n\nSee the Board Documents folder for the reserve fund study.",
            'audience_type' => AnnouncementAudience::ResidencyType,
            'residency_type' => ResidencyType::Owner,
            'created_by_id' => $admin->id,
        ]);
        app(PublishAnnouncement::class)->handle($publishedForOwners);

        Announcement::factory()->for($condo)->create([
            'title' => 'Holiday decorating contest',
            'body' => 'Sign up at the front desk to enter this year\'s holiday decorating contest.',
            'audience_type' => AnnouncementAudience::Community,
            'publish_at' => now()->addDays(3),
            'created_by_id' => $admin->id,
        ]);

        Announcement::factory()->for($condo)->draft()->create([
            'title' => 'Pool opening date (draft)',
            'body' => 'Draft: confirm the pool opening date with the maintenance vendor before publishing.',
            'audience_type' => AnnouncementAudience::Community,
            'created_by_id' => $admin->id,
        ]);
    }

    private function seedDocument(Community $community, ?DocumentFolder $folder, string $filename, DocumentVisibility $visibility, User $uploadedBy): void
    {
        $document = Document::factory()->for($community)->create([
            'folder_id' => $folder?->id,
            'title' => pathinfo($filename, PATHINFO_FILENAME),
            'visibility' => $visibility,
            'uploaded_by_id' => $uploadedBy->id,
        ]);

        $diskPath = "documents/{$community->company_id}/{$document->id}/1-".fake()->uuid().'.pdf';
        Storage::disk('local')->put($diskPath, "%PDF-1.4\n% Placeholder demo document: {$filename}\n");

        $version = DocumentVersion::factory()->for($document)->create([
            'uploaded_by_id' => $uploadedBy->id,
            'version_number' => 1,
            'disk_path' => $diskPath,
            'original_filename' => $filename,
            'mime_type' => 'application/pdf',
            'size_bytes' => Storage::disk('local')->size($diskPath) ?: 0,
        ]);

        $document->forceFill(['current_version_id' => $version->id])->save();
    }
}
