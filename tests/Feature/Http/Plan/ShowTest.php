<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Models\Plan;
use App\Models\User;
use App\Models\UserPlanLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `GET /admin/plans/{plan}`: 基本情報 / 受講者一覧 / メタ情報と、状態・参照による操作ボタンの出し分け。
 */
class ShowTest extends TestCase
{
    use RefreshDatabase;

    // 【解説】削除ボタンのフォームにだけある確認文。削除の URL は詳細の URL と同じなので、この文言で有無を判定する。
    private const DELETE_CONFIRM = 'このプランを削除しますか？';

    public function test_shows_basic_info_users_and_meta(): void
    {
        $admin = User::factory()->admin()->inProgress()->create(['name' => '作成した管理者']);
        $plan = Plan::factory()->published()->create([
            'name' => '3 ヶ月プラン',
            'duration_days' => 90,
            'default_meeting_quota' => 12,
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);
        User::factory()->student()->inProgress()->withPlan($plan)->create(['name' => '受講生ハナコ', 'email' => 'hanako@example.test']);
        // 【解説】退会してソフトデリートされた受講者は、一覧に表示されない。
        User::factory()->student()->withdrawn()->withPlan($plan)->create(['name' => '退会したタロウ'])->delete();

        $response = $this->actingAs($admin)->get(route('admin.plans.show', $plan));

        $response->assertOk()
            ->assertSee('3 ヶ月プラン')
            ->assertSee('作成した管理者')
            ->assertSee('受講生ハナコ')
            ->assertSee('hanako@example.test')
            ->assertDontSee('退会したタロウ');
        $this->assertCount(1, $response->viewData('plan')->users);
    }

    public function test_shows_empty_message_without_users(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $plan = Plan::factory()->draft()->create();

        $this->actingAs($admin)->get(route('admin.plans.show', $plan))
            ->assertOk()
            ->assertSee('このプランを受講中のユーザーはまだいません。');
    }

    public function test_unreferenced_draft_shows_publish_and_delete(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $plan = Plan::factory()->draft()->create();

        $this->actingAs($admin)->get(route('admin.plans.show', $plan))
            ->assertOk()
            ->assertSee(route('admin.plans.edit', $plan))
            ->assertSee(route('admin.plans.publish', $plan))
            ->assertDontSee(route('admin.plans.archive', $plan))
            ->assertDontSee(route('admin.plans.unarchive', $plan))
            ->assertSee(self::DELETE_CONFIRM);
    }

    public function test_published_shows_archive_but_not_delete(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $plan = Plan::factory()->published()->create();

        $this->actingAs($admin)->get(route('admin.plans.show', $plan))
            ->assertOk()
            ->assertSee(route('admin.plans.archive', $plan))
            ->assertDontSee(route('admin.plans.publish', $plan))
            ->assertDontSee(route('admin.plans.unarchive', $plan))
            ->assertDontSee(self::DELETE_CONFIRM);
    }

    public function test_archived_shows_unarchive_but_not_delete(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $plan = Plan::factory()->archived()->create();

        // 【解説】面談パックと違い、アーカイブ済みのプランは削除ボタンが出ない(下書きのみ削除可)。
        $this->actingAs($admin)->get(route('admin.plans.show', $plan))
            ->assertOk()
            ->assertSee(route('admin.plans.unarchive', $plan))
            ->assertDontSee(route('admin.plans.publish', $plan))
            ->assertDontSee(route('admin.plans.archive', $plan))
            ->assertDontSee(self::DELETE_CONFIRM);
    }

    public function test_referenced_draft_hides_delete(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();

        // 【解説】下書きでも、受講者がいる / プラン履歴だけある 場合は削除ボタンが出ない。
        $withUser = Plan::factory()->draft()->create();
        User::factory()->student()->inProgress()->withPlan($withUser)->create();
        $withLog = Plan::factory()->draft()->create();
        UserPlanLog::factory()->create(['plan_id' => $withLog->id]);

        foreach ([$withUser, $withLog] as $plan) {
            $this->actingAs($admin)->get(route('admin.plans.show', $plan))
                ->assertOk()
                ->assertSee(route('admin.plans.publish', $plan))
                ->assertDontSee(self::DELETE_CONFIRM);
        }
    }

    public function test_unknown_id_returns_404(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();

        $this->actingAs($admin)
            ->get(route('admin.plans.show', '01AAAAAAAAAAAAAAAAAAAAAAAA'))
            ->assertNotFound();
    }
}
