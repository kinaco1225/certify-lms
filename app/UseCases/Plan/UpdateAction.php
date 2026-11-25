<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * プランの基本情報を更新するユースケース。status は本 Action では変更せず、状態遷移用 Action に分離する。
 *
 * 受講期間 / 初期付与面談回数を変えても、すでにこのプランを受講中のユーザー(User.plan_expires_at / max_meetings)は
 * 変更しない。これらは招待・延長の時点でプランから複写された値のため。
 */
final class UpdateAction
{
    /**
     * @param array{name: string, description?: ?string, duration_days: int, default_meeting_quota: int, sort_order?: ?int} $validated Plan/UpdateRequest で検証済
     */
    public function __invoke(Plan $plan, User $admin, array $validated): Plan
    {
        // 【解説】use (...) = 外側の変数を無名関数の中で使えるように持ち込む指定。
        return DB::transaction(function () use ($plan, $admin, $validated) {
            // 【解説】'status' は書いていないので、編集フォームから状態が変わることはない。
            $plan->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'duration_days' => $validated['duration_days'],
                'default_meeting_quota' => $validated['default_meeting_quota'],
                'sort_order' => $validated['sort_order'] ?? 0,
                // 【解説】最終更新者を、今操作している管理者に書き換える。
                'updated_by_user_id' => $admin->id,
            ]);

            // 【解説】fresh() = DB から読み直した最新のモデルを返す。
            return $plan->fresh();
        });
    }
}
