<?php

namespace App\Enums;

/**
 * Everything a company can subscribe a webhook endpoint to. The value is the `event` name sent in
 * each delivery; `data` in the payload is the same shape as the matching API resource.
 */
enum WebhookEvent: string
{
    case ServiceRequestCreated = 'service_request.created';
    case ServiceRequestStatusChanged = 'service_request.status_changed';
    case WorkOrderStatusChanged = 'work_order.status_changed';
    case PackageLogged = 'package.logged';
    case PackageReleased = 'package.released';
    case VisitorCheckedIn = 'visitor.checked_in';
    case AmenityBookingCreated = 'amenity_booking.created';
    case AmenityBookingStatusChanged = 'amenity_booking.status_changed';
    case InvoiceIssued = 'invoice.issued';
    case PaymentReceived = 'payment.received';
    case ViolationReported = 'violation.reported';
    case ViolationEscalated = 'violation.escalated';
    case ArchitecturalRequestSubmitted = 'architectural_request.submitted';
    case ArchitecturalRequestDecided = 'architectural_request.decided';
    case BallotClosed = 'ballot.closed';
    case ResidentMovedIn = 'resident.moved_in';
    case ResidentMovedOut = 'resident.moved_out';
    // Sent only by "Send test"; endpoints can't subscribe to it.
    case Ping = 'webhook.ping';

    public function label(): string
    {
        return match ($this) {
            self::ServiceRequestCreated => __('A service request is reported'),
            self::ServiceRequestStatusChanged => __('A service request changes status'),
            self::WorkOrderStatusChanged => __('A work order changes status'),
            self::PackageLogged => __('A package arrives'),
            self::PackageReleased => __('A package is picked up'),
            self::VisitorCheckedIn => __('A visitor checks in'),
            self::AmenityBookingCreated => __('An amenity is booked'),
            self::AmenityBookingStatusChanged => __('A booking is approved, rejected or cancelled'),
            self::InvoiceIssued => __('An invoice is issued'),
            self::PaymentReceived => __('A payment is received'),
            self::ViolationReported => __('A violation is reported'),
            self::ViolationEscalated => __('A violation escalates (warning or fine)'),
            self::ArchitecturalRequestSubmitted => __('A renovation request is submitted'),
            self::ArchitecturalRequestDecided => __('A renovation request is decided'),
            self::BallotClosed => __('A ballot closes and is counted'),
            self::ResidentMovedIn => __('A resident moves in'),
            self::ResidentMovedOut => __('A resident moves out'),
            self::Ping => __('Test ping'),
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::ServiceRequestCreated, self::ServiceRequestStatusChanged, self::WorkOrderStatusChanged => __('Maintenance'),
            self::PackageLogged, self::PackageReleased, self::VisitorCheckedIn => __('Front desk'),
            self::AmenityBookingCreated, self::AmenityBookingStatusChanged => __('Amenities'),
            self::InvoiceIssued, self::PaymentReceived => __('Finance'),
            self::ViolationReported, self::ViolationEscalated, self::ArchitecturalRequestSubmitted, self::ArchitecturalRequestDecided, self::BallotClosed => __('Governance'),
            self::ResidentMovedIn, self::ResidentMovedOut => __('Residents'),
            self::Ping => __('Testing'),
        };
    }

    /**
     * The events an endpoint can subscribe to (everything but the test ping).
     *
     * @return list<self>
     */
    public static function subscribable(): array
    {
        $events = [];

        foreach (self::cases() as $event) {
            if ($event !== self::Ping) {
                $events[] = $event;
            }
        }

        return $events;
    }
}
