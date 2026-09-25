<?php

namespace Database\Seeders;

use App\Actions\Amenities\CreateAmenityBooking;
use App\Actions\Announcements\PublishAnnouncement;
use App\Actions\ArchitecturalRequests\DecideArchitecturalRequest;
use App\Actions\ArchitecturalRequests\SubmitArchitecturalRequest;
use App\Actions\Finance\AssessLateFees;
use App\Actions\Finance\CompleteReconciliation;
use App\Actions\Finance\DecideVendorBill;
use App\Actions\Finance\ImportBankStatement;
use App\Actions\Finance\PayVendorBill;
use App\Actions\Finance\RecordBankLine;
use App\Actions\Finance\RecordPayment;
use App\Actions\Finance\ReversePayment;
use App\Actions\Finance\RunBilling;
use App\Actions\Finance\SubmitVendorBill;
use App\Actions\FrontDesk\CreateIncidentReport;
use App\Actions\FrontDesk\IssueParkingPermit;
use App\Actions\FrontDesk\LogPackage;
use App\Actions\FrontDesk\ScanPatrolCheckpoint;
use App\Actions\Governance\CastVote;
use App\Actions\Governance\CloseBallot;
use App\Actions\Governance\CloseMeeting;
use App\Actions\Governance\GrantProxy;
use App\Actions\Governance\PublishBallot;
use App\Actions\Governance\PublishMinutes;
use App\Actions\Governance\RecordAttendance;
use App\Actions\Governance\SaveBallot;
use App\Actions\Governance\SaveMeeting;
use App\Actions\Maintenance\GenerateDueMaintenanceWorkOrders;
use App\Actions\Violations\EscalateViolation;
use App\Actions\Violations\ReportViolation;
use App\Enums\AnnouncementAudience;
use App\Enums\ArchitecturalRequestStatus;
use App\Enums\AssetCategory;
use App\Enums\Assignee;
use App\Enums\AttendanceMode;
use App\Enums\CompanyRole;
use App\Enums\ContactCategory;
use App\Enums\DocumentVisibility;
use App\Enums\IncidentSeverity;
use App\Enums\PackageStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentReversalReason;
use App\Enums\ResidencyType;
use App\Enums\RsvpStatus;
use App\Enums\ServiceRequestCategory;
use App\Enums\ServiceRequestPriority;
use App\Enums\ServiceRequestStatus;
use App\Enums\SystemAccount;
use App\Models\AccessKey;
use App\Models\AccessKeySignout;
use App\Models\Amenity;
use App\Models\Announcement;
use App\Models\Asset;
use App\Models\Ballot;
use App\Models\BudgetLine;
use App\Models\Building;
use App\Models\ChargeType;
use App\Models\Community;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Document;
use App\Models\DocumentFolder;
use App\Models\DocumentVersion;
use App\Models\EmergencyContact;
use App\Models\EntryAuthorization;
use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\GuestPass;
use App\Models\LateFeeRule;
use App\Models\LedgerEntry;
use App\Models\MaintenanceSchedule;
use App\Models\PatrolCheckpoint;
use App\Models\PatrolRoute;
use App\Models\Pet;
use App\Models\RecurringCharge;
use App\Models\Residency;
use App\Models\Resident;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestComment;
use App\Models\ShiftLogEntry;
use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Vendor;
use App\Models\Violation;
use App\Models\ViolationRule;
use App\Models\Visitor;
use App\Models\WorkOrder;
use App\Support\Finance\ChartOfAccounts;
use App\Support\Finance\FiscalYears;
use App\Support\Finance\Money;
use App\Support\Governance\VotingRoll;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
use LogicException;

