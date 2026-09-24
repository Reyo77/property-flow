<?php

namespace App\Actions\FrontDesk;

use App\Enums\PackageStatus;
use App\Events\FrontDeskActivity;
use App\Models\Package;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;

class ReleasePackage
{
    /**
     * @throws LogicException
     */
    public function handle(Package $package, User $releasedBy, string $releasedToName, ?string $signatureDataUrl = null): void
    {
        if ($package->status !== PackageStatus::AwaitingPickup) {
            throw new LogicException('This package has already been released.');
        }

        $attributes = [
            'status' => PackageStatus::PickedUp,
            'released_at' => now(),
            'released_by_id' => $releasedBy->id,
            'released_to_name' => $releasedToName,
        ];

        if ($signatureDataUrl !== null) {
            $attributes['signature_disk_path'] = $this->storeSignature($package, $signatureDataUrl);
        }

        $package->forceFill($attributes)->save();

        FrontDeskActivity::dispatch($package->community_id, 'package', __('Package released to :name.', ['name' => $releasedToName]));
    }

    private function storeSignature(Package $package, string $dataUrl): string
    {
        $contents = base64_decode(str_contains($dataUrl, ',') ? explode(',', $dataUrl, 2)[1] : $dataUrl, true);

        if ($contents === false) {
            throw new LogicException('The signature could not be read.');
        }

        $diskPath = "signatures/{$package->company_id}/packages/".Str::uuid().'.png';

        Storage::disk('local')->put($diskPath, $contents);

        return $diskPath;
    }
}
