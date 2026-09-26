<?php

namespace App\Http\Webhooks;

use App\Enums\WebhookEvent;
use App\Http\Resources\Api\V1\AmenityBookingResource;
use App\Http\Resources\Api\V1\ArchitecturalRequestResource;
use App\Http\Resources\Api\V1\BallotResource;
use App\Http\Resources\Api\V1\InvoiceResource;
use App\Http\Resources\Api\V1\PackageResource;
use App\Http\Resources\Api\V1\PaymentResource;
use App\Http\Resources\Api\V1\ResidencyResource;
use App\Http\Resources\Api\V1\ResidentResource;
use App\Http\Resources\Api\V1\ServiceRequestResource;
use App\Http\Resources\Api\V1\ViolationResource;
use App\Http\Resources\Api\V1\VisitorResource;
use App\Http\Resources\Api\V1\WorkOrderResource;
use App\Models\Ballot;
use App\Models\Residency;
use App\Support\Webhooks\WebhookPayloads;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use InvalidArgumentException;

/**
 * Webhook payloads are the API's own resources, so a receiver can use the API docs to read them.
 */
class ApiResourcePayloads implements WebhookPayloads
{
    public function for(WebhookEvent $event, Model $subject): array
    {
        return match ($event) {
            WebhookEvent::ServiceRequestCreated, WebhookEvent::ServiceRequestStatusChanged => $this->resolve(ServiceRequestResource::class, $subject, ['unit.building']),
            WebhookEvent::WorkOrderStatusChanged => $this->resolve(WorkOrderResource::class, $subject, ['assignedUser', 'assignedVendor']),
            WebhookEvent::PackageLogged, WebhookEvent::PackageReleased => $this->resolve(PackageResource::class, $subject, ['unit.building', 'resident']),
            WebhookEvent::VisitorCheckedIn => $this->resolve(VisitorResource::class, $subject, ['unit.building']),
            WebhookEvent::AmenityBookingCreated, WebhookEvent::AmenityBookingStatusChanged => $this->resolve(AmenityBookingResource::class, $subject, ['amenity', 'unit.building']),
            WebhookEvent::InvoiceIssued => $this->resolve(InvoiceResource::class, $subject, ['unit.building', 'lines']),
            WebhookEvent::PaymentReceived => $this->resolve(PaymentResource::class, $subject, ['unit.building']),
            WebhookEvent::ViolationReported, WebhookEvent::ViolationEscalated => $this->resolve(ViolationResource::class, $subject, ['rule', 'unit.building', 'notices']),
            WebhookEvent::ArchitecturalRequestSubmitted, WebhookEvent::ArchitecturalRequestDecided => $this->resolve(ArchitecturalRequestResource::class, $subject, ['unit.building']),
            // Results are added explicitly: the resource shows them only to a viewer allowed to see them.
            WebhookEvent::BallotClosed => $subject instanceof Ballot
                ? [...$this->resolve(BallotResource::class, $subject, ['meeting', 'questions.options']), 'results' => $subject->results]
                : throw new InvalidArgumentException('ballot.closed is about a ballot.'),
            WebhookEvent::ResidentMovedIn, WebhookEvent::ResidentMovedOut => $subject instanceof Residency
                ? [
                    'resident' => $this->resolve(ResidentResource::class, $subject->loadMissing('resident')->resident, []),
                    'residency' => $this->resolve(ResidencyResource::class, $subject, ['unit.building']),
                ]
                : throw new InvalidArgumentException("{$event->value} is about a residency."),
            WebhookEvent::Ping => [],
        };
    }

    /**
     * @param  class-string<JsonResource>  $resource
     * @param  list<string>  $relations
     * @return array<string, mixed>
     */
    private function resolve(string $resource, Model $subject, array $relations): array
    {
        /** @var array<string, mixed> */
        return (new $resource($subject->loadMissing($relations)))->resolve();
    }
}
