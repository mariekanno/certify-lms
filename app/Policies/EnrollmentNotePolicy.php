<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;

class EnrollmentNotePolicy
{
    public function viewAny(User $auth, Enrollment $enrollment): bool
    {
        if ($auth->role === UserRole::Admin) {
            return true;
        }

        if ($auth->role !== UserRole::Coach) {
            return false;
        }

        return $enrollment->certification
            ->coaches
            ->contains('id', $auth->id);
    }

    public function create(User $auth, Enrollment $enrollment): bool
    {
        return $this->viewAny($auth, $enrollment);
    }

    public function update(User $auth, EnrollmentNote $note): bool
    {
        if ($auth->role === UserRole::Admin) {
            return true;
        }

        if ($auth->role !== UserRole::Coach) {
            return false;
        }

        if ($note->user_id !== $auth->id) {
            return false;
        }

        return $note->enrollment
            ->certification
            ->coaches
            ->contains('id', $auth->id);
    }

    public function delete(User $auth, EnrollmentNote $note): bool
    {
        return $this->update($auth, $note);
    }
}
