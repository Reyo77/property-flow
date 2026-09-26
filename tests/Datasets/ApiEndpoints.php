<?php

use App\Models\Amenity;
use App\Models\AmenityBooking;
use App\Models\Announcement;
use App\Models\ArchitecturalRequest;
use App\Models\Ballot;
use App\Models\Building;
use App\Models\Community;
use App\Models\ConsentForm;
use App\Models\Document;
use App\Models\Event;
use App\Models\ForumTopic;
use App\Models\GuestPass;
use App\Models\IncidentReport;
use App\Models\Invoice;
use App\Models\Meeting;
use App\Models\Package;
use App\Models\Payment;
use App\Models\ServiceRequest;
use App\Models\Survey;
use App\Models\Unit;
use App\Models\VendorBill;
use App\Models\Violation;
use App\Models\ViolationRule;
use App\Models\WorkOrder;

/**
 * Every read endpoint under a community: the route name and a function that creates a record in
 * the given community and returns the extra route parameters (none for a list).
 *
 * @return array<string, array{0: string, 1: Closure(Community): array<string, int>}>
 */
dataset('community endpoints', function (): array {
    $none = fn (Community $community): array => [];

    return [
        'community' => ['api.v1.communities.show', $none],
        'buildings' => ['api.v1.communities.buildings.index', function (Community $c): array {
            Building::factory()->for($c)->create();

            return [];
        }],
        'units' => ['api.v1.communities.units.index', $none],
        'unit' => ['api.v1.communities.units.show', fn (Community $c) => ['unit' => Unit::factory()->for($c)->create()->id]],
        'residents' => ['api.v1.communities.residents.index', $none],
        'resident' => ['api.v1.communities.residents.show', fn (Community $c) => ['resident' => residentOf($c)->id]],
        'announcements' => ['api.v1.communities.announcements.index', $none],
        'announcement' => ['api.v1.communities.announcements.show', fn (Community $c) => ['announcement' => Announcement::factory()->for($c)->published()->create()->id]],
        'events' => ['api.v1.communities.events.index', $none],
        'event' => ['api.v1.communities.events.show', fn (Community $c) => ['event' => Event::factory()->for($c)->create()->id]],
        'document folders' => ['api.v1.communities.document-folders.index', $none],
        'documents' => ['api.v1.communities.documents.index', $none],
        'document' => ['api.v1.communities.documents.show', fn (Community $c) => ['document' => Document::factory()->for($c)->withVersion()->create()->id]],
        'service requests' => ['api.v1.communities.service-requests.index', $none],
        'service request' => ['api.v1.communities.service-requests.show', fn (Community $c) => ['serviceRequest' => ServiceRequest::factory()->for($c)->create()->id]],
        'work orders' => ['api.v1.communities.work-orders.index', $none],
        'work order' => ['api.v1.communities.work-orders.show', fn (Community $c) => ['workOrder' => WorkOrder::factory()->for($c)->create()->id]],
        'amenities' => ['api.v1.communities.amenities.index', $none],
        'amenity' => ['api.v1.communities.amenities.show', fn (Community $c) => ['amenity' => Amenity::factory()->for($c)->create()->id]],
        'amenity bookings' => ['api.v1.communities.amenity-bookings.index', $none],
        'amenity booking' => ['api.v1.communities.amenity-bookings.show', fn (Community $c) => ['amenityBooking' => AmenityBooking::factory()->for(Amenity::factory()->for($c))->create()->id]],
        'packages' => ['api.v1.communities.packages.index', $none],
        'package' => ['api.v1.communities.packages.show', fn (Community $c) => ['package' => Package::factory()->for($c)->create()->id]],
        'visitors' => ['api.v1.communities.visitors.index', $none],
        'guest passes' => ['api.v1.communities.guest-passes.index', $none],
        'guest pass' => ['api.v1.communities.guest-passes.show', fn (Community $c) => ['guestPass' => GuestPass::factory()->for($c)->create()->id]],
        'parking permits' => ['api.v1.communities.parking-permits.index', $none],
        'incident reports' => ['api.v1.communities.incident-reports.index', $none],
        'incident report' => ['api.v1.communities.incident-reports.show', fn (Community $c) => ['incidentReport' => IncidentReport::factory()->for($c)->create()->id]],
        'unit account' => ['api.v1.communities.units.account.show', fn (Community $c) => ['unit' => Unit::factory()->for($c)->create()->id]],
        'unit statement' => ['api.v1.communities.units.statement.index', fn (Community $c) => ['unit' => Unit::factory()->for($c)->create()->id]],
        'invoices' => ['api.v1.communities.invoices.index', $none],
        'invoice' => ['api.v1.communities.invoices.show', fn (Community $c) => ['invoice' => Invoice::factory()->for(Unit::factory()->for($c))->create()->id]],
        'payments' => ['api.v1.communities.payments.index', $none],
        'payment' => ['api.v1.communities.payments.show', fn (Community $c) => ['payment' => Payment::factory()->for(Unit::factory()->for($c))->create()->id]],
        'vendor bills' => ['api.v1.communities.vendor-bills.index', $none],
        'vendor bill' => ['api.v1.communities.vendor-bills.show', fn (Community $c) => ['vendorBill' => VendorBill::factory()->for($c)->create()->id]],
        'ballots' => ['api.v1.communities.ballots.index', $none],
        'ballot' => ['api.v1.communities.ballots.show', fn (Community $c) => ['ballot' => Ballot::factory()->for($c)->open()->withQuestion()->create()->id]],
        'meetings' => ['api.v1.communities.meetings.index', $none],
        'meeting' => ['api.v1.communities.meetings.show', fn (Community $c) => ['meeting' => Meeting::factory()->for($c)->create()->id]],
        'violations' => ['api.v1.communities.violations.index', $none],
        'violation' => ['api.v1.communities.violations.show', fn (Community $c) => ['violation' => Violation::factory()->for(ViolationRule::factory()->for($c), 'rule')->create()->id]],
        'renovation requests' => ['api.v1.communities.architectural-requests.index', $none],
        'renovation request' => ['api.v1.communities.architectural-requests.show', fn (Community $c) => ['architecturalRequest' => ArchitecturalRequest::factory()->for($c)->create()->id]],
        'surveys' => ['api.v1.communities.surveys.index', $none],
        'survey' => ['api.v1.communities.surveys.show', fn (Community $c) => ['survey' => Survey::factory()->for($c)->create()->id]],
        'forms' => ['api.v1.communities.consent-forms.index', $none],
        'form' => ['api.v1.communities.consent-forms.show', fn (Community $c) => ['consentForm' => ConsentForm::factory()->for($c)->create()->id]],
        'board posts' => ['api.v1.communities.forum-topics.index', $none],
        'board post' => ['api.v1.communities.forum-topics.show', fn (Community $c) => ['forumTopic' => ForumTopic::factory()->for($c)->create()->id]],
    ];
});
