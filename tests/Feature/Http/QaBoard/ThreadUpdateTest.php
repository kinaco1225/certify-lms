<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaBoard;

use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `GET /qa-board/{thread}/edit` と `PATCH /qa-board/{thread}` 質問スレッドの編集を検証する。
 */
class ThreadUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_can_open_edit_form(): void
    {
        $author = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->for($author)->create();

        $this->actingAs($author)->get(route('qa-board.edit', $thread))
            ->assertOk()
            ->assertSee($thread->title);
    }

    public function test_author_can_update_title_and_body(): void
    {
        $author = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->for($author)->create();

        $this->actingAs($author)
            ->patch(route('qa-board.update', $thread), ['title' => '更新後タイトル', 'body' => '更新後本文'])
            ->assertRedirect(route('qa-board.show', $thread))
            ->assertSessionHas('success', '質問を更新しました。');

        $thread->refresh();
        $this->assertSame('更新後タイトル', $thread->title);
        $this->assertSame('更新後本文', $thread->body);
    }

    public function test_certification_cannot_be_changed(): void
    {
        $author = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->for($author)->create();
        $originalCertId = $thread->certification_id;
        $other = Certification::factory()->published()->create();

        $this->actingAs($author)
            ->patch(route('qa-board.update', $thread), [
                'certification_id' => $other->id,
                'title' => 'タイトル',
                'body' => '本文',
            ])
            ->assertRedirect(route('qa-board.show', $thread));

        $this->assertSame($originalCertId, $thread->fresh()->certification_id);
    }

    public function test_other_student_cannot_edit_or_update(): void
    {
        $other = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->create(['title' => '元タイトル']);

        $this->actingAs($other)->get(route('qa-board.edit', $thread))->assertForbidden();
        $this->actingAs($other)
            ->patch(route('qa-board.update', $thread), ['title' => '改ざん', 'body' => '改ざん'])
            ->assertForbidden();

        $this->assertSame('元タイトル', $thread->fresh()->title);
    }

    public function test_author_cannot_update_after_certification_is_unpublished(): void
    {
        $author = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->for($author)->for(Certification::factory()->archived()->create())->create();

        $this->actingAs($author)
            ->patch(route('qa-board.update', $thread), ['title' => 'タイトル', 'body' => '本文'])
            ->assertForbidden();
    }

    public function test_validation(): void
    {
        $author = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->for($author)->create();

        $this->actingAs($author)
            ->patch(route('qa-board.update', $thread), ['title' => '', 'body' => str_repeat('あ', 5001)])
            ->assertSessionHasErrors([
                'title' => 'タイトルを入力してください。',
                'body' => '本文は5000文字以内で入力してください。',
            ]);
    }
}
