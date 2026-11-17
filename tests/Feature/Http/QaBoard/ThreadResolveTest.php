<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaBoard;

use App\Enums\QaThreadStatus;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `POST /qa-board/{thread}/resolve` / `unresolve` 解決状態の切り替えを検証する。
 */
class ThreadResolveTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_can_resolve_open_thread(): void
    {
        $author = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->for($author)->open()->create();

        $this->actingAs($author)
            ->post(route('qa-board.resolve', $thread))
            ->assertRedirect(route('qa-board.show', $thread))
            ->assertSessionHas('success', '解決済にしました。');

        $thread->refresh();
        $this->assertSame(QaThreadStatus::Resolved, $thread->status);
        $this->assertNotNull($thread->resolved_at);
    }

    public function test_author_can_unresolve_resolved_thread(): void
    {
        $author = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->for($author)->resolved()->create();

        $this->actingAs($author)
            ->post(route('qa-board.unresolve', $thread))
            ->assertRedirect(route('qa-board.show', $thread))
            ->assertSessionHas('success', '未解決に戻しました。');

        $thread->refresh();
        $this->assertSame(QaThreadStatus::Open, $thread->status);
        $this->assertNull($thread->resolved_at);
    }

    public function test_resolving_already_resolved_thread_redirects_back_with_error(): void
    {
        $author = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->for($author)->resolved()->create();

        $this->actingAs($author)
            ->from(route('qa-board.show', $thread))
            ->post(route('qa-board.resolve', $thread))
            ->assertRedirect(route('qa-board.show', $thread))
            ->assertSessionHas('error');
    }

    public function test_unresolving_open_thread_redirects_back_with_error(): void
    {
        $author = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->for($author)->open()->create();

        $this->actingAs($author)
            ->from(route('qa-board.show', $thread))
            ->post(route('qa-board.unresolve', $thread))
            ->assertSessionHas('error');

        $this->assertSame(QaThreadStatus::Open, $thread->fresh()->status);
    }

    public function test_other_student_cannot_resolve_or_unresolve(): void
    {
        $other = User::factory()->student()->inProgress()->create();
        $open = QaThread::factory()->open()->create();
        $resolved = QaThread::factory()->resolved()->create();

        $this->actingAs($other)->post(route('qa-board.resolve', $open))->assertForbidden();
        $this->actingAs($other)->post(route('qa-board.unresolve', $resolved))->assertForbidden();

        $this->assertSame(QaThreadStatus::Open, $open->fresh()->status);
        $this->assertSame(QaThreadStatus::Resolved, $resolved->fresh()->status);
    }
}
