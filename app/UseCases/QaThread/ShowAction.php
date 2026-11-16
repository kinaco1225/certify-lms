<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Models\QaReply;
use App\Models\QaThread;

/**
 * 質問スレッド詳細の表示用に、スレッド本体と配下の回答(投稿順)を一括ロードするユースケース。
 *
 * 画面では回答ごとに QaReplyPolicy(update / delete)が評価され、その中で親スレッドと
 * 資格の担当コーチを参照する。回答 → 親スレッドの逆参照をセットし、担当コーチも Eager Load して、
 * 回答件数に比例したクエリが発行されないようにする。
 */
final class ShowAction
{
    public function __invoke(QaThread $thread): QaThread
    {
        $thread->load(['user', 'certification.coaches', 'replies.user'])
            ->loadCount('replies');

        $thread->replies->each(
            fn (QaReply $reply) => $reply->setRelation('thread', $thread),
        );

        return $thread;
    }
}
