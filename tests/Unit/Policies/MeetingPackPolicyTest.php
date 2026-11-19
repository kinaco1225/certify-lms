<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\MeetingPack;
use App\Models\User;
use App\Policies\MeetingPackPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * MeetingPackPolicy: 全 ability が admin のみ true、student / coach は false。
 */
// 【解説】テストクラスは Tests\TestCase を継承する。メソッド名が test_ で始まるものが 1 つのテストとして実行される。
class MeetingPackPolicyTest extends TestCase
{
    // 【解説】RefreshDatabase = テストごとに DB をまっさらに戻す。テスト同士でデータが影響し合わない。
    use RefreshDatabase;

    // 【解説】8 つの ability のうち、対象の面談パックを受け取るもの。ループで全部まとめて確認するために配列にしておく。
    private const INSTANCE_ABILITIES = ['view', 'update', 'delete', 'publish', 'archive', 'unarchive'];

    public function test_admin_can_do_everything(): void
    {
        // 【解説】Factory で管理者ユーザーと面談パックを DB に作る(テスト用の DB。開発用のデータには影響しない)。
        $admin = User::factory()->admin()->create();
        $pack = MeetingPack::factory()->create();
        $policy = new MeetingPackPolicy;

        // 【解説】assertTrue(値) = 値が true であることを確かめる。false ならテスト失敗。
        $this->assertTrue($policy->viewAny($admin));
        $this->assertTrue($policy->create($admin));
        foreach (self::INSTANCE_ABILITIES as $ability) {
            // 【解説】$policy->$ability(...) = 変数に入った名前のメソッドを呼ぶ(例: $ability が 'view' なら $policy->view(...))。
            // 【解説】第 2 引数のメッセージは、失敗したときにどの ability で失敗したかを表示するため。
            $this->assertTrue($policy->$ability($admin, $pack), "admin は {$ability} できるべき");
        }
    }

    public function test_student_and_coach_can_do_nothing(): void
    {
        $pack = MeetingPack::factory()->create();
        $policy = new MeetingPackPolicy;

        // 【解説】受講生とコーチの 2 人について、同じ確認をループで行う。
        foreach ([User::factory()->student()->create(), User::factory()->coach()->create()] as $user) {
            $this->assertFalse($policy->viewAny($user));
            $this->assertFalse($policy->create($user));
            foreach (self::INSTANCE_ABILITIES as $ability) {
                $this->assertFalse($policy->$ability($user, $pack), "{$user->role->value} は {$ability} できないべき");
            }
        }
    }
}
