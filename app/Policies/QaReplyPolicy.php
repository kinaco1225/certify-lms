<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;

/**
 * 質問掲示板の回答(QaReply)の認可ルール。
 *
 * - create: student / coach のうち、親スレッドを閲覧できる場合のみ(admin は回答不可)
 * - update: 投稿者本人かつ親スレッドを閲覧できる場合のみ
 * - delete: admin は全件(モデレーション)。それ以外は update と同条件
 *
 * 親スレッドの閲覧可否は QaThreadPolicy::view に委譲し、判定ルールを 1 箇所に保つ。
 */
class QaReplyPolicy
{
    public function __construct(
        private readonly QaThreadPolicy $threadPolicy,
    ) {}

    public function create(User $auth, QaThread $thread): bool
    {
        if (! in_array($auth->role, [UserRole::Student, UserRole::Coach], true)) {
            return false;
        }

        return $this->threadPolicy->view($auth, $thread);
    }

    public function update(User $auth, QaReply $reply): bool
    {
        return $reply->user_id === $auth->id
            && $this->threadPolicy->view($auth, $reply->thread);
    }

    public function delete(User $auth, QaReply $reply): bool
    {
        if ($auth->role === UserRole::Admin) {
            return true;
        }

        return $this->update($auth, $reply);
    }
}
