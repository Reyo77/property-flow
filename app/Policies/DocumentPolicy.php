<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Community;
use App\Models\Document;
use App\Models\User;
use App\Policies\Concerns\ChecksCommunityAccess;
use App\Policies\Concerns\ChecksDocumentVisibility;

class DocumentPolicy
{
    use ChecksCommunityAccess, ChecksDocumentVisibility;

    public function viewAny(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ViewDocuments)
            || $user->resident?->residencies()->where('community_id', $community->id)->active()->exists() === true;
    }

    public function view(User $user, Document $document): bool
    {
        return $this->canSeeVisibility($user, $document->visibility, $document->company_id, $document->community_id);
    }

    public function create(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ManageDocuments);
    }

    public function update(User $user, Document $document): bool
    {
        return $this->allowedFor($user, $document, Permission::ManageDocuments);
    }

    public function delete(User $user, Document $document): bool
    {
        return $this->update($user, $document);
    }

    /**
     * Uploading a new version needs the same right as editing the document.
     */
    public function upload(User $user, Document $document): bool
    {
        return $this->update($user, $document);
    }
}
