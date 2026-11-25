<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Plan;

use App\Enums\PlanStatus;
use App\Exceptions\Plan\PlanInvalidTransitionException;
use App\Exceptions\Plan\PlanNotDeletableException;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserPlanLog;
use App\UseCases\Plan\ArchiveAction;
use App\UseCases\Plan\DestroyAction;
use App\UseCases\Plan\PublishAction;
use App\UseCases\Plan\UnarchiveAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 状態遷移 / 削除の Action を HTTP を通さずに直接呼び、例外(409)とガードを検証する。
 */
// 【解説】Policy を通らない呼び出し(将来のバッチ処理など)でも、Action だけでルールが守られることを保証する。
class PlanActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_publish_from_non_draft_throws_409(): void
    {
        $this->expectException(PlanInvalidTransitionException::class);
        $this->expectExceptionMessage('下書きのプランのみ公開できます。');

        app(PublishAction::class)(Plan::factory()->published()->create(), User::factory()->admin()->create());
    }

    public function test_archive_from_non_published_throws_409(): void
    {
        $this->expectException(PlanInvalidTransitionException::class);
        $this->expectExceptionMessage('公開中のプランのみアーカイブできます。');

        app(ArchiveAction::class)(Plan::factory()->draft()->create(), User::factory()->admin()->create());
    }

    public function test_unarchive_from_non_archived_throws_409(): void
    {
        $this->expectException(PlanInvalidTransitionException::class);
        $this->expectExceptionMessage('アーカイブ済みのプランのみ下書きに戻せます。');

        app(UnarchiveAction::class)(Plan::factory()->published()->create(), User::factory()->admin()->create());
    }

    public function test_destroy_non_draft_throws_not_draft(): void
    {
        $plan = Plan::factory()->archived()->create();

        try {
            app(DestroyAction::class)($plan);
            $this->fail('例外が投げられるべき');
        } catch (PlanNotDeletableException $e) {
            $this->assertSame(409, $e->getStatusCode());
            $this->assertSame('下書きのプランのみ削除できます。', $e->getMessage());
        }

        $this->assertModelExists($plan);
    }

    public function test_destroy_referenced_draft_throws_in_use(): void
    {
        // 【解説】受講者あり / ソフトデリートされた受講者あり / 履歴だけあり のどれでも inUse の 409。
        $withUser = Plan::factory()->draft()->create();
        User::factory()->student()->inProgress()->withPlan($withUser)->create();
        $withTrashedUser = Plan::factory()->draft()->create();
        User::factory()->student()->withdrawn()->withPlan($withTrashedUser)->create()->delete();
        $withLog = Plan::factory()->draft()->create();
        UserPlanLog::factory()->create(['plan_id' => $withLog->id]);

        foreach ([$withUser, $withTrashedUser, $withLog] as $plan) {
            try {
                app(DestroyAction::class)($plan);
                $this->fail('例外が投げられるべき');
            } catch (PlanNotDeletableException $e) {
                $this->assertSame(409, $e->getStatusCode());
                $this->assertSame('受講者またはプラン履歴が紐づいているプランは削除できません。', $e->getMessage());
            }

            $this->assertModelExists($plan);
        }
    }

    public function test_destroy_unreferenced_draft_deletes_record(): void
    {
        $plan = Plan::factory()->draft()->create();

        app(DestroyAction::class)($plan);

        $this->assertModelMissing($plan);
    }

    public function test_transition_does_not_change_status_on_failure(): void
    {
        $plan = Plan::factory()->archived()->create();

        try {
            app(PublishAction::class)($plan, User::factory()->admin()->create());
        } catch (PlanInvalidTransitionException) {
            // 【解説】例外は想定どおりなので何もしない(catch のブロックでは変数を省略できる)。
        }

        $this->assertSame(PlanStatus::Archived, $plan->fresh()->status);
    }
}
