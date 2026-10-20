<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Hanya Super Admin & Editor yang diizinkan membuat, mengubah, atau menghapus Kategori Kanal.
     */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isEditor();
    }

    public function update(User $user, Category $category): bool
    {
        return $user->isSuperAdmin() || $user->isEditor();
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->isSuperAdmin();
    }
}
