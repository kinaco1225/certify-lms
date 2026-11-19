<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Exceptions\MeetingQuota\MeetingPackInvalidTransitionException;
use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * アーカイブ済みの面談パックを下書きに戻す(archived → draft)ユースケース。再販売するには改めて公開する。
 * アーカイブ以外の状態からの呼出は MeetingPackInvalidTransitionException(409)。
 */
// 【解説】PublishAction と同じ形。アーカイブ → 下書きに戻す。直接「公開中」には戻さない(内容を確認してから公開する流れ)。
final class UnarchiveAction
{
    /**
     * @throws MeetingPackInvalidTransitionException アーカイブ以外からの呼出
     */
    public function __invoke(MeetingPack $pack, User $admin): MeetingPack
    {
        // 【解説】① 下書きに戻せるのは「アーカイブ」だけ。
        if ($pack->status !== MeetingPackStatus::Archived) {
            throw MeetingPackInvalidTransitionException::forUnarchive();
        }

        // 【解説】③ 状態を「下書き」に更新する。
        return DB::transaction(function () use ($pack, $admin) {
            $pack->update([
                'status' => MeetingPackStatus::Draft->value,
                'updated_by_user_id' => $admin->id,
            ]);

            return $pack->fresh();
        });
    }
}
