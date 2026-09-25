<?php

namespace App\Livewire\Engagement;

use App\Actions\Engagement\SignConsentForm;
use App\Enums\Audience;
use App\Enums\ResidencyType;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\Community;
use App\Models\ConsentForm;
use App\Models\ConsentSignature;
use App\Models\Residency;
use App\Support\Governance\AudienceCheck;
use Flux\Flux;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Form')]
class ConsentFormShow extends Component
{
    use InteractsWithCurrentUser;

    public Community $community;

    public ConsentForm $consentForm;

    public string $signed_name = '';

    public string $signature = '';

    public bool $agreed = false;

    public function mount(): void
    {
        $this->authorize('view', $this->consentForm);

        $this->signed_name = $this->currentUser()->name;
    }

    #[Computed]
    public function mySignature(): ?ConsentSignature
    {
        return $this->consentForm->signatures()->where('user_id', $this->currentUser()->id)->first();
    }

    #[Computed]
    public function canSign(): bool
    {
        return $this->consentForm->isOpen() && $this->mySignature() === null
            && app(AudienceCheck::class)->includes($this->currentUser(), $this->consentForm->community_id, $this->consentForm->audience);
    }

    /**
     * @return Collection<int, ConsentSignature>
     */
    #[Computed]
    public function signatures(): Collection
    {
        return $this->consentForm->signatures()->with('user')->orderBy('signed_at')->get();
    }

    /**
     * How many people the form is for, to show progress.
     */
    #[Computed]
    public function audienceSize(): int
    {
        return Residency::query()
            ->where('community_id', $this->community->id)
            ->when($this->consentForm->audience === Audience::Owners, fn ($query) => $query->where('type', ResidencyType::Owner))
            ->active()
            ->whereHas('resident', fn ($query) => $query->whereNotNull('user_id'))
            ->distinct()
            ->count('resident_id');
    }

    public function sign(SignConsentForm $signConsentForm): void
    {
        $this->resetErrorBag();

        if (! $this->agreed) {
            $this->addError('agreed', __('Tick the box to confirm you agree.'));

            return;
        }

        try {
            $signConsentForm->handle($this->consentForm, $this->currentUser(), $this->signed_name, $this->signature, request()->ip(), request()->userAgent());
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }

            return;
        } catch (AuthorizationException $exception) {
            $this->addError('signed_name', $exception->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Signed. Thank you.'));
        unset($this->mySignature, $this->canSign, $this->signatures);
    }

    public function publish(): void
    {
        $this->authorize('manage', $this->consentForm);

        $this->consentForm->forceFill(['published_at' => now()])->save();
        unset($this->canSign);
    }

    public function close(): void
    {
        $this->authorize('manage', $this->consentForm);

        $this->consentForm->forceFill(['closes_at' => now()])->save();
        unset($this->canSign);
    }

    public function render(): View
    {
        return view('livewire.engagement.consent-form-show');
    }
}
