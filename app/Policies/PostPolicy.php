<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

/**
 * Who may read a Guide. Everyone may: the `guides` resource's scope already keeps drafts out,
 * and a guest reads through its declared Public Read without asking here.
 */
class PostPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Post $post): bool
    {
        return true;
    }
}
