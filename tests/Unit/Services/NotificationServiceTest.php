<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\QaReply;
use App\Models\User;
use App\Notifications\QaBoard\QaReplyReceivedNotification;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * NotificationService: 受信対象の絞り込み(受講中の受講生 / コーチのみ)と、コミット後送信。
 */
class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_only_to_in_progress_students_and_coaches(): void
    {
        // 【解説】Notification::fake() = 実際には送らず、「誰に何を送ったか」だけを記録するモードにする。
        Notification::fake();

        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $graduated = User::factory()->student()->graduated()->create();
        $withdrawn = User::factory()->student()->withdrawn()->create();
        $invited = User::factory()->student()->invited()->create();
        $admin = User::factory()->admin()->inProgress()->create();

        app(NotificationService::class)->send(
            [$student, $coach, $graduated, $withdrawn, $invited, $admin],
            new QaReplyReceivedNotification(QaReply::factory()->create()),
        );

        // 【解説】assertSentTo = その人に、その種類の通知が送られたこと。
        Notification::assertSentTo([$student, $coach], QaReplyReceivedNotification::class);
        // 【解説】assertNotSentTo = その人には送られていないこと。
        Notification::assertNotSentTo([$graduated, $withdrawn, $invited, $admin], QaReplyReceivedNotification::class);
    }

    public function test_accepts_single_user_and_removes_duplicates(): void
    {
        Notification::fake();
        $student = User::factory()->student()->inProgress()->create();
        $notification = new QaReplyReceivedNotification(QaReply::factory()->create());

        // 【解説】同じ人が 2 回入っていても、送るのは 1 回だけ。
        app(NotificationService::class)->send([$student, $student], $notification);
        // 【解説】1 人(User)を直接渡す形でも送れる。
        app(NotificationService::class)->send($student, $notification);

        // 【解説】assertSentToTimes(相手, 種類, 回数) = 送られた回数。1 回目の呼び出しで 1 回 + 2 回目で 1 回 = 2 回。
        Notification::assertSentToTimes($student, QaReplyReceivedNotification::class, 2);
    }

    public function test_does_not_send_when_transaction_is_rolled_back(): void
    {
        Notification::fake();
        $student = User::factory()->student()->inProgress()->create();
        $reply = QaReply::factory()->create();

        try {
            DB::transaction(function () use ($student, $reply): void {
                app(NotificationService::class)->send($student, new QaReplyReceivedNotification($reply));
                // 【解説】送信の予約をした後に、わざとエラーを起こしてロールバックさせる。
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException) {
            // 【解説】想定どおりのエラーなので何もしない。
        }

        // 【解説】ロールバックされたので、通知は送られていない(DB::afterCommit の効果)。
        Notification::assertNothingSent();
    }

    public function test_saves_database_notification_and_sends_mail(): void
    {
        // 【解説】fake を使わずに実際に送り、notifications テーブルに保存されることを確認する(メールは phpunit.xml の設定で array ドライバ)。
        $student = User::factory()->student()->inProgress()->create();

        app(NotificationService::class)->send($student, new QaReplyReceivedNotification(QaReply::factory()->create()));

        $this->assertSame(1, $student->notifications()->count());
        $this->assertSame('qa_reply_received', $student->notifications()->first()->data['notification_type']);
        $this->assertNull($student->notifications()->first()->read_at);
    }
}
