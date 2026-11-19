<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `GET /admin/meeting-packs/{plan}`: 基本情報 / メタ情報の表示と、状態ごとの操作ボタンの出し分け。
 */
class ShowTest extends TestCase
{
    use RefreshDatabase;

    // 【解説】削除ボタンのフォームにだけある確認文。削除の URL は詳細の URL と同じなので、URL ではなくこの文言で有無を判定する。
    private const DELETE_CONFIRM = 'この面談パックを削除しますか？';

    public function test_shows_basic_and_meta_info(): void
    {
        $admin = User::factory()->admin()->inProgress()->create(['name' => '作成した管理者']);
        $pack = MeetingPack::factory()->create([
            'name' => '5 回パック',
            'meeting_count' => 5,
            'price' => 12000,
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)->get(route('admin.meeting-packs.show', $pack))
            ->assertOk()
            ->assertSee('5 回パック')
            // 【解説】画面では number_format で「12,000」と表示される。
            ->assertSee('¥12,000')
            ->assertSee('作成した管理者')
            ->assertSee('この SKU の購入はまだありません。');
    }

    public function test_draft_shows_edit_publish_and_delete(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $pack = MeetingPack::factory()->draft()->create();

        $this->actingAs($admin)->get(route('admin.meeting-packs.show', $pack))
            ->assertOk()
            ->assertSee(route('admin.meeting-packs.edit', $pack))
            ->assertSee(route('admin.meeting-packs.publish', $pack))
            // 【解説】assertDontSee = 画面に含まれていないこと。下書きにはアーカイブ / 下書きへ戻すボタンが出ない。
            ->assertDontSee(route('admin.meeting-packs.archive', $pack))
            ->assertDontSee(route('admin.meeting-packs.unarchive', $pack))
            ->assertSee(self::DELETE_CONFIRM);
    }

    public function test_published_shows_archive_but_not_delete(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $pack = MeetingPack::factory()->published()->create();

        $this->actingAs($admin)->get(route('admin.meeting-packs.show', $pack))
            ->assertOk()
            ->assertSee(route('admin.meeting-packs.archive', $pack))
            ->assertDontSee(route('admin.meeting-packs.publish', $pack))
            ->assertDontSee(route('admin.meeting-packs.unarchive', $pack))
            // 【解説】公開中は削除ボタンが出ない(チケットの「公開中は削除不可」)。
            ->assertDontSee(self::DELETE_CONFIRM);
    }

    public function test_archived_shows_unarchive_and_delete(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $pack = MeetingPack::factory()->archived()->create();

        $this->actingAs($admin)->get(route('admin.meeting-packs.show', $pack))
            ->assertOk()
            ->assertSee(route('admin.meeting-packs.unarchive', $pack))
            ->assertDontSee(route('admin.meeting-packs.publish', $pack))
            ->assertDontSee(route('admin.meeting-packs.archive', $pack))
            ->assertSee(self::DELETE_CONFIRM);
    }

    public function test_unknown_id_returns_404(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();

        // 【解説】存在しない ID → ルートモデルバインディングが見つけられず 404 になる。
        $this->actingAs($admin)
            ->get(route('admin.meeting-packs.show', '01AAAAAAAAAAAAAAAAAAAAAAAA'))
            ->assertNotFound();
    }
}
