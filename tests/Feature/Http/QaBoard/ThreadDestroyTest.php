<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaBoard;

use App\Models\CertificationCoachAssignment;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `DELETE /qa-board/{thread}` 投稿者による質問スレッドの削除を検証する。
 */
class ThreadDestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_can_delete_thread_without_replies(): void
    {
        $author = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->for($author)->create();

        $this->actingAs($author)
            ->delete(route('qa-board.destroy', $thread))
            ->assertRedirect(route('qa-board.index'))
            ->assertSessionHas('success', '質問を削除しました。');

        $this->assertModelMissing($thread);
    }

    public function test_author_cannot_delete_thread_with_replies(): void
    {
        $author = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->for($author)->create();
        QaReply::factory()->for($thread, 'thread')->create();

        $this->actingAs($author)->delete(route('qa-board.destroy', $thread))->assertForbidden();

        $this->assertModelExists($thread);
    }

    public function test_other_student_and_coach_cannot_delete(): void
    {
        $other = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $thread = QaThread::factory()->create();
        CertificationCoachAssignment::factory()->create(['certification_id' => $thread->certification_id, 'user_id' => $coach->id]);

        $this->actingAs($other)->delete(route('qa-board.destroy', $thread))->assertForbidden();
        $this->actingAs($coach)->delete(route('qa-board.destroy', $thread))->assertForbidden();

        $this->assertModelExists($thread);
    }
}
