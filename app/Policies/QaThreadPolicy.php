<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Models\QaThread;
use App\Models\User;

/**
 * 質問掲示板スレッド(QaThread)の認可ルール。
 *
 * - viewAny: admin / coach / student(取得範囲は QaThread::scopeVisibleTo で絞る)
 * - view: admin は全件、student は公開中資格、coach は公開中かつ担当資格のスレッドのみ
 * - create: student のみ
 * - update / resolve / unresolve: 投稿者本人かつ view 可能な場合のみ(admin の内容編集・解決マーク代行は不可)
 * - delete: admin は全件(モデレーション)。投稿者本人は view 可能かつ回答が 0 件の場合のみ
 *
 * 解決状態(Open / Resolved)による出し分けは画面と Action 側で行い、本 Policy では判定しない。
 */
class QaThreadPolicy
{
    public function viewAny(User $auth): bool
    {
        return in_array($auth->role, [UserRole::Admin, UserRole::Coach, UserRole::Student], true);
    }

    public function view(User $auth, QaThread $thread): bool
    {
        return match ($auth->role) {
            UserRole::Admin => true,
            UserRole::Student => $this->isCertificationPublished($thread),
            UserRole::Coach => $this->isCertificationPublished($thread)
                && $this->isAssignedCoach($auth, $thread),
            default => false,
        };
    }

    public function create(User $auth): bool
    {
        return $auth->role === UserRole::Student;
    }

    public function update(User $auth, QaThread $thread): bool
    {
        return $this->isAuthorAndVisible($auth, $thread);
    }

    public function delete(User $auth, QaThread $thread): bool
    {
        if ($auth->role === UserRole::Admin) {
            return true;
        }

        // 回答が付いたスレッドは集合知として残すため、投稿者本人でも削除不可
        return $this->isAuthorAndVisible($auth, $thread)
            && ! $thread->replies()->exists();
    }

    public function resolve(User $auth, QaThread $thread): bool
    {
        return $this->isAuthorAndVisible($auth, $thread);
    }

    public function unresolve(User $auth, QaThread $thread): bool
    {
        return $this->isAuthorAndVisible($auth, $thread);
    }

    private function isAuthorAndVisible(User $auth, QaThread $thread): bool
    {
        return $thread->user_id === $auth->id && $this->view($auth, $thread);
    }

    private function isCertificationPublished(QaThread $thread): bool
    {
        return $thread->certification?->status === CertificationStatus::Published;
    }

    private function isAssignedCoach(User $coach, QaThread $thread): bool
    {
        $certification = $thread->certification;

        // 詳細画面では回答ごとに判定が走るため、Eager Load 済みなら追加クエリを発行しない
        if ($certification->relationLoaded('coaches')) {
            return $certification->coaches->contains('id', $coach->id);
        }

        return $certification->coaches()->where('users.id', $coach->id)->exists();
    }
}
