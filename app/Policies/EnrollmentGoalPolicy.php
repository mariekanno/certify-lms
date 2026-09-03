<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;

class EnrollmentGoalPolicy
{
    public function view(User $user, EnrollmentGoal $goal): bool
    {
        return $this->canViewEnrollment($user, $goal->enrollment);
    }

    public function create(User $user, Enrollment $enrollment): bool
    {
        return $this->canManageEnrollment($user, $enrollment);
    }

    public function update(User $user, EnrollmentGoal $goal): bool
    {
        return $this->canManageEnrollment($user, $goal->enrollment);
    }

    public function delete(User $user, EnrollmentGoal $goal): bool
    {
        return $this->canManageEnrollment($user, $goal->enrollment);
    }

    public function markAchieved(User $user, EnrollmentGoal $goal): bool
    {
        return $this->canManageEnrollment($user, $goal->enrollment);
    }

    public function unmarkAchieved(User $user, EnrollmentGoal $goal): bool
    {
        return $this->canManageEnrollment($user, $goal->enrollment);
    }

    private function canManageEnrollment(
        User $user,
        Enrollment $enrollment,
    ): bool {
        return $user->role === UserRole::Student
            && $enrollment->user_id === $user->id
            && $enrollment->status === EnrollmentStatus::Learning;
    }

    private function canViewEnrollment(
        User $user,
        Enrollment $enrollment,
    ): bool {
        return match ($user->role) {
            UserRole::Admin => true,
            UserRole::Student => $enrollment->user_id === $user->id,
            UserRole::Coach => $this->isAssignedCoach($enrollment, $user),
        };
    }

    private function isAssignedCoach(
        Enrollment $enrollment,
        User $coach,
    ): bool {
        $enrollment->loadMissing('certification.coaches');

        return $enrollment->certification?->coaches
            ->contains('id', $coach->id) ?? false;
    }
}
