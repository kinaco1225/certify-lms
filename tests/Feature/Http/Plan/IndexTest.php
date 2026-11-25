<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Models\Plan;
use App\Models\User;
use App\UseCases\Plan\IndexAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `GET /admin/plans`: キーワード検索 / 状態フィルタ / 並び順 / ページネーション / 受講者数 / 不正な絞り込み値。
 */
class IndexTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    // 【解説】setUp() = 各テストの前に毎回実行される準備。全テストで使う管理者を作る。
    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->inProgress()->create();
    }

    public function test_admin_sees_list_with_all_statuses(): void
    {
        Plan::factory()->draft()->create(['name' => '下書きプラン']);
        Plan::factory()->published()->create(['name' => '公開プラン']);
        Plan::factory()->archived()->create(['name' => '終売プラン']);

        $response = $this->actingAs($this->admin)->get(route('admin.plans.index'));

        $response->assertOk()
            ->assertSee('プラン管理')
            ->assertSee('下書きプラン')
            ->assertSee('公開プラン')
            ->assertSee('終売プラン');
        $this->assertSame(3, $response->viewData('plans')->total());
    }

    public function test_shows_user_count_per_plan_excluding_soft_deleted(): void
    {
        $plan = Plan::factory()->published()->create();
        // 【解説】受講中 2 名 + 卒業 1 名 = 3 名。退会してソフトデリートされた 1 名は数えない。
        User::factory()->count(2)->student()->inProgress()->withPlan($plan)->create();
        User::factory()->student()->graduated()->withPlan($plan)->create();
        User::factory()->student()->withdrawn()->withPlan($plan)->create()->delete();
        Plan::factory()->published()->create();

        $response = $this->actingAs($this->admin)->get(route('admin.plans.index'));

        // 【解説】firstWhere('id', ...) = 一覧の中から指定した id のプランを探す。users_count は withCount で付いた値。
        $plans = $response->viewData('plans');
        $this->assertSame(3, $plans->firstWhere('id', $plan->id)->users_count);
        $response->assertSee('3 名');
    }

    public function test_user_count_does_not_cause_n_plus_1(): void
    {
        Plan::factory()->count(3)->create()->each(fn (Plan $p) => User::factory()->student()->withPlan($p)->create());
        $baseline = $this->countQueries(fn () => $this->actingAs($this->admin)->get(route('admin.plans.index'))->assertOk());

        Plan::factory()->count(10)->create()->each(fn (Plan $p) => User::factory()->student()->withPlan($p)->create());
        $scaled = $this->countQueries(fn () => $this->actingAs($this->admin)->get(route('admin.plans.index'))->assertOk());

        // 【解説】プランが 3 件 → 13 件に増えても、発行される SQL の数がほとんど増えないこと(withCount が効いている)。
        $this->assertLessThanOrEqual($baseline + 2, $scaled, "一覧で N+1 (基準 {$baseline} → 増加後 {$scaled})");
    }

    public function test_keyword_filters_by_name(): void
    {
        $target = Plan::factory()->create(['name' => '3 ヶ月プラン']);
        Plan::factory()->create(['name' => '6 ヶ月プラン']);

        $response = $this->actingAs($this->admin)->get(route('admin.plans.index', ['keyword' => '3 ヶ月']));

        $this->assertSame([$target->id], $response->viewData('plans')->pluck('id')->all());
        $this->assertSame('3 ヶ月', $response->viewData('keyword'));
    }

    public function test_status_filters_by_selected_status(): void
    {
        Plan::factory()->draft()->create();
        Plan::factory()->published()->create();
        Plan::factory()->archived()->create();

        foreach (['draft', 'published', 'archived'] as $status) {
            $response = $this->actingAs($this->admin)->get(route('admin.plans.index', ['status' => $status]));

            // 【解説】どの状態を選んでも、その状態のプランだけ(1 件)になる。
            $plans = $response->viewData('plans');
            $this->assertCount(1, $plans, "status={$status}");
            $this->assertSame($status, $plans->first()->status->value);
            $this->assertSame($status, $response->viewData('status'));
        }
    }

    public function test_sorted_by_sort_order_ascending(): void
    {
        $third = Plan::factory()->create(['sort_order' => 30]);
        $first = Plan::factory()->create(['sort_order' => 10]);
        $second = Plan::factory()->create(['sort_order' => 20]);

        $response = $this->actingAs($this->admin)->get(route('admin.plans.index'));

        $this->assertSame([$first->id, $second->id, $third->id], $response->viewData('plans')->pluck('id')->all());
    }

    public function test_paginates_by_20(): void
    {
        Plan::factory()->count(IndexAction::PER_PAGE + 1)->create();

        $page1 = $this->actingAs($this->admin)->get(route('admin.plans.index'))->viewData('plans');
        $page2 = $this->actingAs($this->admin)->get(route('admin.plans.index', ['page' => 2]))->viewData('plans');

        $this->assertCount(20, $page1);
        $this->assertCount(1, $page2);
        $this->assertSame(21, $page1->total());
    }

    public function test_invalid_status_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->from(route('admin.plans.index'))
            ->get(route('admin.plans.index', ['status' => 'xxx']))
            ->assertRedirect(route('admin.plans.index'))
            ->assertSessionHasErrors(['status' => 'ステータスは「下書き」「公開中」「アーカイブ」から選択してください。']);
    }

    // 【解説】処理の間に発行された SQL の数を数える補助メソッド。DB::listen は SQL が実行されるたびに呼ばれる。
    private function countQueries(\Closure $callback): int
    {
        $count = 0;
        DB::listen(function () use (&$count): void {
            $count++;
        });

        $callback();

        return $count;
    }
}
