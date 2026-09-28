<?php

namespace App\Actions\PublicSite;

use App\Models\Community;
use App\Models\ContactMessage;
use App\Support\Tenancy\CurrentCompany;

/**
 * Records a message submitted through a community's public website contact form. There is no
 * authenticated user in this context, so the company/community must be filled explicitly rather
 * than relying on the ambient {@see CurrentCompany}.
 */
class SubmitContactMessage
{
    public function handle(Community $community, string $name, string $email, string $message): ContactMessage
    {
        $contactMessage = new ContactMessage;
        $contactMessage->forceFill([
            'company_id' => $community->company_id,
            'community_id' => $community->id,
            'name' => $name,
            'email' => $email,
            'message' => $message,
        ])->save();

        return $contactMessage;
    }
}
