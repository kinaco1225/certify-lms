<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaBoard;

use App\Enums\QaThreadStatus;
use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `/admin/qa-board` 管理者モデレーション(全資格横断の閲覧 / スレッド・回答の削除)を検証する。
 */
class ModerationTest extends TestCase
{
    use RefreshDatabase;

    // ---- アクセス制御 ----

    public function test_student_and_coach_cannot_access_moderation_routes(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $thread = QaThread::factory()->for($student)->create();
        $reply = QaReply::factory()->for($thread, 'thread')->for($student)->create();
        CertificationCoachAssignment::factory()->create(['certification_id' => $thread->certification_id, 'user_id' => $coach->id]);

        foreach ([$student, $coach] as $user) {
            $this->actingAs($user)->get(route('admin.qa-board.index'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.qa-board.show', $thread))->assertForbidden();
            $this->actingAs($user)->delete(route('admin.qa-board.destroy', $thread))->assertForbidden();
            $this->actingAs($user)
                ->delete(route('admin.qa-board.replies.destroy', ['thread' => $thread, 'reply' => $reply]))
                ->assertForbidden();
        }

        $this->assertModelExists($thread);
        $this->assertModelExists($reply);
    }

    // ---- 一覧 ----

    public function test_admin_sees_threads_of_all_certifications_including_unpublished(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $published = QaThread::factory()->create();
        $draft = QaThread::factory()->for(Certification::factory()->draft()->create())->create();
        $archived = QaThread::factory()->for(Certification::factory()->archived()->create(['name' => 'アーカイブ資格']))->create();

        $response = $this->actingAs($admin)->get(route('admin.qa-board.index'));

        $response->assertOk()->assertSee('質問掲示板モデレーション');
        $this->assertEqualsCanonicalizing(
            [$published->id, $draft->id, $archived->id],
            $response->viewData('threads')->pluck('id')->all(),
        );
        $this->assertCount(3, $response->viewData('certifications'));
        $response->assertSee(route('admin.qa-board.show', $archived));
        $response->assertDontSee(route('qa-board.create'));
    }

    public function test_admin_can_filter_by_unpublished_certification(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $archivedCert = Certification::factory()->archived()->create();
        $target = QaThread::factory()->for($archivedCert)->create();
        QaThread::factory()->create();

        $response = $this->actingAs($admin)
            ->get(route('admin.qa-board.index', ['certification_id' => $archivedCert->id]));

        $this->assertSame([$target->id], $response->viewData('threads')->pluck('id')->all());
    }

    public function test_admin_index_query_count_does_not_grow_with_thread_count(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        QaThread::factory()->count(2)->has(QaReply::factory()->count(2), 'replies')->create();

        $baseline = $this->countQueriesFor(fn () => $this->actingAs($admin)->get(route('admin.qa-board.index'))->assertOk());
        QaThread::factory()->count(15)->has(QaReply::factory()->count(2), 'replies')->create();
        $scaled = $this->countQueriesFor(fn () => $this->actingAs($admin)->get(route('admin.qa-board.index'))->assertOk());

        $this->assertLessThanOrEqual($baseline + 3, $scaled, "モデレーション一覧で N+1 (基準 {$baseline} → 増加後 {$scaled})");
    }

    // ---- 詳細 ----

    public function test_admin_show_offers_delete_only(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $thread = QaThread::factory()->for(Certification::factory()->archived()->create())->open()->create();
        $reply = QaReply::factory()->for($thread, 'thread')->create();

        $this->actingAs($admin)->get(route('admin.qa-board.show', $thread))
            ->assertOk()
            ->assertSee(route('admin.qa-board.destroy', $thread))
            ->assertSee(route('admin.qa-board.replies.destroy', ['thread' => $thread, 'reply' => $reply]))
            ->assertSee('管理者は閲覧 + モデレーション削除のみ可能で、回答投稿はできません。')
            ->assertDontSee(route('qa-board.edit', $thread))
            ->assertDontSee(route('qa-board.resolve', $thread))
            ->assertDontSee(route('qa-board.replies.edit', ['thread' => $thread, 'reply' => $reply]))
            ->assertDontSee(route('qa-board.replies.store', $thread));
    }

    // ---- 削除 ----

    public function test_admin_can_delete_thread_with_replies(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $thread = QaThread::factory()->create();
        $replies = QaReply::factory()->count(2)->for($thread, 'thread')->create();

        $this->actingAs($admin)
            ->delete(route('admin.qa-board.destroy', $thread))
            ->assertRedirect(route('admin.qa-board.index'))
            ->assertSessionHas('success', '質問を削除しました。');

        $this->assertModelMissing($thread);
        $replies->each(fn (QaReply $reply) => $this->assertModelMissing($reply));
    }

    public function test_admin_can_delete_reply(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $reply = QaReply::factory()->create();

        $this->actingAs($admin)
            ->delete(route('admin.qa-board.replies.destroy', ['thread' => $reply->qa_thread_id, 'reply' => $reply]))
            ->assertRedirect(route('admin.qa-board.show', $reply->qa_thread_id))
            ->assertSessionHas('success', '回答を削除しました。');

        $this->assertModelMissing($reply);
    }

    public function test_admin_reply_deletion_of_another_thread_returns_404(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $reply = QaReply::factory()->create();
        $anotherThread = QaThread::factory()->create();

        $this->actingAs($admin)
            ->delete(route('admin.qa-board.replies.destroy', ['thread' => $anotherThread, 'reply' => $reply]))
            ->assertNotFound();

        $this->assertModelExists($reply);
    }

    // ---- 管理者が行えない操作 ----

    public function test_admin_cannot_edit_resolve_or_reply_via_public_routes(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $thread = QaThread::factory()->open()->create(['title' => '元タイトル']);
        $reply = QaReply::factory()->for($thread, 'thread')->create(['body' => '元の回答']);

        $this->actingAs($admin)->patch(route('qa-board.update', $thread), ['title' => '改', 'body' => '改'])->assertForbidden();
        $this->actingAs($admin)->post(route('qa-board.resolve', $thread))->assertForbidden();
        $this->actingAs($admin)->post(route('qa-board.replies.store', $thread), ['body' => '回答'])->assertForbidden();
        $this->actingAs($admin)
            ->patch(route('qa-board.replies.update', ['thread' => $thread, 'reply' => $reply]), ['body' => '改'])
            ->assertForbidden();

        $this->assertSame('元タイトル', $thread->fresh()->title);
        $this->assertTrue($thread->fresh()->status === QaThreadStatus::Open);
        $this->assertSame('元の回答', $reply->fresh()->body);
        $this->assertDatabaseCount('qa_replies', 1);
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
