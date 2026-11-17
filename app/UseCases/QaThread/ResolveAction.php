<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Enums\QaThreadStatus;
use App\Exceptions\QaBoard\QaThreadInvalidTransitionException;
use App\Models\QaThread;
use Illuminate\Support\Facades\DB;

/**
 * 投稿者による解決済マークのユースケース。Open → Resolved の遷移のみ許可し、resolved_at を記録する。
 */
final class ResolveAction
{
    /**
     * @throws QaThreadInvalidTransitionException
     */
    public function __invoke(QaThread $thread): QaThread
    {
        if ($thread->status !== QaThreadStatus::Open) {
            throw QaThreadInvalidTransitionException::from($thread->status, QaThreadStatus::Resolved);
        }

        return DB::transaction(function () use ($thread) {
            $thread->update([
                'status' => QaThreadStatus::Resolved->value,
                'resolved_at' => now(),
            ]);

            return $thread;
        });
    }
}
