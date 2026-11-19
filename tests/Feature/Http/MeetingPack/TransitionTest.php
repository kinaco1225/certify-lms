<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 状態遷移 `POST /admin/meeting-packs/{plan}/{publish|archive|unarchive}`。
 * 下書き → 公開中 → アーカイブ → 下書き の正しい順序だけ許可し、それ以外は 409(元の画面にエラー表示)。
 */
class TransitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_lifecycle_draft_publish_archive_unarchive(): void
    {
        // 【解説】作成者とは別の管理者で操作し、最終更新者が記録されることも確認する。
        $admin = User::factory()->admin()->inProgress()->create();
        $pack = MeetingPack::factory()->draft()->create();

        // 【解説】[ルート名, 遷移後の状態, フラッシュメッセージ] の順に 3 回操作する。
        $steps = [
            ['admin.meeting-packs.publish', MeetingPackStatus::Published, '面談パックを公開しました。'],
            ['admin.meeting-packs.archive', MeetingPackStatus::Archived, '面談パックをアーカイブしました。'],
            ['admin.meeting-packs.unarchive', MeetingPackStatus::Draft, '面談パックを下書きに戻しました。'],
        ];

        foreach ($steps as [$route, $expected, $message]) {
            $this->actingAs($admin)
                ->post(route($route, $pack))
                ->assertRedirect(route('admin.meeting-packs.show', $pack))
                ->assertSessionHas('success', $message);

            $fresh = $pack->fresh();
            $this->assertSame($expected, $fresh->status, $route);
            $this->assertSame($admin->id, $fresh->updated_by_user_id);
        }
    }

    public function test_invalid_transitions_are_rejected(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();

        // 【解説】[ルート名, 開始時の状態, 表示されるエラー] — 正しい順序以外の 6 通りすべて。
        $cases = [
            ['admin.meeting-packs.publish', 'published', '下書きの面談パックのみ公開できます。'],
            ['admin.meeting-packs.publish', 'archived', '下書きの面談パックのみ公開できます。'],
            ['admin.meeting-packs.archive', 'draft', '公開中の面談パックのみアーカイブできます。'],
            ['admin.meeting-packs.archive', 'archived', '公開中の面談パックのみアーカイブできます。'],
            ['admin.meeting-packs.unarchive', 'draft', 'アーカイブ済みの面談パックのみ下書きに戻せます。'],
            ['admin.meeting-packs.unarchive', 'published', 'アーカイブ済みの面談パックのみ下書きに戻せます。'],
        ];

        foreach ($cases as [$route, $from, $error]) {
            // 【解説】create(['status' => ...]) で、開始時の状態を指定してパックを作る。
            $pack = MeetingPack::factory()->create(['status' => $from]);

            $this->actingAs($admin)
                ->from(route('admin.meeting-packs.show', $pack))
                ->post(route($route, $pack))
                ->assertRedirect(route('admin.meeting-packs.show', $pack))
                ->assertSessionHas('error', $error);

            // 【解説】状態は変わっていない。
            $this->assertSame($from, $pack->fresh()->status->value, "{$route} from {$from}");
        }
    }
}
