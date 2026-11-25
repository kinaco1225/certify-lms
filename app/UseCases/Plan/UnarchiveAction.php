<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Enums\PlanStatus;
use App\Exceptions\Plan\PlanInvalidTransitionException;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * アーカイブ済みのプランを下書きに戻す(archived → draft)ユースケース。再提供するには改めて公開する。
 * アーカイブ以外の状態からの呼出は PlanInvalidTransitionException(409)。
 */
// 【解説】状態遷移の Action は 3 つとも同じ形: ①今の状態を確認 → ②違えば 409 → ③新しい状態に更新。
final class UnarchiveAction
{
    /**
     * @throws PlanInvalidTransitionException アーカイブ以外からの呼出
     */
    public function __invoke(Plan $plan, User $admin): Plan
    {
        // 【解説】① アーカイブのプランだけが対象。!== は「同じではない」。
        if ($plan->status !== PlanStatus::Archived) {
            // 【解説】② static メソッドで例外を作って投げる。ここで処理が止まり、下の更新は実行されない。
            throw PlanInvalidTransitionException::forUnarchive();
        }

        // 【解説】③ 状態を更新し、誰が操作したかを記録する。
        return DB::transaction(function () use ($plan, $admin) {
            $plan->update([
                'status' => PlanStatus::Draft->value,
                'updated_by_user_id' => $admin->id,
            ]);

            return $plan->fresh();
        });
    }
}
