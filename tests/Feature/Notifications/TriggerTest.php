<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\Certification;
use App\Models\ChatMember;
use App\Models\ChatRoom;
use App\Models\CoachAvailability;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\QaThread;
use App\Models\User;
use App\Notifications\Chat\ChatMessageReceivedNotification;
use App\Notifications\Mentoring\MeetingCanceledNotification;
use App\Notifications\Mentoring\MeetingReservedNotification;
use App\Notifications\QaBoard\QaReplyReceivedNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * 業務イベント(回答投稿 / 面談予約 / 面談キャンセル / チャット送信)で、正しい当事者に通知が送られることを
 * HTTP 経由で検証する。
 */
class TriggerTest extends TestCase
{
    use RefreshDatabase;

    // 【解説】各テストの前に Notification::fake() にしておく(実際の送信はせず、送った記録だけ取る)。
    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_qa_reply_notifies_thread_author_only(): void
    {
        $author = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $thread = QaThread::factory()->for($author)->create();
        $this->assignCoach($thread->certification, $coach);

        $this->actingAs($coach)
            ->post(route('qa-board.replies.store', $thread), ['body' => '回答します'])
            ->assertSessionHasNoErrors();

        // 【解説】質問の投稿者に届き、回答したコーチ本人には届かない。
        Notification::assertSentTo($author, QaReplyReceivedNotification::class);
        Notification::assertNotSentTo($coach, QaReplyReceivedNotification::class);
    }

    public function test_reply_to_own_thread_does_not_notify(): void
    {
        $author = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->for($author)->create();

        $this->actingAs($author)
            ->post(route('qa-board.replies.store', $thread), ['body' => '自己解決しました'])
            ->assertSessionHasNoErrors();

        // 【解説】自分の質問に自分で回答した場合は、誰にも通知しない。
        Notification::assertNothingSent();
    }

    public function test_meeting_reservation_notifies_assigned_coach(): void
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);
        $coach = User::factory()->coach()->inProgress()->create(['meeting_url' => 'https://meet.example.com/x']);
        $certification = Certification::factory()->published()->create();
        $this->assignCoach($certification, $coach);
        // 【解説】面談の予約には、コーチの対応可能時間(月曜 9〜18 時)が必要(既存の MeetingControllerTest と同じ準備)。
        CoachAvailability::factory()->forCoach($coach)->onDay(1)->timeRange('09:00:00', '18:00:00')->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();

        $this->actingAs($student)->post(route('meetings.store', $enrollment), [
            'scheduled_at' => now()->startOfDay()->next(Carbon::MONDAY)->setTime(10, 0)->format('Y-m-d\TH:i:s'),
            'topic' => '相談したい',
        ])->assertSessionHasNoErrors();

        // 【解説】assertSentTo の第 3 引数に関数を渡すと、送られた通知の中身まで確認できる。
        Notification::assertSentTo(
            $coach,
            MeetingReservedNotification::class,
            fn (MeetingReservedNotification $n) => $n->url() === route('meetings.show', Meeting::sole(), false),
        );
        Notification::assertNotSentTo($student, MeetingReservedNotification::class);
    }

    public function test_meeting_cancel_by_student_notifies_coach(): void
    {
        [$student, $coach, $meeting] = $this->reservedMeeting();

        $this->actingAs($student)->post(route('meetings.cancel', $meeting))->assertRedirect();

        Notification::assertSentTo($coach, MeetingCanceledNotification::class);
        Notification::assertNotSentTo($student, MeetingCanceledNotification::class);
    }

    public function test_meeting_cancel_by_coach_notifies_student(): void
    {
        [$student, $coach, $meeting] = $this->reservedMeeting();

        $this->actingAs($coach)->post(route('meetings.cancel', $meeting))->assertRedirect();

        Notification::assertSentTo(
            $student,
            MeetingCanceledNotification::class,
            // 【解説】メッセージにキャンセルした人(コーチ)の名前が入っていること。
            fn (MeetingCanceledNotification $n) => str_contains($n->message(), $coach->name),
        );
        Notification::assertNotSentTo($coach, MeetingCanceledNotification::class);
    }

    public function test_chat_message_notifies_other_members(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $otherCoach = User::factory()->coach()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $this->assignCoach($certification, $coach);
        $room = ChatRoom::factory()->for(Enrollment::factory()->for($student)->for($certification))->create();
        foreach ([$student, $coach, $otherCoach] as $member) {
            ChatMember::factory()->create(['chat_room_id' => $room->id, 'user_id' => $member->id]);
        }

        $this->actingAs($student)->post(route('chat.storeMessage', $room), ['body' => '質問です'])->assertRedirect();

        // 【解説】送信者以外のメンバー全員(コーチ 2 人)に届き、送信者には届かない。
        Notification::assertSentTo([$coach, $otherCoach], ChatMessageReceivedNotification::class);
        Notification::assertNotSentTo($student, ChatMessageReceivedNotification::class);
    }

    public function test_graduated_recipient_is_not_notified(): void
    {
        // 【解説】配信対象の制御: 卒業した受講生の質問に回答がついても通知しない。
        $author = User::factory()->student()->graduated()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $thread = QaThread::factory()->for($author)->create();
        $this->assignCoach($thread->certification, $coach);

        $this->actingAs($coach)->post(route('qa-board.replies.store', $thread), ['body' => '回答します']);

        Notification::assertNothingSent();
    }

    /**
     * @return array{0: User, 1: User, 2: Meeting}
     */
    // 【解説】受講生・コーチ・予約済み(3 日後)の面談を作る補助メソッド。
    private function reservedMeeting(): array
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 5]);
        $coach = User::factory()->coach()->inProgress()->create();
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);

        return [$student, $coach, $meeting];
    }

    // 【解説】資格に担当コーチを割り当てる補助メソッド(既存テストと同じ形)。
    private function assignCoach(Certification $certification, User $coach): void
    {
        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => User::factory()->admin()->create()->id,
            'assigned_at' => now(),
        ]);
    }
}
