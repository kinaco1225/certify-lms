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
 * `GET /qa-board` 質問掲示板一覧の閲覧範囲・絞り込み・並び順・ページネーションを検証する。
 */
class IndexTest extends TestCase
{
    use RefreshDatabase;

    // ---- アクセス制御 ----

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('qa-board.index'))->assertRedirect(route('login'));
    }

    public function test_admin_cannot_access_public_board(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();

        $this->actingAs($admin)->get(route('qa-board.index'))->assertForbidden();
    }

    // ---- 閲覧範囲 ----

    public function test_student_sees_threads_of_all_published_certifications_only(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $publishedA = QaThread::factory()->create(['title' => '公開資格Aの質問']);
        $publishedB = QaThread::factory()->create(['title' => '公開資格Bの質問']);
        QaThread::factory()->for(Certification::factory()->draft()->create())->create(['title' => '下書き資格の質問']);
        QaThread::factory()->for(Certification::factory()->archived()->create())->create(['title' => 'アーカイブ資格の質問']);

        $response = $this->actingAs($student)->get(route('qa-board.index'));

        $response->assertOk();
        $this->assertEqualsCanonicalizing(
            [$publishedA->id, $publishedB->id],
            $response->viewData('threads')->pluck('id')->all(),
        );
        $response->assertDontSee('下書き資格の質問');
        $response->assertDontSee('アーカイブ資格の質問');
    }

    public function test_coach_sees_only_threads_of_assigned_published_certifications(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $assigned = Certification::factory()->published()->create();
        $assignedArchived = Certification::factory()->archived()->create();
        $formerlyAssigned = Certification::factory()->published()->create();
        $this->assignCoach($assigned, $coach);
        $this->assignCoach($assignedArchived, $coach);
        CertificationCoachAssignment::factory()->unassigned()->create([
            'certification_id' => $formerlyAssigned->id,
            'user_id' => $coach->id,
        ]);

        $visible = QaThread::factory()->for($assigned)->create();
        QaThread::factory()->for($assignedArchived)->create();
        QaThread::factory()->for($formerlyAssigned)->create();
        QaThread::factory()->create(); // 担当外

        $response = $this->actingAs($coach)->get(route('qa-board.index'));

        $response->assertOk();
        $this->assertSame([$visible->id], $response->viewData('threads')->pluck('id')->all());
        $this->assertSame([$assigned->id], $response->viewData('certifications')->pluck('id')->all());
    }

    public function test_certification_options_for_student_are_published_only(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $published = Certification::factory()->published()->create();
        Certification::factory()->draft()->create();
        Certification::factory()->archived()->create();

        $response = $this->actingAs($student)->get(route('qa-board.index'));

        $this->assertSame([$published->id], $response->viewData('certifications')->pluck('id')->all());
    }

    public function test_post_button_is_shown_to_student_but_not_to_coach(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();

        $this->actingAs($student)->get(route('qa-board.index'))->assertSee(route('qa-board.create'));
        $this->actingAs($coach)->get(route('qa-board.index'))->assertDontSee(route('qa-board.create'));
    }

    // ---- 絞り込み ----

    public function test_filter_by_status(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $open = QaThread::factory()->open()->create();
        $resolved = QaThread::factory()->resolved()->create();

        $this->assertSame(
            [$open->id],
            $this->actingAs($student)->get(route('qa-board.index', ['status' => 'unresolved']))
                ->viewData('threads')->pluck('id')->all(),
        );
        $this->assertSame(
            [$resolved->id],
            $this->actingAs($student)->get(route('qa-board.index', ['status' => 'resolved']))
                ->viewData('threads')->pluck('id')->all(),
        );
    }

    public function test_filter_by_certification(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $target = QaThread::factory()->create();
        QaThread::factory()->create();

        $response = $this->actingAs($student)
            ->get(route('qa-board.index', ['certification_id' => $target->certification_id]));

        $this->assertSame([$target->id], $response->viewData('threads')->pluck('id')->all());
    }

    public function test_filter_by_keyword_matches_title_or_body(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $byTitle = QaThread::factory()->create(['title' => '二分探索木の計算量', 'body' => '本文']);
        $byBody = QaThread::factory()->create(['title' => '質問', 'body' => '二分探索木がわかりません']);
        QaThread::factory()->create(['title' => 'ネットワーク', 'body' => 'TCP と UDP']);

        $response = $this->actingAs($student)->get(route('qa-board.index', ['keyword' => '二分探索木']));

        $this->assertEqualsCanonicalizing(
            [$byTitle->id, $byBody->id],
            $response->viewData('threads')->pluck('id')->all(),
        );
    }

    public function test_keyword_or_condition_does_not_bypass_other_filters(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $target = QaThread::factory()->open()->create(['title' => '対象', 'body' => 'キーワード']);
        // 本文一致だが資格・状態が異なる / 非公開資格 → いずれも除外されるべき
        QaThread::factory()->resolved()->for($target->certification)->create(['body' => 'キーワード']);
        QaThread::factory()->open()->create(['body' => 'キーワード']);
        QaThread::factory()->for(Certification::factory()->archived()->create())->create(['body' => 'キーワード']);

        $response = $this->actingAs($student)->get(route('qa-board.index', [
            'keyword' => 'キーワード',
            'status' => 'unresolved',
            'certification_id' => $target->certification_id,
        ]));

        $this->assertSame([$target->id], $response->viewData('threads')->pluck('id')->all());
    }

    public function test_invalid_filter_is_rejected(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $this->actingAs($student)
            ->get(route('qa-board.index', ['status' => 'invalid']))
            ->assertSessionHasErrors(['status' => '解決状態は「未解決」か「解決済」から選択してください。']);
    }

    // ---- 並び順 / ページネーション / 回答数 ----

    public function test_threads_are_ordered_newest_first_and_paginated(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $cert = Certification::factory()->published()->create();
        $oldest = QaThread::factory()->for($cert)->create(['created_at' => now()->subDays(30)]);
        $newest = QaThread::factory()->for($cert)->create(['created_at' => now()]);
        QaThread::factory()->count(19)->for($cert)->create(['created_at' => now()->subDays(10)]);

        $page1 = $this->actingAs($student)->get(route('qa-board.index'))->viewData('threads');
        $page2 = $this->actingAs($student)->get(route('qa-board.index', ['page' => 2]))->viewData('threads');

        $this->assertSame(21, $page1->total());
        $this->assertCount(20, $page1->items());
        $this->assertSame($newest->id, $page1->first()->id);
        $this->assertSame([$oldest->id], $page2->pluck('id')->all());
    }

    public function test_reply_count_is_loaded(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->create();
        QaReply::factory()->count(3)->for($thread, 'thread')->create();

        $threads = $this->actingAs($student)->get(route('qa-board.index'))->viewData('threads');

        $this->assertSame(3, $threads->first()->replies_count);
    }

    private function assignCoach(Certification $cert, User $coach): void
    {
        CertificationCoachAssignment::factory()->create([
            'certification_id' => $cert->id,
            'user_id' => $coach->id,
        ]);
    }
}
