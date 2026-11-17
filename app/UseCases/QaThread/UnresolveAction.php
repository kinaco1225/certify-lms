<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Enums\QaThreadStatus;
use App\Exceptions\QaBoard\QaThreadInvalidTransitionException;
use App\Models\QaThread;
use Illuminate\Support\Facades\DB;

/**
 * 投稿者による未解決への差し戻しユースケース。Resolved → Open の遷移のみ許可し、resolved_at をクリアする。
 */
final class UnresolveAction
{
    /**
     * @throws QaThreadInvalidTransitionException
     */
    public function __invoke(QaThread $thread): QaThread
    {
        if ($thread->status !== QaThreadStatus::Resolved) {
            throw QaThreadInvalidTransitionException::from($thread->status, QaThreadStatus::Open);
        }

        return DB::transaction(function () use ($thread) {
            $thread->update([
                'status' => QaThreadStatus::Open->value,
                'resolved_at' => null,
            ]);

            return $thread;
        });
    }
}
