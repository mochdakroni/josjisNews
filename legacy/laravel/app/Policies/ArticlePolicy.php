<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Article;
use App\Models\User;

class ArticlePolicy
{
    /**
     * Semua anggota redaksi (Super Admin, Editor, Jurnalis) dapat melihat daftar artikel.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [
            UserRole::SUPER_ADMIN,
            UserRole::EDITOR,
            UserRole::JOURNALIST,
        ], true);
    }

    /**
     * Jurnalis hanya boleh melihat artikel miliknya sendiri.
     */
    public function view(User $user, Article $article): bool
    {
        if ($user->isSuperAdmin() || $user->isEditor()) {
            return true;
        }

        return $article->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Jurnalis hanya boleh mengubah artikel miliknya sendiri yang belum dipublish,
     * sedangkan Editor & Super Admin dapat menyunting seluruh artikel.
     */
    public function update(User $user, Article $article): bool
    {
        if ($user->isSuperAdmin() || $user->isEditor()) {
            return true;
        }

        return $article->user_id === $user->id;
    }

    /**
     * Hanya Super Admin dan Editor yang dapat menghapus artikel,
     * atau Jurnalis hanya pada draf miliknya sendiri.
     */
    public function delete(User $user, Article $article): bool
    {
        if ($user->isSuperAdmin() || $user->isEditor()) {
            return true;
        }

        return $article->user_id === $user->id && $article->status->value === 'draft';
    }
}
