<?php

declare(strict_types=1);

namespace App\UseCases\QaReply;

use App\Models\QaReply;
use Illuminate\Support\Facades\DB;

/**
 * 回答の物理削除ユースケース。投稿者本人による削除と、管理者のモデレーション削除で共用する。
 */
final class DestroyAction
{
    public function __invoke(QaReply $reply): void
    {
        DB::transaction(fn () => $reply->delete());
    }
}
