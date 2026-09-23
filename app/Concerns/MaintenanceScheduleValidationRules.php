<?php

namespace App\Concerns;

use App\Enums\Assignee;
use App\Models\Community;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait MaintenanceScheduleValidationRules
{
    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function maintenanceScheduleRules(Community $community, string $assigneeType): array
    {
        $assignee = Assignee::tryFrom($assigneeType);

        return [
            'title' => ['required', 'string', 'max:255'],
            'interval_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'next_due_on' => ['required', 'date'],
            'assignee_type' => ['nullable', Rule::enum(Assignee::class)],
            'assigned_user_id' => [
                $assignee === Assignee::Staff ? 'required' : 'exclude',
                'integer',
                Rule::exists(User::class, 'id')->where('company_id', $community->company_id),
            ],
            'assigned_vendor_id' => [
                $assignee === Assignee::Vendor ? 'required' : 'exclude',
                'integer',
                Rule::exists(Vendor::class, 'id')->where('company_id', $community->company_id)->withoutTrashed(),
            ],
        ];
    }
}
