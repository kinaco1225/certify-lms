<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaBoard;

use App\Enums\QaThreadStatus;
use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `GET /qa-board/create` と `POST /qa-board` 質問スレッドの新規投稿を検証する。
 */
class ThreadStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_open_create_form_with_published_certifications_only(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $published = Certification::factory()->published()->create();
        Certification::factory()->draft()->create();
        Certification::factory()->archived()->create();

        $response = $this->actingAs($student)->get(route('qa-board.create'));

        $response->assertOk();
        $this->assertSame([$published->id], $response->viewData('certifications')->pluck('id')->all());
    }

    public function test_coach_cannot_open_create_form_or_post(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $cert = Certification::factory()->published()->create();
        CertificationCoachAssignment::factory()->create(['certification_id' => $cert->id, 'user_id' => $coach->id]);

        $this->actingAs($coach)->get(route('qa-board.create'))->assertForbidden();
        $this->actingAs($coach)->post(route('qa-board.store'), $this->payload($cert))->assertForbidden();
        $this->assertDatabaseCount('qa_threads', 0);
    }

    public function test_admin_cannot_post(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $cert = Certification::factory()->published()->create();

        $this->actingAs($admin)->post(route('qa-board.store'), $this->payload($cert))->assertForbidden();
    }

    public function test_student_can_post_thread_to_unenrolled_published_certification(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $cert = Certification::factory()->published()->create();

        $response = $this->actingAs($student)->post(route('qa-board.store'), $this->payload($cert));

        $thread = QaThread::sole();
        $response->assertRedirect(route('qa-board.show', $thread))
            ->assertSessionHas('success', '質問を投稿しました。');
        $this->assertSame($student->id, $thread->user_id);
        $this->assertSame($cert->id, $thread->certification_id);
        $this->assertSame('投稿タイトル', $thread->title);
        $this->assertSame(QaThreadStatus::Open, $thread->status);
        $this->assertNull($thread->resolved_at);
    }

    public function test_cannot_post_to_unpublished_certification(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        foreach ([Certification::factory()->draft()->create(), Certification::factory()->archived()->create()] as $cert) {
            $this->actingAs($student)
                ->post(route('qa-board.store'), $this->payload($cert))
                ->assertSessionHasErrors(['certification_id' => '公開中の資格を選択してください。']);
        }

        $this->assertDatabaseCount('qa_threads', 0);
    }

    public function test_required_fields(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $this->actingAs($student)
            ->post(route('qa-board.store'), ['certification_id' => '', 'title' => '', 'body' => ''])
            ->assertSessionHasErrors([
                'certification_id' => '資格を選択してください。',
                'title' => 'タイトルを入力してください。',
                'body' => '本文を入力してください。',
            ]);
    }

    public function test_title_and_body_max_length(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $cert = Certification::factory()->published()->create();

        $this->actingAs($student)
            ->post(route('qa-board.store'), $this->payload($cert, [
                'title' => str_repeat('あ', 201),
                'body' => str_repeat('あ', 5001),
            ]))
            ->assertSessionHasErrors([
                'title' => 'タイトルは200文字以内で入力してください。',
                'body' => '本文は5000文字以内で入力してください。',
            ]);

        $this->actingAs($student)
            ->post(route('qa-board.store'), $this->payload($cert, [
                'title' => str_repeat('あ', 200),
                'body' => str_repeat('あ', 5000),
            ]))
            ->assertSessionHasNoErrors();
    }

    /**
     * @param array<string, string> $overrides
     *
     * @return array<string, string>
     */
    private function payload(Certification $cert, array $overrides = []): array
    {
        return array_merge([
            'certification_id' => $cert->id,
            'title' => '投稿タイトル',
            'body' => '投稿本文',
        ], $overrides);
    }
}
