<?php

use App\Livewire\Buildings\Index as BuildingsIndex;
use App\Livewire\CommunitySwitcher;
use App\Livewire\Units\Index as UnitsIndex;
use App\Models\AccessKey;
use App\Models\AccessKeySignout;
use App\Models\Account;
use App\Models\Amenity;
use App\Models\AmenityBlackout;
use App\Models\AmenityBooking;
use App\Models\Announcement;
use App\Models\Asset;
use App\Models\Ballot;
use App\Models\BallotAnswer;
use App\Models\BallotOption;
use App\Models\BallotProxy;
use App\Models\BallotQuestion;
use App\Models\BallotVote;
use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\BudgetLine;
use App\Models\Building;
use App\Models\ChargeType;
use App\Models\Community;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Document;
use App\Models\DocumentFolder;
use App\Models\EmergencyContact;
use App\Models\EntryAuthorization;
use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\FiscalYear;
use App\Models\GuestPass;
use App\Models\IncidentReport;
use App\Models\Invitation;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\JournalEntry;
use App\Models\LateFeeRule;
use App\Models\LedgerEntry;
use App\Models\MaintenanceSchedule;
use App\Models\Meeting;
use App\Models\MeetingAgendaItem;
use App\Models\MeetingAttendance;
use App\Models\Package;
use App\Models\ParkingPermit;
use App\Models\PatrolCheckpoint;
use App\Models\PatrolRoute;
use App\Models\PatrolScan;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Pet;
use App\Models\RecurringCharge;
use App\Models\Residency;
use App\Models\Resident;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestComment;
use App\Models\ShiftLogEntry;
use App\Models\Task;
use App\Models\Unit;
use App\Models\Vehicle;
use App\Models\Vendor;
use App\Models\VendorBill;
use App\Models\Visitor;
use App\Models\WorkOrder;
use App\Support\Tenancy\CurrentCompany;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

dataset('tenant models', [
    'communities' => fn () => Community::factory(),
    'buildings' => fn () => Building::factory(),
    'units' => fn () => Unit::factory(),
    'residents' => fn () => Resident::factory(),
    'residencies' => fn () => Residency::factory(),
    'vehicles' => fn () => Vehicle::factory(),
    'pets' => fn () => Pet::factory(),
    'emergency contacts' => fn () => EmergencyContact::factory(),
    'invitations' => fn () => Invitation::factory(),
    'contacts' => fn () => Contact::factory(),
    'events' => fn () => Event::factory(),
    'event rsvps' => fn () => EventRsvp::factory(),
    'document folders' => fn () => DocumentFolder::factory(),
    'documents' => fn () => Document::factory(),
    'announcements' => fn () => Announcement::factory(),
    'vendors' => fn () => Vendor::factory(),
    'service requests' => fn () => ServiceRequest::factory(),
    'service request comments' => fn () => ServiceRequestComment::factory(),
    'work orders' => fn () => WorkOrder::factory(),
    'assets' => fn () => Asset::factory(),
    'maintenance schedules' => fn () => MaintenanceSchedule::factory(),
    'tasks' => fn () => Task::factory(),
    'amenities' => fn () => Amenity::factory(),
    'amenity blackouts' => fn () => AmenityBlackout::factory(),
    'amenity bookings' => fn () => AmenityBooking::factory(),
    'packages' => fn () => Package::factory(),
    'visitors' => fn () => Visitor::factory(),
    'guest passes' => fn () => GuestPass::factory(),
    'parking permits' => fn () => ParkingPermit::factory(),
    'incident reports' => fn () => IncidentReport::factory(),
    'access keys' => fn () => AccessKey::factory(),
    'access key signouts' => fn () => AccessKeySignout::factory(),
    'entry authorizations' => fn () => EntryAuthorization::factory(),
    'patrol routes' => fn () => PatrolRoute::factory(),
    'patrol checkpoints' => fn () => PatrolCheckpoint::factory(),
    'patrol scans' => fn () => PatrolScan::factory(),
    'shift log entries' => fn () => ShiftLogEntry::factory(),
    'accounts' => fn () => Account::factory(),
    'fiscal years' => fn () => FiscalYear::factory(),
    'journal entries' => fn () => JournalEntry::factory(),
    'ledger entries' => fn () => LedgerEntry::factory(),
    'charge types' => fn () => ChargeType::factory(),
    'invoices' => fn () => Invoice::factory(),
    'invoice lines' => fn () => InvoiceLine::factory(),
    'payments' => fn () => Payment::factory(),
    'payment allocations' => fn () => PaymentAllocation::factory(),
    'recurring charges' => fn () => RecurringCharge::factory(),
    'late fee rules' => fn () => LateFeeRule::factory(),
    'vendor bills' => fn () => VendorBill::factory(),
    'budget lines' => fn () => BudgetLine::factory(),
    'bank statements' => fn () => BankStatement::factory(),
    'bank statement lines' => fn () => BankStatementLine::factory(),
    'ballots' => fn () => Ballot::factory(),
    'ballot questions' => fn () => BallotQuestion::factory(),
    'ballot options' => fn () => BallotOption::factory(),
    'ballot proxies' => fn () => BallotProxy::factory(),
    'ballot votes' => fn () => BallotVote::factory(),
    'ballot answers' => fn () => BallotAnswer::factory(),
    'meetings' => fn () => Meeting::factory(),
    'meeting agenda items' => fn () => MeetingAgendaItem::factory(),
    'meeting attendances' => fn () => MeetingAttendance::factory(),
]);

