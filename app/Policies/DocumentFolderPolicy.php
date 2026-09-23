<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Community;
use App\Models\DocumentFolder;
use App\Models\User;
use App\Policies\Concerns\ChecksCommunityAccess;
use App\Policies\Concerns\ChecksDocumentVisibility;

class DocumentFolderPolicy
{
    use ChecksCommunityAccess, ChecksDocumentVisibility;

    public function viewAny(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ViewDocuments)
            || $user->resident?->residencies()->where('community_id', $community->id)->active()->exists() === true;
    }

    public function view(User $user, DocumentFolder $folder): bool
    {
        return $this->canSeeVisibility($user, $folder->visibility, $folder->company_id, $folder->community_id);
    }

    public function create(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ManageDocuments);
    }

    public function update(User $user, DocumentFolder $folder): bool
    {
        return $this->allowedFor($user, $folder, Permission::ManageDocuments);
    }

    public function delete(User $user, DocumentFolder $folder): bool
    {
        return $this->update($user, $folder);
    }
}
