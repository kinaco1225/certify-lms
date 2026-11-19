<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `DELETE /admin/meeting-packs/{plan}`: 下書き / アーカイブは物理削除できる。公開中は削除できない(409 → 元の画面にエラー表示)。
 */
class DestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_and_archived_can_be_deleted(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();

        foreach ([MeetingPack::factory()->draft()->create(), MeetingPack::factory()->archived()->create()] as $pack) {
            $this->actingAs($admin)
                ->delete(route('admin.meeting-packs.destroy', $pack))
                ->assertRedirect(route('admin.meeting-packs.index'))
                ->assertSessionHas('success', '面談パックを削除しました。');

            // 【解説】assertModelMissing = DB から消えていること(物理削除なので行ごと無い)。
            $this->assertModelMissing($pack);
        }
    }

    public function test_published_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $pack = MeetingPack::factory()->published()->create();

        // 【解説】DestroyAction が 409 の例外を投げる → Handler が「元の画面に戻して error メッセージ」に変換する。
        // 【解説】from() で詳細画面から操作したことにして、そこに戻されることを確認する。
        $this->actingAs($admin)
            ->from(route('admin.meeting-packs.show', $pack))
            ->delete(route('admin.meeting-packs.destroy', $pack))
            ->assertRedirect(route('admin.meeting-packs.show', $pack))
            ->assertSessionHas('error', '公開中の面談パックは削除できません。');

        // 【解説】削除されずに残っている。
        $this->assertModelExists($pack);
    }
}
