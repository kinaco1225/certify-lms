<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Models\Plan;
use App\Models\User;
use App\Models\UserPlanLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `DELETE /admin/plans/{plan}`: 受講者・プラン履歴の参照がない下書きだけ物理削除できる。
 * それ以外は PlanPolicy::delete が false → 403(画面には削除ボタンが出ない)。
 */
class DestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_unreferenced_draft_can_be_deleted(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $plan = Plan::factory()->draft()->create();

        $this->actingAs($admin)
            ->delete(route('admin.plans.destroy', $plan))
            ->assertRedirect(route('admin.plans.index'))
            ->assertSessionHas('success', 'プランを削除しました。');

        $this->assertModelMissing($plan);
    }

    public function test_non_draft_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();

        foreach ([Plan::factory()->published()->create(), Plan::factory()->archived()->create()] as $plan) {
            $this->actingAs($admin)->delete(route('admin.plans.destroy', $plan))->assertForbidden();
            $this->assertModelExists($plan);
        }
    }

    public function test_referenced_draft_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();

        // 【解説】受講者あり / ソフトデリートされた受講者あり / プラン履歴だけあり の 3 パターン。
        $withUser = Plan::factory()->draft()->create();
        User::factory()->student()->inProgress()->withPlan($withUser)->create();

        $withTrashedUser = Plan::factory()->draft()->create();
        User::factory()->student()->withdrawn()->withPlan($withTrashedUser)->create()->delete();

        $withLog = Plan::factory()->draft()->create();
        UserPlanLog::factory()->create(['plan_id' => $withLog->id]);

        foreach ([$withUser, $withTrashedUser, $withLog] as $plan) {
            // 【解説】もし判定が漏れていたら、外部キー制約で DB エラー(500)になる。403 で止まっていることを確認する。
            $this->actingAs($admin)->delete(route('admin.plans.destroy', $plan))->assertForbidden();
            $this->assertModelExists($plan);
        }
    }
}
