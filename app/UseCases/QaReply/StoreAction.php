<?php

declare(strict_types=1);

namespace App\UseCases\QaReply;

use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 受講生 / コーチによる回答投稿ユースケース。解決済スレッドへの補足回答も許可する。
 */
final class StoreAction
{
    /**
     * @param array{body: string} $validated QaReply/StoreRequest::rules() で検証済
     */
    public function __invoke(User $author, QaThread $thread, array $validated): QaReply
    {
        return DB::transaction(fn () => $thread->replies()->create([
            'user_id' => $author->id,
            'body' => $validated['body'],
        ]));
    }
}
