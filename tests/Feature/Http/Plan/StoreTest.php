<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Enums\PlanStatus;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `GET /admin/plans/create` + `POST /admin/plans`: 下書きで作成 / 作成者の記録 / 入力検証。
 */
class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_form_is_shown(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();

        $this->actingAs($admin)->get(route('admin.plans.create'))->assertOk();
    }

    public function test_admin_creates_plan_as_draft(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();

        // 【解説】status を送っても無視されることを確認するため、わざと published を送る。
        $response = $this->actingAs($admin)->post(route('admin.plans.store'), $this->payload(['status' => 'published']));

        // 【解説】sole() = ちょうど 1 件を取得(0 件や 2 件以上ならテスト失敗)。
        $plan = Plan::sole();
        $response->assertRedirect(route('admin.plans.show', $plan))
            ->assertSessionHas('success', 'プランを作成しました。');

        $this->assertSame('3 ヶ月プラン 12 回', $plan->name);
        $this->assertSame(90, $plan->duration_days);
        $this->assertSame(12, $plan->default_meeting_quota);
        $this->assertSame(20, $plan->sort_order);
        $this->assertSame(PlanStatus::Draft, $plan->status);
        $this->assertSame($admin->id, $plan->created_by_user_id);
        $this->assertSame($admin->id, $plan->updated_by_user_id);
    }

    public function test_optional_fields_can_be_empty(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();

        $this->actingAs($admin)
            ->post(route('admin.plans.store'), $this->payload(['description' => '', 'sort_order' => '']))
            ->assertSessionHasNoErrors();

        $plan = Plan::sole();
        $this->assertNull($plan->description);
        // 【解説】sort_order は DB が NOT NULL なので、空欄は StoreAction で 0 になる。
        $this->assertSame(0, $plan->sort_order);
    }

    public function test_required_fields(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();

        $this->actingAs($admin)
            ->post(route('admin.plans.store'), ['name' => '', 'duration_days' => '', 'default_meeting_quota' => ''])
            ->assertSessionHasErrors([
                'name' => 'プラン名を入力してください。',
                'duration_days' => '受講期間を入力してください。',
                'default_meeting_quota' => '初期付与面談回数を入力してください。',
            ]);

        $this->assertDatabaseCount('plans', 0);
    }

    public function test_values_out_of_range(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();

        // 【解説】上限 + 1 の値。
        $this->actingAs($admin)
            ->post(route('admin.plans.store'), [
                'name' => str_repeat('あ', 101),
                'description' => str_repeat('あ', 2001),
                'duration_days' => 3651,
                'default_meeting_quota' => 1001,
                'sort_order' => 65536,
            ])
            ->assertSessionHasErrors([
                'name' => 'プラン名は100文字以内で入力してください。',
                'description' => '説明は2000文字以内で入力してください。',
                'duration_days' => '受講期間は3650日以下で入力してください。',
                'default_meeting_quota' => '初期付与面談回数は1000回以下で入力してください。',
                'sort_order' => '並び順は65535以下で入力してください。',
            ]);

        // 【解説】下限 - 1 の値。
        $this->actingAs($admin)
            ->post(route('admin.plans.store'), $this->payload(['duration_days' => 0, 'default_meeting_quota' => -1, 'sort_order' => -1]))
            ->assertSessionHasErrors([
                'duration_days' => '受講期間は1日以上で入力してください。',
                'default_meeting_quota' => '初期付与面談回数は0回以上で入力してください。',
                'sort_order' => '並び順は0以上で入力してください。',
            ]);

        $this->assertDatabaseCount('plans', 0);
    }

    public function test_non_integer_values_are_rejected(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();

        $this->actingAs($admin)
            ->post(route('admin.plans.store'), $this->payload(['duration_days' => '1.5', 'default_meeting_quota' => 'abc', 'sort_order' => 'x']))
            ->assertSessionHasErrors([
                'duration_days' => '受講期間は整数で入力してください。',
                'default_meeting_quota' => '初期付与面談回数は整数で入力してください。',
                'sort_order' => '並び順は整数で入力してください。',
            ]);
    }

    public function test_boundary_values_are_accepted(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();

        // 【解説】ちょうど上限(受講期間 3650 日など)はエラーにならない。
        $this->actingAs($admin)
            ->post(route('admin.plans.store'), [
                'name' => str_repeat('あ', 100),
                'description' => str_repeat('あ', 2000),
                'duration_days' => 3650,
                'default_meeting_quota' => 1000,
                'sort_order' => 65535,
            ])
            ->assertSessionHasNoErrors();

        // 【解説】ちょうど下限(受講期間 1 日 / 面談 0 回)もエラーにならない。
        $this->actingAs($admin)
            ->post(route('admin.plans.store'), $this->payload(['duration_days' => 1, 'default_meeting_quota' => 0, 'sort_order' => 0]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('plans', 2);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    // 【解説】正しい入力値のひな形。$overrides で渡した項目だけ上書きする(配列の + は左側を優先)。
    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'name' => '3 ヶ月プラン 12 回',
            'description' => '標準的な学習期間。',
            'duration_days' => 90,
            'default_meeting_quota' => 12,
            'sort_order' => 20,
        ];
    }
}
