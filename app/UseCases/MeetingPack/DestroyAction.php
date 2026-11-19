<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Exceptions\MeetingQuota\MeetingPackNotDeletableException;
use App\Models\MeetingPack;
use Illuminate\Support\Facades\DB;

/**
 * 面談パックを物理削除するユースケース。公開中の面談パックは購入履歴の整合性を守るため削除できない
 * (下書き / アーカイブのみ削除可)。
 */
final class DestroyAction
{
    /**
     * @throws MeetingPackNotDeletableException 公開中の面談パック
     */
    // 【解説】上の @throws は「このメソッドは場合によってこの例外を投げます」という説明。
    // 【解説】戻り値の型 void = 何も返さない。
    public function __invoke(MeetingPack $pack): void
    {
        // 【解説】状態が「公開中」なら削除させない。$pack->status はモデルの $casts により Enum として取り出される。
        // 【解説】Enum 同士なので === で比較できる。
        if ($pack->status === MeetingPackStatus::Published) {
            // 【解説】throw した時点で処理はここで止まり、下の delete() は実行されない → 409 エラーになる。
            throw new MeetingPackNotDeletableException;
        }

        // 【解説】delete() = DB から行を削除する。このモデルは SoftDeletes を使っていないので物理削除(完全に消える)。
        DB::transaction(fn () => $pack->delete());
    }
}
