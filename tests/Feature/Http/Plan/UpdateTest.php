<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Enums\PlanStatus;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `GET /admin/plans/{plan}/edit` + `PUT /admin/plans/{plan}`: 基本情報の更新(状態・受講中ユーザーは変えない)。
 */
class UpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_form_shows_current_values(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $plan = Plan::factory()->create(['name' => '編集前のプラン']);

        $this->actingAs($admin)->get(route('admin.plans.edit', $plan))
            ->assertOk()
            ->assertSee('編集前のプラン')
            ->assertSee(route('admin.plans.update', $plan));
    }

    public function test_admin_updates_basic_info_with_put(): void
    {
        $creator = User::factory()->admin()->inProgress()->create();
        $editor = User::factory()->admin()->inProgress()->create();
        $plan = Plan::factory()->published()->create([
            'duration_days' => 30,
            'default_meeting_quota' => 4,
            'created_by_user_id' => $creator->id,
            'updated_by_user_id' => $creator->id,
        ]);
        // 【解説】受講中のユーザー。プランの受講期間を変えても、このユーザーの期限・面談回数は変わらないことを確認する。
        $student = User::factory()->student()->inProgress()->withPlan($plan)->create();
        $expiresBefore = $student->plan_expires_at->toDateTimeString();

        // 【解説】チケットどおり PUT で送る(編集フォームも @method('PUT'))。
        $this->actingAs($editor)
            ->put(route('admin.plans.update', $plan), [
                'name' => '新しい名前',
                'description' => '',
                'duration_days' => 90,
                'default_meeting_quota' => 12,
                'sort_order' => '',
                'status' => 'archived',
            ])
            ->assertRedirect(route('admin.plans.show', $plan))
            ->assertSessionHas('success', 'プランを更新しました。');

        $fresh = $plan->fresh();
        $this->assertSame('新しい名前', $fresh->name);
        $this->assertNull($fresh->description);
        $this->assertSame(90, $fresh->duration_days);
        $this->assertSame(12, $fresh->default_meeting_quota);
        $this->assertSame(0, $fresh->sort_order);
        // 【解説】状態は公開中のまま。
        $this->assertSame(PlanStatus::Published, $fresh->status);
        $this->assertSame($creator->id, $fresh->created_by_user_id);
        $this->assertSame($editor->id, $fresh->updated_by_user_id);

        // 【解説】受講中ユーザーの期限・面談回数はそのまま。
        $student->refresh();
        $this->assertSame($expiresBefore, $student->plan_expires_at->toDateTimeString());
        $this->assertSame(4, $student->max_meetings);
    }

    public function test_validation(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $plan = Plan::factory()->create(['name' => '元の名前']);

        $this->actingAs($admin)
            ->put(route('admin.plans.update', $plan), ['name' => '', 'duration_days' => 3651, 'default_meeting_quota' => -1])
            ->assertSessionHasErrors([
                'name' => 'プラン名を入力してください。',
                'duration_days' => '受講期間は3650日以下で入力してください。',
                'default_meeting_quota' => '初期付与面談回数は0回以上で入力してください。',
            ]);

        $this->assertSame('元の名前', $plan->fresh()->name);
    }
}
