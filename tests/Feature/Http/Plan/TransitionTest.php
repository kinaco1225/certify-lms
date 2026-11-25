<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Enums\PlanStatus;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 状態遷移 `POST /admin/plans/{plan}/{publish|archive|unarchive}`。
 * 下書き → 公開中 → アーカイブ → 下書き の正しい順序だけ許可し、それ以外は 409(元の画面にエラー表示)。
 */
class TransitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_lifecycle_draft_publish_archive_unarchive(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $plan = Plan::factory()->draft()->create();

        // 【解説】[ルート名, 遷移後の状態, フラッシュメッセージ] の順に 3 回操作する。
        $steps = [
            ['admin.plans.publish', PlanStatus::Published, 'プランを公開しました。'],
            ['admin.plans.archive', PlanStatus::Archived, 'プランをアーカイブしました。'],
            ['admin.plans.unarchive', PlanStatus::Draft, 'プランを下書きに戻しました。'],
        ];

        foreach ($steps as [$route, $expected, $message]) {
            $this->actingAs($admin)
                ->post(route($route, $plan))
                ->assertRedirect(route('admin.plans.show', $plan))
                ->assertSessionHas('success', $message);

            $fresh = $plan->fresh();
            $this->assertSame($expected, $fresh->status, $route);
            $this->assertSame($admin->id, $fresh->updated_by_user_id);
        }
    }

    public function test_archive_keeps_users_plan_reference(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $plan = Plan::factory()->published()->create();
        $student = User::factory()->student()->inProgress()->withPlan($plan)->create();

        $this->actingAs($admin)->post(route('admin.plans.archive', $plan))->assertSessionHas('success');

        // 【解説】チケットの「アーカイブ後も受講中ユーザーの参照は維持される」。plan_id は変わらない。
        $this->assertSame($plan->id, $student->fresh()->plan_id);
    }

    public function test_invalid_transitions_are_rejected(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();

        // 【解説】[ルート名, 開始時の状態, 表示されるエラー] — 正しい順序以外の 6 通りすべて。
        $cases = [
            ['admin.plans.publish', 'published', '下書きのプランのみ公開できます。'],
            ['admin.plans.publish', 'archived', '下書きのプランのみ公開できます。'],
            ['admin.plans.archive', 'draft', '公開中のプランのみアーカイブできます。'],
            ['admin.plans.archive', 'archived', '公開中のプランのみアーカイブできます。'],
            ['admin.plans.unarchive', 'draft', 'アーカイブ済みのプランのみ下書きに戻せます。'],
            ['admin.plans.unarchive', 'published', 'アーカイブ済みのプランのみ下書きに戻せます。'],
        ];

        foreach ($cases as [$route, $from, $error]) {
            $plan = Plan::factory()->create(['status' => $from]);

            $this->actingAs($admin)
                ->from(route('admin.plans.show', $plan))
                ->post(route($route, $plan))
                ->assertRedirect(route('admin.plans.show', $plan))
                ->assertSessionHas('error', $error);

            $this->assertSame($from, $plan->fresh()->status->value, "{$route} from {$from}");
        }
    }
}
