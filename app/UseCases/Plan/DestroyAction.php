<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Enums\PlanStatus;
use App\Exceptions\Plan\PlanNotDeletableException;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;

/**
 * プランを物理削除するユースケース。下書き かつ 受講者(ソフトデリート済みを含む)・プラン履歴の参照がない場合のみ削除できる。
 *
 * PlanPolicy::delete でも同じ条件を判定しているが、認可の後に受講者が紐づいた場合などに備えてここでも確認する
 * (確認せずに削除すると、外部キー制約で DB エラー(500)になる)。
 */
final class DestroyAction
{
    /**
     * @throws PlanNotDeletableException 下書き以外 / 参照あり
     */
    public function __invoke(Plan $plan): void
    {
        // 【解説】① 下書き以外(公開中 / アーカイブ)は削除させない。
        if ($plan->status !== PlanStatus::Draft) {
            throw PlanNotDeletableException::notDraft();
        }

        // 【解説】② 受講者またはプラン履歴が紐づいていたら削除させない(判定はモデルの hasReferences() に任せる)。
        if ($plan->hasReferences()) {
            throw PlanNotDeletableException::inUse();
        }

        // 【解説】Plan モデルは SoftDeletes を使っていないので、delete() は物理削除(行ごと消える)。
        DB::transaction(fn () => $plan->delete());
    }
}