/**
 * A realistic company for trying the app. Every demo login uses the password "password":
 * demo@ (company admin), manager@ (Harbour Towers only), board@, staff@, resident@ and
 * vendor@propertyflow.test (a vendor with a work order to view and update).
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
        $this->seedMaintenance($condo);
        $this->seedAmenities($condo);
        $this->seedFrontDesk($condo);
        $this->seedFinance($condo);
        $this->seedGovernance($condo);
        $this->seedViolationsAndRenovations($condo);
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

    /**
     * Vendors, service requests in every stage of the workflow, tasks, and an asset with an
     * overdue maintenance schedule ready to demonstrate the scheduler.
     */
    private function seedMaintenance(Community $condo): void
    {
        $admin = User::where('email', 'demo@propertyflow.test')->sole();
        $manager = User::where('email', 'manager@propertyflow.test')->sole();
        $staff = User::where('email', 'staff@propertyflow.test')->sole();
        $resident = Resident::where('email', 'resident@propertyflow.test')->sole();
        $residentUnit = Residency::where('resident_id', $resident->id)->active()->firstOrFail()->unit;

        $plumber = Vendor::factory()->for($condo->company)->create(['name' => 'Ace Plumbing Co.', 'trade' => 'Plumbing', 'phone' => '416-555-0200']);
        $elevatorVendor = Vendor::factory()->for($condo->company)->withLogin()->create(['name' => 'Reliable Elevator Services', 'trade' => 'Elevator maintenance', 'email' => 'vendor@propertyflow.test']);

        // Open, unassigned request straight from a resident.
        ServiceRequest::factory()->for($condo)->create([
            'unit_id' => $residentUnit->id,
            'reported_by_resident_id' => $resident->id,
            'reported_by_user_id' => $resident->user_id,
            'title' => 'Kitchen faucet is leaking',
            'description' => "The kitchen faucet drips constantly, even when fully closed.\n\nStarted about a week ago.",
            'category' => ServiceRequestCategory::Plumbing,
            'priority' => ServiceRequestPriority::Medium,
        ]);

        // Assigned to a vendor, with a work order and both an internal and a resident-visible comment.
        $assigned = ServiceRequest::factory()->for($condo)->assigned()->create([
            'unit_id' => $residentUnit->id,
            'reported_by_resident_id' => $resident->id,
            'reported_by_user_id' => $resident->user_id,
            'title' => 'No hot water',
            'description' => 'The water heater for our unit is not producing hot water.',
            'category' => ServiceRequestCategory::Plumbing,
            'priority' => ServiceRequestPriority::Urgent,
        ]);
        ServiceRequestComment::factory()->for($assigned)->create(['author_id' => $manager->id, 'body' => 'Ace Plumbing is booked for tomorrow morning.', 'visible_to_resident' => true]);
        ServiceRequestComment::factory()->for($assigned)->internal()->create(['author_id' => $manager->id, 'body' => 'Vendor quoted $250 if the tank needs replacing.']);
        WorkOrder::factory()->for($condo)->assignedToVendor($plumber->id)->create([
            'service_request_id' => $assigned->id,
            'title' => 'Fix water heater',
            'due_on' => now()->addDay(),
            'created_by_id' => $manager->id,
        ]);

        // A resolved, closed request with its full history for reference.
        $closed = ServiceRequest::factory()->for($condo)->create([
            'title' => 'Hallway light out on 3rd floor',
            'description' => 'The hallway light near unit 301 has been flickering and is now out.',
            'category' => ServiceRequestCategory::Electrical,
            'priority' => ServiceRequestPriority::Low,
            'status' => ServiceRequestStatus::Closed,
        ]);
        WorkOrder::factory()->for($condo)->assignedToUser($staff->id)->completed()->create([
            'service_request_id' => $closed->id,
            'title' => 'Replace hallway bulb',
            'completion_notes' => 'Replaced with a new LED bulb.',
            'created_by_id' => $manager->id,
        ]);

        // Tasks: a mix of open, overdue and done.
        Task::factory()->for($condo)->create(['title' => 'Order more salt for winter', 'assigned_to_id' => $staff->id, 'due_on' => now()->addWeek(), 'created_by_id' => $manager->id]);
        Task::factory()->for($condo)->overdue()->create(['title' => 'Follow up with landscaping vendor', 'assigned_to_id' => $manager->id, 'created_by_id' => $admin->id]);
        Task::factory()->for($condo)->done()->create(['title' => 'Post the AGM notice', 'assigned_to_id' => $manager->id, 'created_by_id' => $admin->id]);

        // Assets: one with an overdue schedule (ready for "Generate due now" or the daily scheduler), one on track.
        $elevator = Asset::factory()->for($condo)->create(['name' => 'North Tower Elevator', 'category' => AssetCategory::Elevator, 'location' => 'North Tower']);
        MaintenanceSchedule::factory()->for($elevator)->overdue()->create([
            'title' => 'Monthly elevator inspection',
            'interval_days' => 30,
            'assignee_type' => Assignee::Vendor,
            'assigned_vendor_id' => $elevatorVendor->id,
        ]);

        $generator = Asset::factory()->for($condo)->create(['name' => 'Backup Generator', 'category' => AssetCategory::Generator, 'location' => 'Basement']);
        MaintenanceSchedule::factory()->for($generator)->create([
            'title' => 'Quarterly load test',
            'interval_days' => 90,
            'assignee_type' => Assignee::Staff,
            'assigned_user_id' => $staff->id,
        ]);

        // Materialize the overdue elevator schedule into a real work order, so the vendor
        // login has something to see immediately instead of waiting for the daily scheduler.
        app(GenerateDueMaintenanceWorkOrders::class)
            ->handle($elevator->maintenanceSchedules()->dueToGenerate()->get());
    }

    /**
     * Two amenities demonstrating both booking paths: one auto-confirms, one needs a manager's
     * decision. Both bookings go through the real action, so they're subject to every rule.
     */
    private function seedAmenities(Community $condo): void
    {
        $resident = Resident::where('email', 'resident@propertyflow.test')->sole();
        $residentUser = User::where('email', 'resident@propertyflow.test')->sole();
        $residentUnit = Residency::where('resident_id', $resident->id)->active()->firstOrFail()->unit;
        $createBooking = app(CreateAmenityBooking::class);

        $partyRoom = Amenity::factory()->for($condo)->create([
            'name' => 'Party Room',
            'description' => 'Seats up to 20. Kitchenette included.',
            'opens_at_minutes' => 10 * 60,
            'closes_at_minutes' => 22 * 60,
            'slot_minutes' => 120,
            'capacity' => 1,
            'cancellation_notice_hours' => 48,
            'fee_cents' => 5000,
            'deposit_cents' => 20000,
            'terms' => "No smoking. Remove decorations and take out trash before leaving.\nDamage will be deducted from the deposit.",
        ]);
        $partyRoomSlot = $partyRoom->availableSlots($partyRoom->minBookableDate()->addDays(4))[0]['starts_at'];
        $createBooking->handle($partyRoom, $residentUser, $partyRoomSlot, $residentUnit->id, 'Birthday party, around 15 guests.', true);

        $guestSuite = Amenity::factory()->for($condo)->needsApproval()->create([
            'name' => 'Guest Suite',
            'description' => 'A private room for out-of-town visitors.',
            'opens_at_minutes' => 0,
            'closes_at_minutes' => 23 * 60 + 59,
            'slot_minutes' => 23 * 60 + 59,
            'capacity' => 1,
            'max_bookings_per_unit' => 2,
            'max_bookings_period_days' => 90,
            'fee_cents' => 7500,
        ]);
        $guestSuiteSlot = $guestSuite->availableSlots($guestSuite->minBookableDate()->addDays(10))[0]['starts_at'];
        $createBooking->handle($guestSuite, $residentUser, $guestSuiteSlot, $residentUnit->id, 'My parents are visiting next month.', false);
    }

    /**
     * Three months of common expense fees, billed by the real billing run and split by unit factor,
     * with realistic payment behaviour: most units pay in full, some pay late or partially, one
     * prepays, one cheque bounces, and late fees land on what is still overdue. The demo resident
     * is paid up except for this month.
     */
    private function seedFinance(Community $condo): void
    {
        $chartOfAccounts = app(ChartOfAccounts::class);
        $runBilling = app(RunBilling::class);
        $recordPayment = app(RecordPayment::class);
        $manager = User::where('email', 'manager@propertyflow.test')->sole();
        $residentUnitId = Residency::where('resident_id', Resident::where('email', 'resident@propertyflow.test')->sole()->id)->active()->firstOrFail()->unit_id;

        $assessments = $chartOfAccounts->account($condo, SystemAccount::Assessments);
        $monthlyFees = ChargeType::factory()->for($condo)->create(['name' => 'Common expense fees', 'account_id' => $assessments->id, 'default_amount_cents' => null]);
        ChargeType::factory()->for($condo)->create(['name' => 'Move-in fee', 'account_id' => $chartOfAccounts->account($condo, SystemAccount::OtherIncome)->id, 'default_amount_cents' => 25000]);

        $units = Unit::query()->where('community_id', $condo->id)->with('community')->orderBy('id')->get()->keyBy('id');
        $shares = Money::of(2_700_000, $condo->currency)->allocate($units->map(fn (Unit $unit) => (string) $unit->unit_factor)->all());
        $thisMonth = CarbonImmutable::now($condo->timezone)->startOfMonth();

        RecurringCharge::factory()->for($condo)->byUnitFactor(2_700_000)->create([
            'charge_type_id' => $monthlyFees->id,
            'description' => 'Common expense fees',
            'starts_on' => $thisMonth->subMonths(2)->toDateString(),
        ]);
        $parking = ChargeType::factory()->for($condo)->create(['name' => 'Parking spot', 'account_id' => $chartOfAccounts->account($condo, SystemAccount::OtherIncome)->id, 'default_amount_cents' => 7500]);
        foreach ($units->values()->slice(20, 2) as $unit) {
            RecurringCharge::factory()->for($condo)->create([
                'charge_type_id' => $parking->id,
                'unit_id' => $unit->id,
                'description' => "Parking {$unit->parking}",
                'amount_cents' => 7500,
                'starts_on' => $thisMonth->toDateString(),
            ]);
        }

        foreach ([2, 1, 0] as $monthsAgo) {
            $month = $thisMonth->subMonths($monthsAgo);

            $runBilling->handle($condo, $month, $manager);

            if ($monthsAgo === 0) {
                continue;
            }

            foreach ($units->values() as $index => $unit) {
                $share = $shares[$unit->id];
                $amount = match (true) {
                    $index % 11 === 3 => null,
                    $index % 13 === 5 => $share->allocate([1, 1])[0],
                    $index === 7 && $monthsAgo === 1 => $share->times(3),
                    default => $share,
                };

                if ($amount === null) {
                    continue;
                }

                $recordPayment->handle(
                    $unit,
                    $index % 2 === 0 ? PaymentMethod::BankTransfer : PaymentMethod::Cheque,
                    $amount,
                    $month->addDays($index % 9),
                    $index % 2 === 0 ? 'EFT-'.(10000 + $index) : 'CHQ '.(300 + $index),
                    recordedBy: $manager,
                );
            }
        }

        foreach ($units->values() as $index => $unit) {
            if ($unit->id !== $residentUnitId && $index % 8 !== 1 && $index % 11 !== 3) {
                $recordPayment->handle($unit, PaymentMethod::BankTransfer, $shares[$unit->id], $thisMonth->addDays(min(3, $thisMonth->diffInDays(CarbonImmutable::now($condo->timezone)))), 'EFT-'.(20000 + $index), recordedBy: $manager);
            }
        }

        $bouncingUnit = $units->values()->get(9) ?? throw new LogicException('The demo condo needs at least ten units.');
        $bounced = $recordPayment->handle($bouncingUnit, PaymentMethod::Cheque, $shares[$bouncingUnit->id], $thisMonth, 'CHQ 999', recordedBy: $manager);
        app(ReversePayment::class)->handle($bounced, PaymentReversalReason::Nsf, $thisMonth->addDays(2), $manager);

        LateFeeRule::factory()->for($condo)->create(['grace_days' => 10, 'flat_cents' => 2500, 'created_at' => $thisMonth->subMonths(3)]);
        app(AssessLateFees::class)->handle($condo, CarbonImmutable::now($condo->timezone));

        $this->seedVendorBills($condo, $manager);
        $this->seedBudget($condo);
        $this->seedReconciledMonth($condo, $manager);
    }

    /**
     * This fiscal year's budget: the fees actually charged, and expenses a little over and under.
     */
    private function seedBudget(Community $condo): void
    {
        $year = app(FiscalYears::class)->covering($condo, CarbonImmutable::now($condo->timezone));
        $budgets = ['4000' => 32_400_000, '4100' => 60_000, '4900' => 120_000, '5000' => 4_800_000, '5100' => 7_200_000, '5200' => 3_600_000, '5300' => 5_400_000, '5400' => 2_400_000, '5600' => 6_000_000, '5900' => 600_000];

        foreach ($budgets as $code => $cents) {
            BudgetLine::factory()->for($condo)->create([
                'fiscal_year_id' => $year->id,
                'account_id' => $condo->accounts()->where('code', $code)->sole()->id,
                'annual_cents' => $cents,
            ]);
        }
    }

    /**
     * Last month closed properly: the bank's statement imported and matched against the books,
     * the bank's service charge booked, and the reconciliation signed off. Two cheques written
     * at month end are still outstanding, as they would be in real life.
     */
    private function seedReconciledMonth(Community $condo, User $manager): void
    {
        $lastMonth = CarbonImmutable::now($condo->timezone)->subMonthNoOverflow()->startOfMonth();
        $cash = app(ChartOfAccounts::class)->account($condo, SystemAccount::Cash);
        $entries = LedgerEntry::query()->withoutGlobalScopes()->where('account_id', $cash->id)
            ->whereBetween('posted_on', [$lastMonth->toDateString(), $lastMonth->endOfMonth()->toDateString()])
            ->orderBy('posted_on')->orderBy('id')->get();

        $outstanding = $entries->filter(fn (LedgerEntry $entry) => $entry->netCents() > 0)->take(-2)->pluck('id')->all();
        $bankCharge = -1_295;
        $rows = ['date,description,reference,amount'];
        $clearedTotal = 0;

        foreach ($entries as $entry) {
            if (in_array($entry->id, $outstanding, true)) {
                continue;
            }

            $clearsOn = $entry->posted_on->addDays(1)->min($lastMonth->endOfMonth());
            $rows[] = $clearsOn->toDateString().','.($entry->netCents() > 0 ? 'DEPOSIT' : 'CHEQUE PAID').','.$entry->memo.','.Money::of($entry->netCents())->toDecimal();
            $clearedTotal += $entry->netCents();
        }

        $rows[] = $lastMonth->endOfMonth()->toDateString().',MONTHLY SERVICE CHARGE,,'.Money::of($bankCharge)->toDecimal();
        $openingBalance = (int) LedgerEntry::query()->withoutGlobalScopes()->where('account_id', $cash->id)
            ->whereDate('posted_on', '<', $lastMonth->toDateString())
            ->toBase()->selectRaw('COALESCE(SUM(CAST(debit_cents AS SIGNED) - CAST(credit_cents AS SIGNED)), 0) as balance')->value('balance');

        $filename = 'harbour-towers-'.$lastMonth->format('Y-m').'.csv';
        $disk = Storage::disk('local');
        $disk->put("demo/{$filename}", implode("\n", $rows)."\n");
        $path = $disk->path("demo/{$filename}");

        $statement = app(ImportBankStatement::class)->handle(
            $condo,
            new UploadedFile($path, $filename, 'text/csv', null, true),
            $lastMonth,
            $lastMonth->endOfMonth()->startOfDay(),
            Money::of($openingBalance + $clearedTotal + $bankCharge, $condo->currency),
            $manager,
        );
        $disk->delete("demo/{$filename}");

        app(RecordBankLine::class)->handle(
            $statement->lines()->where('description', 'MONTHLY SERVICE CHARGE')->sole(),
            $condo->accounts()->where('code', '5900')->sole(),
            $manager,
        );
        app(CompleteReconciliation::class)->handle($statement, $manager);
    }

    /**
     * Last year's AGM (held, minutes published, its ballot closed with results), this year's AGM
     * coming up, and an open budget vote linked to it with some owners already voted — one of
     * them by proxy. The demo resident owns a unit, so they can vote too.
     */
    private function seedGovernance(Community $condo): void
    {
        $board = User::where('email', 'board@propertyflow.test')->sole();
        $saveMeeting = app(SaveMeeting::class);
        $saveBallot = app(SaveBallot::class);
        $castVote = app(CastVote::class);
        $roll = app(VotingRoll::class);
        $owners = $roll->eligibleUnits($condo)->values();
        $ownerOf = fn (Unit $unit) => Residency::query()->where('unit_id', $unit->id)->where('type', ResidencyType::Owner)->active()->firstOrFail()->resident;
        // Owners need a login to vote; give the first sixty owner households one.
        foreach ($owners->take(60) as $unit) {
            $owner = $ownerOf($unit);

            if ($owner->user_id === null && $owner->email !== null) {
                $user = User::factory()->for($condo->company)->create(['name' => $owner->name, 'email' => $owner->email]);
                $owner->forceFill(['user_id' => $user->id])->save();
            }
        }

        $votingOwners = $owners->filter(fn (Unit $unit) => $ownerOf($unit)->user !== null)->values();
        $now = CarbonImmutable::now($condo->timezone);
        $agenda = ['Call to order and quorum', "Approval of last year's minutes", 'Financial statements', 'Election of directors', 'New business', 'Adjournment'];

        // Last year's AGM, run from the past: open the ballot, let owners vote, then close.
        $lastYear = $saveMeeting->handle($condo, null, [
            'title' => 'Annual General Meeting '.($now->year - 1), 'kind' => 'agm',
            'starts_at' => $now->subYear()->setTime(19, 0)->utc()->toDateTimeString(), 'location' => 'Party Room',
            'description' => null, 'weighting' => 'unit_factor', 'quorum_percent' => 25,
        ], $agenda, $board);

        $reserve = $saveBallot->handle($condo, null, [
            'meeting_id' => $lastYear->id, 'title' => 'Reserve fund top-up', 'description' => 'Special assessment of $400 per unit factor point to restore the reserve fund.',
            'weighting' => 'unit_factor', 'quorum_percent' => 25,
            'opens_at' => $now->subYear()->subWeeks(2)->utc()->toDateTimeString(), 'closes_at' => $now->subYear()->setTime(21, 0)->utc()->toDateTimeString(),
        ], [['title' => 'Do you approve the reserve fund top-up?', 'options' => ['Yes', 'No', 'Abstain']]], $board);
        $reserve->forceFill(['published_at' => $now->subYear()->subWeeks(3)])->save();

        $this->travelTo($now->subYear()->subWeek(), function () use ($reserve, $votingOwners, $ownerOf, $castVote): void {
            foreach ($votingOwners->take(60) as $index => $unit) {
                $label = match (true) {
                    $index % 7 === 0 => 'No', $index % 11 === 0 => 'Abstain', default => 'Yes'
                };
                $owner = $ownerOf($unit)->user ?? throw new LogicException('Voting owner has no login.');
                $castVote->handle($reserve, $unit, $owner, $this->answers($reserve, [$label]));
            }
        });

        foreach ($owners->take(55) as $index => $unit) {
            app(RecordAttendance::class)->handle($lastYear, $unit, $index % 5 === 0 ? AttendanceMode::Proxy : AttendanceMode::InPerson, null, $board);
        }

        app(CloseBallot::class)->handle($reserve, $board);
        app(PublishMinutes::class)->handle($lastYear, "Meeting called to order at 7:02pm; quorum confirmed (55 units represented).\n\n1. Minutes of the previous AGM approved.\n2. Financial statements presented by the treasurer and accepted.\n3. Reserve fund top-up approved by ballot (see results).\n4. Directors elected by acclamation.\n\nAdjourned at 8:40pm.", true, $board);
        app(CloseMeeting::class)->handle($lastYear, $board);

        // This year's AGM, three weeks out, with the budget ballot already open.
        $agm = $saveMeeting->handle($condo, null, [
            'title' => 'Annual General Meeting '.$now->year, 'kind' => 'agm',
            'starts_at' => $now->addWeeks(3)->setTime(19, 0)->utc()->toDateTimeString(), 'location' => 'Party Room',
            'description' => 'All owners are invited. If you cannot attend, appoint a proxy on the ballot page.',
            'weighting' => 'unit_factor', 'quorum_percent' => 25,
        ], $agenda, $board);

        $budget = $saveBallot->handle($condo, null, [
            'meeting_id' => $agm->id, 'title' => 'Approve the '.($now->year + 1).' operating budget', 'description' => 'The proposed budget keeps common expense fees flat and increases the reserve contribution by 3%.',
            'weighting' => 'unit_factor', 'quorum_percent' => 25,
            'opens_at' => $now->subDays(3)->utc()->toDateTimeString(), 'closes_at' => $now->addWeeks(3)->setTime(21, 0)->utc()->toDateTimeString(),
        ], [
            ['title' => 'Do you approve the proposed operating budget?', 'options' => ['Yes', 'No', 'Abstain']],
            ['title' => 'Elect Jordan Patel to the board?', 'options' => ['For', 'Against']],
        ], $board);
        app(PublishBallot::class)->handle($budget, $board);

        $resident = Resident::where('email', 'resident@propertyflow.test')->sole();

        foreach ($votingOwners->slice(1, 24)->values() as $index => $unit) {
            $owner = $ownerOf($unit)->user;

            if ($owner === null || $owner->resident?->is($resident)) {
                continue;
            }

            if ($index === 0) {
                // This owner can't make it, and lets the board member vote for them.
                app(GrantProxy::class)->handle($budget, $unit, $owner, $board);
                $castVote->handle($budget, $unit, $board, $this->answers($budget, ['Yes', 'For']));

                continue;
            }

            $castVote->handle($budget, $unit, $owner, $this->answers($budget, [$index % 6 === 0 ? 'No' : 'Yes', $index % 4 === 0 ? 'Against' : 'For']));
        }
    }

    /**
     * A small rule library; one violation already fined (reported six weeks ago and escalated
     * on schedule), one fresh courtesy notice; the demo resident's renovation request awaiting
     * the board, and a neighbour's approved with conditions.
     */
    private function seedViolationsAndRenovations(Community $condo): void
    {
        $manager = User::where('email', 'manager@propertyflow.test')->sole();
        $board = User::where('email', 'board@propertyflow.test')->sole();
        $rule = fn (string $title, string $reference, int $cureDays, ?int $fineCents) => ViolationRule::factory()->for($condo)->create([
            'title' => $title, 'reference' => $reference, 'cure_days' => $cureDays, 'fine_cents' => $fineCents, 'max_fines' => 3,
        ]);

        $balcony = $rule('Items stored on balcony', 'Rules, s. 12', 14, 10000);
        $noise = $rule('Noise after 11pm', 'Rules, s. 4', 7, 15000);
        $rule('Pet off leash in common areas', 'Rules, s. 21', 7, 5000);
        $rule('Unapproved alterations', 'Declaration, art. 9', 30, null);

        $units = Unit::query()->where('community_id', $condo->id)->orderBy('id')->get()->values();
        $unitAt = fn (int $index): Unit => $units->get($index) ?? throw new LogicException("The demo condo has no unit #{$index}.");
        $now = CarbonImmutable::now($condo->timezone)->startOfDay();
        $balconyUnit = $unitAt(13);

        $this->travelTo($now->subWeeks(6), function () use ($balcony, $balconyUnit, $manager): void {
            app(ReportViolation::class)->handle($balcony, $balconyUnit, CarbonImmutable::now(), 'Bicycles, boxes and a barbecue tank stored on the balcony.', 'Balcony, facing the courtyard', [], $manager);
        });
        $fined = Violation::query()->where('unit_id', $balconyUnit->id)->sole();

        // Each step falls due the day after its cure period ends: a warning, then the first fine.
        foreach ([$now->subWeeks(4)->addDay(), $now->subWeeks(2)->addDays(2)] as $day) {
            $this->travelTo($day, fn () => app(EscalateViolation::class)->handle($fined, $day, null));
        }

        app(ReportViolation::class)->handle($noise, $unitAt(30), $now->subDay()->setTime(23, 40), 'Loud music reported by two neighbours after 11pm.', null, [], $manager);

        $resident = Resident::where('email', 'resident@propertyflow.test')->sole();
        $residentUnit = Residency::where('resident_id', $resident->id)->active()->firstOrFail()->unit;
        $submit = app(SubmitArchitecturalRequest::class);

        $submit->handle($residentUnit, $resident->user ?? throw new LogicException('Demo resident has no login.'), 'Replace carpet with engineered hardwood', "Living room and hallway, about 45 m².\nInstaller: Northern Floors. Work on weekdays 9am–5pm only.", 'Northern Floors Ltd.', $now->addWeeks(3), []);

        $neighbourUnit = $unitAt(5);
        $neighbourOwner = Residency::query()->where('unit_id', $neighbourUnit->id)->where('type', ResidencyType::Owner)->active()->firstOrFail()->resident;
        if ($neighbourOwner->user !== null) {
            $approved = $submit->handle($neighbourUnit, $neighbourOwner->user, 'Install balcony privacy screen', 'Frosted glass screen on the east side of the balcony.', null, null, []);
            $decide = app(DecideArchitecturalRequest::class);
            $decide->startReview($approved, $board);
            $decide->decide($approved->refresh(), ArchitecturalRequestStatus::ApprovedWithConditions, "Frosted glass only; matching the approved sample in the management office.\nNo fixings into the exterior wall.", null, $board);
        }
    }

    /**
     * Answers for a ballot, one label per question in order, as [question id => option id].
     *
     * @param  list<string>  $labels
     * @return array<int, int>
     */
    private function answers(Ballot $ballot, array $labels): array
    {
        $choices = [];

        foreach ($ballot->questions()->get()->values() as $index => $question) {
            $choices[$question->id] = (int) $question->options()->where('label', $labels[$index])->valueOrFail('id');
        }

        return $choices;
    }

    /**
     * Runs a callback with the clock set to the given moment.
     */
    private function travelTo(CarbonImmutable $moment, callable $callback): void
    {
        Date::setTestNow($moment);

        try {
            $callback();
        } finally {
            Date::setTestNow();
        }
    }

    /**
     * One vendor bill in every state: a small one a manager can approve, a large one waiting for
     * the board, an approved one waiting to be paid, and a paid one.
     */
    private function seedVendorBills(Community $condo, User $manager): void
    {
        $chartOfAccounts = app(ChartOfAccounts::class);
        $submit = app(SubmitVendorBill::class);
        $decide = app(DecideVendorBill::class);
        $plumber = Vendor::where('name', 'Ace Plumbing Co.')->sole();
        $elevators = Vendor::where('name', 'Reliable Elevator Services')->sole();
        $landscaper = Vendor::factory()->for($condo->company)->create(['name' => 'Greenway Grounds', 'trade' => 'Landscaping']);
        $board = User::where('email', 'board@propertyflow.test')->sole();
        $expense = fn (string $code) => $condo->accounts()->where('code', $code)->sole();
        $today = CarbonImmutable::now($condo->timezone)->startOfDay();
        $chartOfAccounts->ensureFor($condo);

        $bill = fn (Vendor $vendor, string $account, int $cents, int $daysAgo, string $description, string $reference) => $submit->handle(
            $condo, $vendor, $expense($account), Money::of($cents, $condo->currency), $today->subDays($daysAgo), $today->subDays($daysAgo)->addDays(30), $description, $reference, $manager,
        );

        $paid = $bill($landscaper, '5400', 185000, 40, 'August grounds maintenance', 'GG-2208');
        $decide->approve($paid, $manager);
        app(PayVendorBill::class)->handle($paid, PaymentMethod::Cheque, $today->subDays(20), 'CHQ 1041', $manager);

        $approved = $bill($plumber, '5000', 64000, 12, 'Replace riser valve, North Tower', 'ACE-4471');
        $decide->approve($approved, $manager, 'Matches work order');

        $bill($landscaper, '5400', 185000, 3, 'September grounds maintenance', 'GG-2209');
        $bill($elevators, '5000', 1_240_000, 2, 'Elevator 2 controller replacement', 'RES-9913');

        $rejected = $bill($plumber, '5000', 64000, 5, 'Replace riser valve, North Tower', 'ACE-4471');
        $decide->reject($rejected, $board, 'Duplicate of the bill already approved');
    }

    /**
     * One or two examples of every front-desk & security module, most created through their
     * real actions so notifications, snapshots and broadcasts fire just like they would live.
     */
    private function seedFrontDesk(Community $condo): void
    {
        $admin = User::where('email', 'demo@propertyflow.test')->sole();
        $staff = User::where('email', 'staff@propertyflow.test')->sole();
        $resident = Resident::where('email', 'resident@propertyflow.test')->sole();
        $residentUser = User::where('email', 'resident@propertyflow.test')->sole();
        $residentUnit = Residency::where('resident_id', $resident->id)->active()->firstOrFail()->unit;

        // Packages: one still on the shelf, one already picked up with a signature.
        app(LogPackage::class)->handle($condo, $staff, [
            'unit_id' => $residentUnit->id,
            'resident_id' => $resident->id,
            'carrier' => 'UPS',
            'tracking_number' => '1Z999AA10123456784',
            'shelf_location' => 'Shelf B',
        ]);
        $pickedUpPackage = app(LogPackage::class)->handle($condo, $staff, [
            'unit_id' => $residentUnit->id,
            'resident_id' => $resident->id,
            'carrier' => 'Amazon',
            'tracking_number' => null,
            'shelf_location' => 'Shelf A',
        ]);
        $pickedUpPackage->forceFill([
            'status' => PackageStatus::PickedUp,
            'released_at' => now()->subDay(),
            'released_by_id' => $staff->id,
            'released_to_name' => $resident->name,
        ])->save();

        // Visitors: one still on site, one already checked out.
        Visitor::factory()->for($condo)->create(['visitor_name' => 'Contractor - HVAC Service', 'unit_id' => null, 'purpose' => 'Annual inspection', 'logged_by_id' => $staff->id]);
        Visitor::factory()->for($condo)->checkedOut()->create(['visitor_name' => 'Dana Plumber', 'unit_id' => $residentUnit->id, 'purpose' => 'Plumbing repair', 'logged_by_id' => $staff->id]);

        // A guest pass the resident created for an upcoming visitor, still unredeemed.
        GuestPass::factory()->for($condo)->create([
            'unit_id' => $residentUnit->id,
            'resident_id' => $resident->id,
            'guest_name' => 'Rita\'s Sister',
        ]);

        // An active visitor parking permit.
        app(IssueParkingPermit::class)->handle($condo, $staff, [
            'unit_id' => $residentUnit->id,
            'plate_number' => 'CXYZ 123',
            'visitor_name' => 'Weekend guest',
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addDays(2)->toDateString(),
            'notes' => null,
        ]);

        // A resolved and an open incident report.
        $resolvedIncident = app(CreateIncidentReport::class)->handle($condo, $staff, [
            'unit_id' => null,
            'title' => 'Slippery lobby floor after cleaning',
            'description' => 'Wet floor sign was missing after the cleaning crew mopped the lobby.',
            'location' => 'Main lobby',
            'severity' => IncidentSeverity::Low->value,
            'occurred_at' => now()->subDays(3)->toDateTimeString(),
        ]);
        $resolvedIncident->forceFill(['resolved_at' => now()->subDays(2), 'resolution_notes' => 'Spoke with cleaning contractor about signage.'])->save();

        app(CreateIncidentReport::class)->handle($condo, $staff, [
            'unit_id' => null,
            'title' => 'Suspicious vehicle in visitor parking',
            'description' => 'A vehicle without a permit has been parked in a visitor spot for two days.',
            'location' => 'Visitor parking',
            'severity' => IncidentSeverity::Medium->value,
            'occurred_at' => now()->subHours(6)->toDateTimeString(),
        ]);

        // A key currently signed out to a vendor.
        $elevatorKey = AccessKey::factory()->for($condo)->create(['label' => 'Elevator machine room key']);
        AccessKeySignout::factory()->for($elevatorKey)->create([
            'signed_out_to' => 'Reliable Elevator Services',
            'signed_out_by_id' => $staff->id,
            'due_back_at' => now()->addHours(4),
        ]);

        // Someone pre-authorized to enter the resident's unit without them being home.
        $entryAuthorization = EntryAuthorization::factory()->for($condo)->create([
            'unit_id' => $residentUnit->id,
            'name' => 'Maple Cleaning Co.',
            'relationship' => 'Cleaner',
        ]);
        $entryAuthorization->forceFill(['created_by_id' => $resident->user_id])->save();

        // A patrol route with checkpoints, one already scanned tonight.
        $route = PatrolRoute::factory()->for($condo)->create(['name' => 'Night patrol']);
        $lobby = PatrolCheckpoint::factory()->for($route)->create(['name' => 'Main lobby', 'position' => 1]);
        PatrolCheckpoint::factory()->for($route)->create(['name' => 'Parking garage', 'position' => 2]);
        PatrolCheckpoint::factory()->for($route)->create(['name' => 'Pool gate', 'position' => 3]);
        app(ScanPatrolCheckpoint::class)->handle($lobby, $staff);

        // Shift log entries.
        ShiftLogEntry::factory()->for($condo)->create(['user_id' => $staff->id, 'body' => 'Shift started. All common areas quiet.']);
        ShiftLogEntry::factory()->for($condo)->create(['user_id' => $admin->id, 'body' => 'Reviewed the open incident about visitor parking with the manager.']);
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
