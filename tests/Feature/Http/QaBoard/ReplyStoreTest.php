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
 * `POST /qa-board/{thread}/replies` 回答投稿を検証する。
 */
class ReplyStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_reply_to_other_students_thread(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->create();

        $response = $this->actingAs($student)
            ->post(route('qa-board.replies.store', $thread), ['body' => '回答します']);

        $reply = QaReply::sole();
        $response->assertRedirect(route('qa-board.show', $thread).'#reply-'.$reply->id)
            ->assertSessionHas('success', '回答を投稿しました。');
        $this->assertSame($thread->id, $reply->qa_thread_id);
        $this->assertSame($student->id, $reply->user_id);
        $this->assertSame('回答します', $reply->body);
    }

    public function test_assigned_coach_can_reply(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $thread = QaThread::factory()->create();
        CertificationCoachAssignment::factory()->create(['certification_id' => $thread->certification_id, 'user_id' => $coach->id]);

        $this->actingAs($coach)
            ->post(route('qa-board.replies.store', $thread), ['body' => 'コーチの回答'])
            ->assertRedirect();

        $this->assertSame($coach->id, QaReply::sole()->user_id);
    }

    public function test_student_can_reply_to_resolved_thread(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->resolved()->create();

        $this->actingAs($student)
            ->post(route('qa-board.replies.store', $thread), ['body' => '補足'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('qa_replies', 1);
    }

    public function test_unassigned_coach_cannot_reply(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $thread = QaThread::factory()->create();

        $this->actingAs($coach)
            ->post(route('qa-board.replies.store', $thread), ['body' => '回答'])
            ->assertForbidden();

        $this->assertDatabaseCount('qa_replies', 0);
    }

    public function test_cannot_reply_to_thread_of_unpublished_certification(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->for(Certification::factory()->archived()->create())->create();

        $this->actingAs($student)
            ->post(route('qa-board.replies.store', $thread), ['body' => '回答'])
            ->assertForbidden();
    }

    public function test_admin_cannot_reply(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $thread = QaThread::factory()->create();

        $this->actingAs($admin)
            ->post(route('qa-board.replies.store', $thread), ['body' => '回答'])
            ->assertForbidden();
    }

    public function test_validation(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->create();

        $this->actingAs($student)
            ->post(route('qa-board.replies.store', $thread), ['body' => ''])
            ->assertSessionHasErrors(['body' => '回答本文を入力してください。']);
        $this->actingAs($student)
            ->post(route('qa-board.replies.store', $thread), ['body' => str_repeat('あ', 5001)])
            ->assertSessionHasErrors(['body' => '回答本文は5000文字以内で入力してください。']);
        $this->actingAs($student)
            ->post(route('qa-board.replies.store', $thread), ['body' => str_repeat('あ', 5000)])
            ->assertSessionHasNoErrors();
    }

    public function test_posting_reply_touches_thread_updated_at(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->create(['updated_at' => now()->subDays(3)]);

        $this->actingAs($student)->post(route('qa-board.replies.store', $thread), ['body' => '回答']);

        $this->assertTrue($thread->fresh()->updated_at->isAfter(now()->subMinute()));
    }
}
