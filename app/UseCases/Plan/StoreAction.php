<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Enums\PlanStatus;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * プランを新規作成するユースケース。初期状態は下書き(draft)で、admin を作成者 / 更新者に記録する。
 */
final class StoreAction
{
    /**
     * @param array{name: string, description?: ?string, duration_days: int, default_meeting_quota: int, sort_order?: ?int} $validated Plan/StoreRequest で検証済
     */
    // 【解説】$admin = 操作している管理者、$validated = FormRequest でチェック済みの入力値。
    public function __invoke(User $admin, array $validated): Plan
    {
        // 【解説】DB::transaction = 途中でエラーが起きたら DB の変更をすべて取り消す。
        return DB::transaction(fn () => Plan::create([
            'name' => $validated['name'],
            // 【解説】?? = 左側が無い / null なら右側を使う。説明が未入力なら null を保存する。
            'description' => $validated['description'] ?? null,
            'duration_days' => $validated['duration_days'],
            'default_meeting_quota' => $validated['default_meeting_quota'],
            // 空欄送信は null になるが、sort_order カラムは NOT NULL のため 0 に揃える
            'sort_order' => $validated['sort_order'] ?? 0,
            // 【解説】状態は入力値を使わず「下書き」に固定する(チケットの「初期状態は下書き」)。
            'status' => PlanStatus::Draft->value,
            // 【解説】作成者・更新者はログイン中の管理者の id(入力値からは取らない)。
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]));
    }
}
