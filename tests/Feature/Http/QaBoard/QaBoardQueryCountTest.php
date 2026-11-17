<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaBoard;

use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 質問掲示板の一覧 / 詳細の N+1 非回帰を検証する。
 *
 * 一覧はスレッド件数、詳細は回答件数を増やしても発行クエリ数がほぼ増えないこと
 * (投稿者 / 資格 / 回答数 / 担当コーチを Eager Loading + withCount で一括取得していること)を担保する。
 */
class QaBoardQueryCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_query_count_does_not_grow_with_thread_count(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $this->createThreadsWithReplies(2);

        $baseline = $this->countQueriesFor(fn () => $this->actingAs($student)->get(route('qa-board.index'))->assertOk());
        $this->createThreadsWithReplies(15);
        $scaled = $this->countQueriesFor(fn () => $this->actingAs($student)->get(route('qa-board.index'))->assertOk());

        $this->assertLessThanOrEqual(
            $baseline + 3,
            $scaled,
            "質問掲示板一覧で N+1 が発生している (基準 {$baseline} → 増加後 {$scaled})。投稿者 / 資格 / 回答数を一括取得しているか確認",
        );
    }

    public function test_show_query_count_does_not_grow_with_reply_count_for_coach(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $cert = Certification::factory()->published()->create();
        CertificationCoachAssignment::factory()->create(['certification_id' => $cert->id, 'user_id' => $coach->id]);
        $thread = QaThread::factory()->for($cert)->create();
        // コーチ自身の回答は update / delete 判定で親スレッドの閲覧可否まで評価される
        QaReply::factory()->count(2)->for($thread, 'thread')->for($coach)->create();

        $baseline = $this->countQueriesFor(fn () => $this->actingAs($coach)->get(route('qa-board.show', $thread))->assertOk());
        QaReply::factory()->count(10)->for($thread, 'thread')->for($coach)->create();
        QaReply::factory()->count(10)->for($thread, 'thread')->create();
        $scaled = $this->countQueriesFor(fn () => $this->actingAs($coach)->get(route('qa-board.show', $thread))->assertOk());

        $this->assertLessThanOrEqual(
            $baseline + 3,
            $scaled,
            "質問スレッド詳細で N+1 が発生している (基準 {$baseline} → 増加後 {$scaled})。回答者 / 親スレッド / 担当コーチを一括取得しているか確認",
        );
    }

    private function createThreadsWithReplies(int $count): void
    {
        QaThread::factory()
            ->count($count)
            ->has(QaReply::factory()->count(2), 'replies')
            ->create();
    }

    private function countQueriesFor(\Closure $closure): int
    {
        $count = 0;
        DB::listen(function () use (&$count): void {
            $count++;
        });

        $closure();

        return $count;
    }
}
