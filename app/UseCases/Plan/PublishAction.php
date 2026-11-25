<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Enums\PlanStatus;
use App\Exceptions\Plan\PlanInvalidTransitionException;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * プランを公開(draft → published)するユースケース。公開中のプランのみ招待 / プラン延長の選択肢に並ぶ。
 * 下書き以外の状態からの呼出は PlanInvalidTransitionException(409)。
 */
// 【解説】状態遷移の Action は 3 つとも同じ形: ①今の状態を確認 → ②違えば 409 → ③新しい状態に更新。
final class PublishAction
{
    /**
     * @throws PlanInvalidTransitionException 下書き以外からの呼出
     */
    public function __invoke(Plan $plan, User $admin): Plan
    {
        // 【解説】① 下書きのプランだけが対象。!== は「同じではない」。
        if ($plan->status !== PlanStatus::Draft) {
            // 【解説】② static メソッドで例外を作って投げる。ここで処理が止まり、下の更新は実行されない。
            throw PlanInvalidTransitionException::forPublish();
        }

        // 【解説】③ 状態を更新し、誰が操作したかを記録する。
        return DB::transaction(function () use ($plan, $admin) {
            $plan->update([
                'status' => PlanStatus::Published->value,
                'updated_by_user_id' => $admin->id,
            ]);

            return $plan->fresh();
        });
    }
}
