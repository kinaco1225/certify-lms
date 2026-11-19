<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Models\MeetingPack;
use App\Models\User;
use App\UseCases\MeetingPack\IndexAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `GET /admin/meeting-packs`: キーワード検索 / 状態フィルタ / 並び順 / ページネーション / 不正な絞り込み値。
 */
class IndexTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    // 【解説】setUp() = 各テストの前に毎回実行される準備処理。全テストで使う管理者をここで作る。
    protected function setUp(): void
    {
        // 【解説】親クラスの準備処理(DB の初期化など)を先に必ず呼ぶ。
        parent::setUp();
        $this->admin = User::factory()->admin()->inProgress()->create();
    }

    public function test_admin_sees_list_with_all_statuses(): void
    {
        MeetingPack::factory()->draft()->create(['name' => '下書きパック']);
        MeetingPack::factory()->published()->create(['name' => '公開パック']);
        MeetingPack::factory()->archived()->create(['name' => '終売パック']);

        $response = $this->actingAs($this->admin)->get(route('admin.meeting-packs.index'));

        // 【解説】assertOk() = 200。assertSee('文字') = 画面の HTML にその文字が含まれる。
        $response->assertOk()
            ->assertSee('面談パック管理')
            ->assertSee('下書きパック')
            ->assertSee('公開パック')
            ->assertSee('終売パック');
        // 【解説】viewData('plans') = Controller が view() に渡した 'plans' の値を取り出す。total() は全件数。
        $this->assertSame(3, $response->viewData('plans')->total());
    }

    public function test_keyword_filters_by_name(): void
    {
        $target = MeetingPack::factory()->create(['name' => '5 回パック']);
        MeetingPack::factory()->create(['name' => '10 回パック']);

        // 【解説】route() の第 2 引数の配列は URL のクエリ(?keyword=5)になる。
        $response = $this->actingAs($this->admin)->get(route('admin.meeting-packs.index', ['keyword' => '5']));

        // 【解説】pluck('id') = 一覧から id だけを取り出す。all() で普通の配列にして、期待値と比べる。
        $this->assertSame([$target->id], $response->viewData('plans')->pluck('id')->all());
        // 【解説】Blade の検索フォームに入力値が戻るよう、$keyword が渡されていることも確認する。
        $this->assertSame('5', $response->viewData('keyword'));
    }

    public function test_status_filters_by_selected_status(): void
    {
        MeetingPack::factory()->draft()->create();
        $published = MeetingPack::factory()->published()->create();
        MeetingPack::factory()->archived()->create();

        foreach (['draft', 'published', 'archived'] as $status) {
            $response = $this->actingAs($this->admin)->get(route('admin.meeting-packs.index', ['status' => $status]));

            // 【解説】どの状態を選んでも、その状態のパックだけ(1 件)になることを確認する。
            // 【解説】(既存の資格一覧には「常に下書きで絞り込む」バグがあるので、同じ間違いがないことをここで保証する)
            $plans = $response->viewData('plans');
            $this->assertCount(1, $plans, "status={$status}");
            $this->assertSame($status, $plans->first()->status->value);
            $this->assertSame($status, $response->viewData('status'));
        }

        $this->assertSame(
            [$published->id],
            $this->actingAs($this->admin)->get(route('admin.meeting-packs.index', ['status' => 'published']))
                ->viewData('plans')->pluck('id')->all(),
        );
    }

    public function test_sorted_by_sort_order_ascending(): void
    {
        $third = MeetingPack::factory()->create(['sort_order' => 30]);
        $first = MeetingPack::factory()->create(['sort_order' => 10]);
        $second = MeetingPack::factory()->create(['sort_order' => 20]);

        $response = $this->actingAs($this->admin)->get(route('admin.meeting-packs.index'));

        $this->assertSame(
            [$first->id, $second->id, $third->id],
            $response->viewData('plans')->pluck('id')->all(),
        );
    }

    public function test_paginates_by_20(): void
    {
        // 【解説】count(21) = 21 件まとめて作る。1 ページ 20 件なので 2 ページ目に 1 件残るはず。
        MeetingPack::factory()->count(IndexAction::PER_PAGE + 1)->create();

        $page1 = $this->actingAs($this->admin)->get(route('admin.meeting-packs.index'))->viewData('plans');
        $page2 = $this->actingAs($this->admin)->get(route('admin.meeting-packs.index', ['page' => 2]))->viewData('plans');

        $this->assertCount(20, $page1);
        $this->assertCount(1, $page2);
        $this->assertSame(21, $page1->total());
    }

    public function test_invalid_status_is_rejected(): void
    {
        // 【解説】from(URL) = 「この画面から来た」ことにする。バリデーション失敗時はこの URL に戻される。
        $this->actingAs($this->admin)
            ->from(route('admin.meeting-packs.index'))
            ->get(route('admin.meeting-packs.index', ['status' => 'xxx']))
            ->assertRedirect(route('admin.meeting-packs.index'))
            // 【解説】assertSessionHasErrors(['項目' => 'メッセージ']) = その項目にそのエラーメッセージが出ていること。
            // 【解説】messages() のキー('status.'.Enum::class)が正しくないと英語のメッセージになり、ここで失敗する。
            ->assertSessionHasErrors(['status' => 'ステータスは「下書き」「公開中」「アーカイブ」から選択してください。']);
    }
}
