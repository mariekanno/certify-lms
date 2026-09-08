<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\User;

class PlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, Plan $plan): bool
    {
        return $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, Plan $plan): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, Plan $plan): bool
    {
        return $this->isAdmin($user);
    }

    public function publish(User $user, Plan $plan): bool
    {
        return $this->isAdmin($user);
    }

    public function archive(User $user, Plan $plan): bool
    {
        return $this->isAdmin($user);
    }

    public function unarchive(User $user, Plan $plan): bool
    {
        return $this->isAdmin($user);
    }

    private function isAdmin(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }
}
