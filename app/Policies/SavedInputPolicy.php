<?php

namespace App\Policies;

use App\Models\SavedInput;
use App\Models\User;

/**
 * Who may use a reader's saved inputs: only the reader who saved them. Without this policy every write on the
 * `saved-inputs` mount is a 403, because Beam asks the policy and nothing allows it.
 */
class SavedInputPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SavedInput $saved): bool
    {
        return $saved->user_id === $user->getAuthIdentifier();
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, SavedInput $saved): bool
    {
        return $saved->user_id === $user->getAuthIdentifier();
    }

    public function delete(User $user, SavedInput $saved): bool
    {
        return $saved->user_id === $user->getAuthIdentifier();
    }
}
