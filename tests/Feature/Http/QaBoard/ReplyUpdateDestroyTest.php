<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaBoard;

use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 回答の編集(`GET .../edit` / `PATCH`)と削除(`DELETE`)、および {thread} / {reply} の親子整合を検証する。
 */
class ReplyUpdateDestroyTest extends TestCase
{
    use RefreshDatabase;

    // ---- 編集 ----

    public function test_author_can_open_edit_form(): void
    {
        $author = User::factory()->student()->inProgress()->create();
        $reply = QaReply::factory()->for($author)->create(['body' => '編集前の回答']);

        $this->actingAs($author)
            ->get(route('qa-board.replies.edit', ['thread' => $reply->qa_thread_id, 'reply' => $reply]))
            ->assertOk()
            ->assertSee('編集前の回答');
    }

    public function test_author_can_update_reply(): void
    {
        $author = User::factory()->student()->inProgress()->create();
        $reply = QaReply::factory()->for($author)->create();

        $this->actingAs($author)
            ->patch(route('qa-board.replies.update', ['thread' => $reply->qa_thread_id, 'reply' => $reply]), ['body' => '更新後'])
            ->assertRedirect(route('qa-board.show', $reply->qa_thread_id).'#reply-'.$reply->id)
            ->assertSessionHas('success', '回答を更新しました。');

        $this->assertSame('更新後', $reply->fresh()->body);
    }

    public function test_other_user_cannot_edit_or_update_reply(): void
    {
        $other = User::factory()->student()->inProgress()->create();
        $reply = QaReply::factory()->create(['body' => '元の回答']);
        $params = ['thread' => $reply->qa_thread_id, 'reply' => $reply];

        $this->actingAs($other)->get(route('qa-board.replies.edit', $params))->assertForbidden();
        $this->actingAs($other)->patch(route('qa-board.replies.update', $params), ['body' => '改ざん'])->assertForbidden();

        $this->assertSame('元の回答', $reply->fresh()->body);
    }

    public function test_author_cannot_update_after_certification_is_unpublished(): void
    {
        $author = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->for(Certification::factory()->archived()->create())->create();
        $reply = QaReply::factory()->for($thread, 'thread')->for($author)->create();

        $this->actingAs($author)
            ->patch(route('qa-board.replies.update', ['thread' => $thread, 'reply' => $reply]), ['body' => '更新'])
            ->assertForbidden();
    }

    public function test_update_validation(): void
    {
        $author = User::factory()->student()->inProgress()->create();
        $reply = QaReply::factory()->for($author)->create();

        $this->actingAs($author)
            ->patch(route('qa-board.replies.update', ['thread' => $reply->qa_thread_id, 'reply' => $reply]), ['body' => ''])
            ->assertSessionHasErrors(['body' => '回答本文を入力してください。']);
    }

    // ---- 削除 ----

    public function test_author_can_delete_reply(): void
    {
        $author = User::factory()->student()->inProgress()->create();
        $reply = QaReply::factory()->for($author)->create();

        $this->actingAs($author)
            ->delete(route('qa-board.replies.destroy', ['thread' => $reply->qa_thread_id, 'reply' => $reply]))
            ->assertRedirect(route('qa-board.show', $reply->qa_thread_id))
            ->assertSessionHas('success', '回答を削除しました。');

        $this->assertModelMissing($reply);
    }

    public function test_other_user_cannot_delete_reply(): void
    {
        $other = User::factory()->student()->inProgress()->create();
        $reply = QaReply::factory()->create();

        $this->actingAs($other)
            ->delete(route('qa-board.replies.destroy', ['thread' => $reply->qa_thread_id, 'reply' => $reply]))
            ->assertForbidden();

        $this->assertModelExists($reply);
    }

    // ---- 親子整合(scopeBindings) ----

    public function test_reply_of_another_thread_returns_404(): void
    {
        $author = User::factory()->student()->inProgress()->create();
        $reply = QaReply::factory()->for($author)->create();
        $anotherThread = QaThread::factory()->create();
        $params = ['thread' => $anotherThread, 'reply' => $reply];

        $this->actingAs($author)->get(route('qa-board.replies.edit', $params))->assertNotFound();
        $this->actingAs($author)->patch(route('qa-board.replies.update', $params), ['body' => 'x'])->assertNotFound();
        $this->actingAs($author)->delete(route('qa-board.replies.destroy', $params))->assertNotFound();

        $this->assertModelExists($reply);
    }
}
