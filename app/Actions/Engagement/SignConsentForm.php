<?php

namespace App\Actions\Engagement;

use App\Models\ConsentForm;
use App\Models\ConsentSignature;
use App\Models\User;
use App\Support\Governance\AudienceCheck;
use App\Support\Signatures;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Signs a consent form: typed name plus drawn signature, with when, from where, and a
 * fingerprint of exactly the text that was signed.
 */
class SignConsentForm
{
    public function __construct(private readonly AudienceCheck $audienceCheck) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(ConsentForm $form, User $signer, string $signedName, string $signatureDataUrl, ?string $ipAddress, ?string $userAgent): ConsentSignature
    {
        if (! $form->isOpen()) {
            throw ValidationException::withMessages(['signed_name' => __('This form is no longer open for signing.')]);
        }

        if (! $this->audienceCheck->includes($signer, $form->community_id, $form->audience)) {
            throw new AuthorizationException(__('This form isn\'t for you.'));
        }

        if (trim($signedName) === '') {
            throw ValidationException::withMessages(['signed_name' => __('Type your full name.')]);
        }

        try {
            $path = Signatures::store($signatureDataUrl, "signatures/{$form->company_id}/consent-forms/{$form->id}");
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['signature' => __('Draw your signature in the box.')]);
        }

        try {
            $signature = new ConsentSignature;
            $signature->forceFill([
                'company_id' => $form->company_id,
                'consent_form_id' => $form->id,
                'user_id' => $signer->id,
                'signed_name' => trim($signedName),
                'signature_disk_path' => $path,
                'body_hash' => $form->bodyHash(),
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent === null ? null : mb_substr($userAgent, 0, 255),
                'signed_at' => now(),
            ])->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['signed_name' => __('You have already signed this form.')]);
        }

        return $signature;
    }
}
