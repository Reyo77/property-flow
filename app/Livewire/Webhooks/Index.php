<?php

namespace App\Livewire\Webhooks;

use App\Actions\Webhooks\SaveWebhookEndpoint;
use App\Enums\WebhookDeliveryStatus;
use App\Enums\WebhookEvent;
use App\Jobs\SendWebhookDelivery;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Support\Webhooks\Webhooks;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Webhooks')]
class Index extends Component
{
    use InteractsWithCurrentUser;

    #[Locked]
    public ?int $editingEndpointId = null;

    public string $url = '';

    public string $description = '';

    /**
     * @var list<string>
     */
    public array $events = [];

    public bool $active = true;

    #[Locked]
    public ?int $selectedEndpointId = null;

    /**
     * Only set when an admin asks to see a secret, so it isn't in the page otherwise.
     */
    #[Locked]
    public ?string $revealedSecret = null;

    #[Locked]
    public ?int $revealedSecretEndpointId = null;

    public function mount(): void
    {
        $this->authorize('viewAny', WebhookEndpoint::class);
    }

    /**
     * @return Collection<int, WebhookEndpoint>
     */
    #[Computed]
    public function endpoints(): Collection
    {
        return WebhookEndpoint::query()->latest()->get();
    }

    /**
     * @return Collection<int, WebhookDelivery>
     */
    #[Computed]
    public function deliveries(): Collection
    {
        if ($this->selectedEndpointId === null) {
            return new Collection;
        }

        return WebhookDelivery::query()->where('webhook_endpoint_id', $this->selectedEndpointId)->latest('id')->limit(25)->get();
    }

    /**
     * @return array<string, list<WebhookEvent>>
     */
    public function eventGroups(): array
    {
        $groups = [];

        foreach (WebhookEvent::subscribable() as $event) {
            $groups[$event->group()][] = $event;
        }

        return $groups;
    }

    public function create(): void
    {
        $this->authorize('create', WebhookEndpoint::class);

        $this->resetValidation();
        $this->reset('editingEndpointId', 'url', 'description');
        $this->events = array_map(fn (WebhookEvent $event) => $event->value, WebhookEvent::subscribable());
        $this->active = true;

        Flux::modal('endpoint-form')->show();
    }

    public function edit(int $endpointId): void
    {
        $endpoint = $this->findEndpoint($endpointId);
        $this->authorize('update', $endpoint);

        $this->resetValidation();
        $this->editingEndpointId = $endpoint->id;
        $this->url = $endpoint->url;
        $this->description = (string) $endpoint->description;
        $this->events = $endpoint->events;
        $this->active = $endpoint->is_active;

        Flux::modal('endpoint-form')->show();
    }

    public function save(SaveWebhookEndpoint $saveEndpoint): void
    {
        $endpoint = $this->editingEndpointId === null ? null : $this->findEndpoint($this->editingEndpointId);

        $endpoint === null
            ? $this->authorize('create', WebhookEndpoint::class)
            : $this->authorize('update', $endpoint);

        $validated = $this->validate([
            'url' => ['required', 'string', 'url', 'max:2048'],
            'description' => ['nullable', 'string', 'max:255'],
            'events' => ['array'],
            'events.*' => ['string'],
            'active' => ['boolean'],
        ]);

        $saved = $saveEndpoint->handle($this->currentUser(), $endpoint, $validated['url'], $validated['description'] ?: null, array_values($validated['events']), $validated['active']);

        Flux::modal('endpoint-form')->close();
        Flux::toast(variant: 'success', text: $endpoint === null ? __('Webhook added. Copy its signing secret to your receiver.') : __('Webhook updated.'));

        if ($endpoint === null) {
            $this->revealSecret($saved->id);
        }

        unset($this->endpoints);
    }

    public function revealSecret(int $endpointId): void
    {
        $endpoint = $this->findEndpoint($endpointId);
        $this->authorize('update', $endpoint);

        $this->revealedSecret = $endpoint->secret;
        $this->revealedSecretEndpointId = $endpoint->id;
    }

    public function hideSecret(): void
    {
        $this->reset('revealedSecret', 'revealedSecretEndpointId');
    }

    public function rotateSecret(int $endpointId): void
    {
        $endpoint = $this->findEndpoint($endpointId);
        $this->authorize('update', $endpoint);

        $endpoint->forceFill(['secret' => WebhookEndpoint::generateSecret()])->save();
        $this->revealSecret($endpoint->id);

        Flux::toast(variant: 'success', text: __('New signing secret created. Update your receiver; the old one no longer works.'));
    }

    public function sendTest(int $endpointId, Webhooks $webhooks): void
    {
        $endpoint = $this->findEndpoint($endpointId);
        $this->authorize('update', $endpoint);

        $webhooks->send($endpoint, WebhookEvent::Ping, null, ['message' => __('Hello from PropertyFlow. This is a test delivery.')]);

        $this->selectedEndpointId = $endpoint->id;
        unset($this->deliveries, $this->endpoints);
        Flux::toast(text: __('Test delivery sent.'));
    }

    public function showDeliveries(int $endpointId): void
    {
        $this->authorize('update', $this->findEndpoint($endpointId));

        $this->selectedEndpointId = $this->selectedEndpointId === $endpointId ? null : $endpointId;
        unset($this->deliveries);
    }

    public function redeliver(int $deliveryId): void
    {
        $delivery = WebhookDelivery::query()->findOrFail($deliveryId);
        $this->authorize('update', $this->findEndpoint($delivery->webhook_endpoint_id));

        $delivery->forceFill(['status' => WebhookDeliveryStatus::Pending, 'attempts' => 0, 'response_status' => null, 'response_excerpt' => null])->save();
        SendWebhookDelivery::dispatch($delivery->id);

        unset($this->deliveries, $this->endpoints);
        Flux::toast(text: __('Sending again.'));
    }

    public function delete(int $endpointId): void
    {
        $endpoint = $this->findEndpoint($endpointId);
        $this->authorize('delete', $endpoint);

        $endpoint->delete();

        if ($this->selectedEndpointId === $endpointId) {
            $this->selectedEndpointId = null;
        }

        $this->hideSecret();
        unset($this->endpoints, $this->deliveries);
        Flux::toast(variant: 'success', text: __('Webhook deleted.'));
    }

    public function render(): View
    {
        return view('livewire.webhooks.index');
    }

    private function findEndpoint(int $endpointId): WebhookEndpoint
    {
        return WebhookEndpoint::query()->findOrFail($endpointId);
    }
}
