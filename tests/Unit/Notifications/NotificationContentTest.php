<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Models\ChatMessage;
use App\Models\Meeting;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use App\Notifications\Chat\ChatMessageReceivedNotification;
use App\Notifications\Mentoring\MeetingCanceledNotification;
use App\Notifications\Mentoring\MeetingReservedNotification;
use App\Notifications\QaBoard\QaReplyReceivedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 4 種類の通知クラスが、画面(notification-row)とメールで使うデータを正しく作ることを検証する。
 */
class NotificationContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_qa_reply_received(): void
    {
        $recipient = User::factory()->student()->inProgress()->create(['name' => '質問者']);
        $thread = QaThread::factory()->for($recipient)->create(['title' => '例外の使い方']);
        $reply = QaReply::factory()->for($thread, 'thread')->for(User::factory()->coach()->create(['name' => 'コーチA']))->create();

        $notification = new QaReplyReceivedNotification($reply);

        // 【解説】via() = 送る経路。アプリ内(database)とメール(mail)の両方。
        $this->assertSame(['database', 'mail'], $notification->via($recipient));
        // 【解説】toArray() = notifications.data に保存される中身。画面はこのキーで読む。
        $this->assertSame([
            'notification_type' => 'qa_reply_received',
            'title' => '質問に回答がつきました',
            'message' => '「例外の使い方」に コーチA さんが回答しました。',
            'url' => "/qa-board/{$thread->id}#reply-{$reply->id}",
        ], $notification->toArray($recipient));

        // 【解説】toMail() = メールの中身。件名・あいさつ・ボタンの URL を確認する。
        $mail = $notification->toMail($recipient);
        $this->assertSame('【Certify LMS】質問に回答がつきました', $mail->subject);
        $this->assertSame('質問者 さん', $mail->greeting);
        // 【解説】ボタンの URL はドメイン付きの完全な URL(メールからアクセスするため)。
        $this->assertSame(url("/qa-board/{$thread->id}#reply-{$reply->id}"), $mail->actionUrl);
    }

    public function test_meeting_reserved(): void
    {
        $student = User::factory()->student()->inProgress()->create(['name' => '受講生B']);
        $coach = User::factory()->coach()->inProgress()->create();
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)
            ->create(['scheduled_at' => '2026-12-01 10:00:00']);

        $data = (new MeetingReservedNotification($meeting))->toArray($coach);

        $this->assertSame('meeting_reserved', $data['notification_type']);
        $this->assertSame('面談が予約されました', $data['title']);
        $this->assertSame('受講生B さんが 2026/12/01 10:00 の面談を予約しました。', $data['message']);
        $this->assertSame("/meetings/{$meeting->id}", $data['url']);
    }

    public function test_meeting_canceled(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create(['name' => 'コーチC']);
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)
            ->create(['scheduled_at' => '2026-12-02 15:00:00']);

        // 【解説】コーチがキャンセルした → 受講生が受け取る通知。メッセージにはキャンセルした人の名前が入る。
        $data = (new MeetingCanceledNotification($meeting, $coach))->toArray($student);

        $this->assertSame('meeting_canceled', $data['notification_type']);
        $this->assertSame('面談がキャンセルされました', $data['title']);
        $this->assertSame('コーチC さんが 2026/12/02 15:00 の面談をキャンセルしました。', $data['message']);
        $this->assertSame("/meetings/{$meeting->id}", $data['url']);
    }

    public function test_chat_message_received_truncates_body(): void
    {
        $sender = User::factory()->coach()->inProgress()->create(['name' => 'コーチD']);
        $message = ChatMessage::factory()->create([
            'sender_user_id' => $sender->id,
            // 【解説】60 文字の本文。通知では 50 文字 + 「...」に切り詰められる。
            'body' => str_repeat('あ', 60),
        ]);

        $data = (new ChatMessageReceivedNotification($message))->toArray(User::factory()->student()->create());

        $this->assertSame('chat_message_received', $data['notification_type']);
        $this->assertSame('新しいチャットメッセージ', $data['title']);
        $this->assertSame('コーチD さん:'.str_repeat('あ', 50).'...', $data['message']);
        $this->assertSame("/chat-rooms/{$message->chat_room_id}", $data['url']);
    }
}
