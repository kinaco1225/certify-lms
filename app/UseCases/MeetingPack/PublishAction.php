<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Exceptions\MeetingQuota\MeetingPackInvalidTransitionException;
use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 面談パックを公開(draft → published)するユースケース。公開中のパックのみ受講生の購入画面に並ぶ。
 * 下書き以外の状態からの呼出は MeetingPackInvalidTransitionException(409)。
 */
// 【解説】状態遷移の Action は 3 つとも同じ形: ①今の状態を確認 → ②違えば 409 → ③新しい状態に更新。
final class PublishAction
{
    /**
     * @throws MeetingPackInvalidTransitionException 下書き以外からの呼出
     */
    public function __invoke(MeetingPack $pack, User $admin): MeetingPack
    {
        // 【解説】① 公開できるのは「下書き」だけ。!== は「同じではない」。
        // 【解説】画面ではボタンを出し分けているが、別タブで先に公開した場合などに備えてここでも確認する。
        if ($pack->status !== MeetingPackStatus::Draft) {
            // 【解説】② static メソッドで例外を作って投げる。メッセージは例外クラス側で決まっている。
            throw MeetingPackInvalidTransitionException::forPublish();
        }

        // 【解説】③ 状態を「公開中」に更新し、誰が操作したかを記録する。
        return DB::transaction(function () use ($pack, $admin) {
            $pack->update([
                'status' => MeetingPackStatus::Published->value,
                'updated_by_user_id' => $admin->id,
            ]);

            return $pack->fresh();
        });
    }
}
