<?php

declare(strict_types=1);

namespace App\UseCases\QaReply;

use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use App\Notifications\QaBoard\QaReplyReceivedNotification;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;

/**
 * 受講生 / コーチによる回答投稿ユースケース。解決済スレッドへの補足回答も許可する。
 *
 * 投稿後、質問の投稿者へ QaReplyReceivedNotification を送る(投稿者本人の回答では送らない)。
 */
final class StoreAction
{
    // 【解説】コンストラクタの引数に書くと、Laravel が NotificationService を自動で作って渡してくれる(DI)。
    public function __construct(private readonly NotificationService $notifications) {}

    /**
     * @param array{body: string} $validated QaReply/StoreRequest::rules() で検証済
     */
    public function __invoke(User $author, QaThread $thread, array $validated): QaReply
    {
        return DB::transaction(function () use ($author, $thread, $validated) {
            $reply = $thread->replies()->create([
                'user_id' => $author->id,
                'body' => $validated['body'],
            ]);

            // 【解説】自分の質問に自分で回答した場合は通知しない(!== は「同じではない」)。
            if ($thread->user_id !== $author->id) {
                // 【解説】質問の投稿者($thread->user)に通知する。実際の送信はトランザクション確定後(NotificationService)。
                $this->notifications->send($thread->user, new QaReplyReceivedNotification($reply));
            }

            return $reply;
        });
    }
}
