<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaBoard;

use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `GET /qa-board/{thread}` スレッド詳細の閲覧可否と、閲覧者ごとの操作ボタン表示を検証する。
 */
class ShowTest extends TestCase
{
    use RefreshDatabase;

    // ---- 閲覧可否 ----

    public function test_student_can_view_thread_with_replies_in_posted_order(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->create(['title' => '詳細表示の質問']);
        QaReply::factory()->for($thread, 'thread')->create(['body' => '後の回答', 'created_at' => now()]);
        QaReply::factory()->for($thread, 'thread')->create(['body' => '先の回答', 'created_at' => now()->subHour()]);

        $response = $this->actingAs($student)->get(route('qa-board.show', $thread));

        $response->assertOk();
        $response->assertSee('詳細表示の質問');
        $response->assertSeeInOrder(['先の回答', '後の回答']);
    }

    public function test_student_cannot_view_thread_of_unpublished_certification(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->for(Certification::factory()->archived()->create())->create();

        $this->actingAs($student)->get(route('qa-board.show', $thread))->assertForbidden();
    }

    public function test_assigned_coach_can_view_thread(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $cert = Certification::factory()->published()->create();
        CertificationCoachAssignment::factory()->create(['certification_id' => $cert->id, 'user_id' => $coach->id]);
        $thread = QaThread::factory()->for($cert)->create();

        $this->actingAs($coach)->get(route('qa-board.show', $thread))
            ->assertOk()
            ->assertSee(route('qa-board.replies.store', $thread));
    }

    public function test_unassigned_coach_cannot_view_thread(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $thread = QaThread::factory()->create();

        $this->actingAs($coach)->get(route('qa-board.show', $thread))->assertForbidden();
    }

    public function test_admin_cannot_access_public_show(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $thread = QaThread::factory()->create();

        $this->actingAs($admin)->get(route('qa-board.show', $thread))->assertForbidden();
    }

    public function test_missing_thread_returns_404(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $this->actingAs($student)->get(route('qa-board.show', '01HZZZZZZZZZZZZZZZZZZZZZZZ'))->assertNotFound();
    }

    // ---- 操作ボタンの出し分け ----

    public function test_author_sees_edit_delete_and_resolve_buttons(): void
    {
        $author = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->for($author)->open()->create();

        $this->actingAs($author)->get(route('qa-board.show', $thread))
            ->assertOk()
            ->assertSee(route('qa-board.edit', $thread))
            ->assertSee(route('qa-board.destroy', $thread))
            ->assertSee(route('qa-board.resolve', $thread))
            ->assertDontSee(route('qa-board.unresolve', $thread));
    }

    public function test_author_sees_unresolve_button_on_resolved_thread(): void
    {
        $author = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->for($author)->resolved()->create();

        $this->actingAs($author)->get(route('qa-board.show', $thread))
            ->assertSee(route('qa-board.unresolve', $thread))
            ->assertDontSee(route('qa-board.resolve', $thread));
    }

    public function test_author_does_not_see_delete_button_when_thread_has_replies(): void
    {
        $author = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->for($author)->create();
        QaReply::factory()->for($thread, 'thread')->create();

        $this->actingAs($author)->get(route('qa-board.show', $thread))
            ->assertSee(route('qa-board.edit', $thread))
            ->assertDontSee('この質問を削除しますか');
    }

    public function test_other_student_sees_no_thread_operation_buttons(): void
    {
        $other = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->create();

        $this->actingAs($other)->get(route('qa-board.show', $thread))
            ->assertOk()
            ->assertDontSee(route('qa-board.edit', $thread))
            ->assertDontSee(route('qa-board.resolve', $thread))
            ->assertDontSee('この質問を削除しますか');
    }

    public function test_reply_author_sees_edit_link_only_for_own_reply(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->create();
        $own = QaReply::factory()->for($thread, 'thread')->for($student)->create();
        $others = QaReply::factory()->for($thread, 'thread')->create();

        $this->actingAs($student)->get(route('qa-board.show', $thread))
            ->assertSee(route('qa-board.replies.edit', ['thread' => $thread, 'reply' => $own]))
            ->assertDontSee(route('qa-board.replies.edit', ['thread' => $thread, 'reply' => $others]));
    }
}
