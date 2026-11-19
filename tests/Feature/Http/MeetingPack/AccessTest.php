<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 面談パック管理の全 10 ルートが admin 専用であること(student / coach は 403、未ログインはログイン画面へ)。
 */
// 【解説】Feature テスト = 実際に URL へリクエストを送って、レスポンス(ステータスコードや画面)を確かめるテスト。
class AccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_and_coach_are_forbidden_on_all_routes(): void
    {
        $pack = MeetingPack::factory()->draft()->create();

        foreach ([User::factory()->student()->inProgress()->create(), User::factory()->coach()->inProgress()->create()] as $user) {
            // 【解説】[HTTP メソッド, URL] の組を 10 個並べ、全部 403 になるかを確認する。
            foreach ($this->routes($pack) as [$method, $url]) {
                // 【解説】actingAs($user) = そのユーザーでログインした状態にする。
                // 【解説】$this->$method($url) = $this->get($url) / $this->post($url) などを呼ぶ。
                // 【解説】assertForbidden() = ステータスが 403 であることを確かめる。
                $this->actingAs($user)->$method($url)->assertForbidden();
            }
        }

        // 【解説】403 で弾かれたので、データは何も変わっていない(削除も状態変更もされていない)はず。
        // 【解説】assertModelExists = DB にまだ存在する。fresh() = DB から読み直した最新の値。
        $this->assertModelExists($pack);
        $this->assertSame('draft', $pack->fresh()->status->value);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $pack = MeetingPack::factory()->create();

        foreach ($this->routes($pack) as [$method, $url]) {
            // 【解説】actingAs を使わない = 未ログイン。auth ミドルウェアでログイン画面へ移動させられる。
            $this->$method($url)->assertRedirect(route('login'));
        }
    }

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    // 【解説】テストで使う 10 ルートの一覧を返す補助メソッド(チケットの HTTP インターフェース表と同じ)。
    private function routes(MeetingPack $pack): array
    {
        return [
            ['get', route('admin.meeting-packs.index')],
            ['get', route('admin.meeting-packs.create')],
            ['post', route('admin.meeting-packs.store')],
            ['get', route('admin.meeting-packs.show', $pack)],
            ['get', route('admin.meeting-packs.edit', $pack)],
            ['patch', route('admin.meeting-packs.update', $pack)],
            ['delete', route('admin.meeting-packs.destroy', $pack)],
            ['post', route('admin.meeting-packs.publish', $pack)],
            ['post', route('admin.meeting-packs.archive', $pack)],
            ['post', route('admin.meeting-packs.unarchive', $pack)],
        ];
    }
}
