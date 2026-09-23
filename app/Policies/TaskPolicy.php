<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Community;
use App\Models\Task;
use App\Models\User;
use App\Policies\Concerns\ChecksCommunityAccess;

class TaskPolicy
{
    use ChecksCommunityAccess;

    public function viewAny(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ViewTasks);
    }

    public function create(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ManageTasks);
    }

    public function update(User $user, Task $task): bool
    {
        return $this->allowedFor($user, $task, Permission::ManageTasks);
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->update($user, $task);
    }
}
