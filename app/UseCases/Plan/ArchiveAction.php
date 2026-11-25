<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Enums\PlanStatus;
use App\Exceptions\Plan\PlanInvalidTransitionException;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * プランをアーカイブ(published → archived)するユースケース。招待 / プラン延長の選択肢から外す(受講中ユーザーの plan_id は変更しない)。
 * 公開中以外の状態からの呼出は PlanInvalidTransitionException(409)。
 */
// 【解説】状態遷移の Action は 3 つとも同じ形: ①今の状態を確認 → ②違えば 409 → ③新しい状態に更新。
final class ArchiveAction
{
    /**
     * @throws PlanInvalidTransitionException 公開中以外からの呼出
     */
    public function __invoke(Plan $plan, User $admin): Plan
    {
        // 【解説】① 公開中のプランだけが対象。!== は「同じではない」。
        if ($plan->status !== PlanStatus::Published) {
            // 【解説】② static メソッドで例外を作って投げる。ここで処理が止まり、下の更新は実行されない。
            throw PlanInvalidTransitionException::forArchive();
        }

        // 【解説】③ 状態を更新し、誰が操作したかを記録する。
        return DB::transaction(function () use ($plan, $admin) {
            $plan->update([
                'status' => PlanStatus::Archived->value,
                'updated_by_user_id' => $admin->id,
            ]);

            return $plan->fresh();
        });
    }
}