it('hides another company\'s records from queries', function ($factory) {
    $ownRecord = $factory->create();
    $foreignRecord = $factory->create();

    actingAs(companyAdmin(Company::findOrFail($ownRecord->company_id)));

    $model = $ownRecord::class;

    expect($model::query()->pluck('id')->all())->toBe([$ownRecord->id])
        ->and($model::query()->find($foreignRecord->id))->toBeNull();
})->with('tenant models');

it('stamps new records with the signed-in user\'s company', function () {
    $admin = companyAdmin();

    actingAs($admin);

    $community = Community::factory()->make(['company_id' => null]);
    $community->save();

    expect($community->company_id)->toBe($admin->company_id);
});

it('scopes queries to an explicitly set company when nobody is signed in', function () {
    $community = Community::factory()->create();
    Community::factory()->create();

    app(CurrentCompany::class)->set($community->company_id);

    expect(Community::query()->pluck('id')->all())->toBe([$community->id]);
});

it('returns 404 for pages of another company\'s community', function (string $routeName) {
    $foreignCommunity = Community::factory()->create();

    actingAs(companyAdmin());

    get(route($routeName, $foreignCommunity))->assertNotFound();
})->with([
    'overview' => 'communities.show',
    'edit' => 'communities.edit',
    'buildings' => 'communities.buildings.index',
    'units' => 'communities.units.index',
    'unit import' => 'communities.units.import',
    'finance overview' => 'communities.finance.overview',
    'invoices' => 'communities.finance.invoices',
    'payments' => 'communities.finance.payments',
    'accounts & charges' => 'communities.finance.setup',
    'billing' => 'communities.finance.billing',
    'vendor bills' => 'communities.finance.bills',
    'budget' => 'communities.finance.budget',
    'reports' => 'communities.finance.reports',
    'reconciliation' => 'communities.finance.reconciliation',
    'ballots' => 'communities.ballots.index',
    'new ballot' => 'communities.ballots.create',
    'meetings' => 'communities.meetings.index',
]);

it('refuses to edit a unit of another company', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $foreignUnit = Unit::factory()->create();

    actingAs($admin);

    Livewire::test(UnitsIndex::class, ['community' => $community])
        ->call('edit', $foreignUnit->id)
        ->assertNotFound()
        ->assertSet('editingUnitId', null);
});

it('refuses to edit a unit of another community in the same company', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $otherCommunityUnit = Unit::factory()->for(Community::factory()->for($admin->company))->create();

    actingAs($admin);

    Livewire::test(UnitsIndex::class, ['community' => $community])
        ->call('edit', $otherCommunityUnit->id)
        ->assertNotFound()
        ->assertSet('editingUnitId', null);
});

it('refuses to delete a building of another company', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $foreignBuilding = Building::factory()->create();

    actingAs($admin);

    Livewire::test(BuildingsIndex::class, ['community' => $community])
        ->call('delete', $foreignBuilding->id)
        ->assertNotFound();

    expect(Building::withoutGlobalScopes()->find($foreignBuilding->id)?->trashed())->toBeFalse();
});

it('refuses to switch to another company\'s community', function () {
    $foreignCommunity = Community::factory()->create();

    actingAs(companyAdmin());

    Livewire::test(CommunitySwitcher::class)
        ->call('switchTo', $foreignCommunity->id)
        ->assertNotFound()
        ->assertNoRedirect();

    expect(session('current_community_id'))->toBeNull();
});
