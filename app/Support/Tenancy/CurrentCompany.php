<?php

namespace App\Support\Tenancy;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Resolves the company that tenant-owned queries are scoped to.
 *
 * Defaults to the authenticated user's company; jobs and commands can set it explicitly.
 */
class CurrentCompany
{
    private ?int $companyId = null;

    private bool $explicitlySet = false;

    public function set(?int $companyId): void
    {
        $this->companyId = $companyId;
        $this->explicitlySet = true;
    }

    public function forget(): void
    {
        $this->companyId = null;
        $this->explicitlySet = false;
    }

    public function id(): ?int
    {
        if ($this->explicitlySet) {
            return $this->companyId;
        }

        $user = Auth::user();

        return $user instanceof User ? $user->company_id : null;
    }
}
