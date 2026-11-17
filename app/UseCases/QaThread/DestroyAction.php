<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Exceptions\QaBoard\QaThreadNotDeletableException;
use App\Models\QaThread;
use Illuminate\Support\Facades\DB;

/**
 * 質問スレッドの物理削除ユースケース。配下の回答は FK(cascadeOnDelete)で同時に削除される。
 *
 * - 投稿者による削除($allowWithReplies = false): 回答が 1 件でも付いていれば QaThreadNotDeletableException(409)
 * - 管理者のモデレーション削除($allowWithReplies = true): 回答の有無にかかわらず削除する
 */
final class DestroyAction
{
    /**
     * @throws QaThreadNotDeletableException
     */
    public function __invoke(QaThread $thread, bool $allowWithReplies = false): void
    {
        if (! $allowWithReplies && $thread->replies()->exists()) {
            throw new QaThreadNotDeletableException;
        }

        DB::transaction(fn () => $thread->delete());
    }
}
