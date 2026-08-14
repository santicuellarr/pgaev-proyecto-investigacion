<?php

namespace App\Services;

use App\Models\User;

class ProfileService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data): User
    {
        $user->update($data);

        return $user->fresh();
    }
}
