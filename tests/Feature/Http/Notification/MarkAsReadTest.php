<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Notification;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `POST /notifications/{notification}/read`: 既読化と同時に関連画面へ移動。他人の通知は 404。
 */
class MarkAsReadTest extends TestCase
{
    use NotificationTestHelpers;
    use RefreshDatabase;

    public function test_marks_as_read_and_redirects_to_url(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $notification = $this->createNotification($student, ['url' => '/qa-board/abc#reply-1']);

        $this->actingAs($student)
            ->post(route('notifications.markAsRead', $notification->id))
            ->assertRedirect('/qa-board/abc#reply-1');

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_already_read_notification_keeps_read_at(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $notification = $this->createNotification($student, read: true);
        $readAt = $notification->read_at->toDateTimeString();
        $this->travel(1)->hours();

        $this->actingAs($student)->post(route('notifications.markAsRead', $notification->id))->assertRedirect('/qa-board');

        // 【解説】既読の通知をもう一度クリックしても、既読にした日時は変わらない。
        $this->assertSame($readAt, $notification->fresh()->read_at->toDateTimeString());
    }

    public function test_cannot_read_others_notification(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $notification = $this->createNotification($owner);
        $other = User::factory()->coach()->inProgress()->create();

        // 【解説】他人宛の通知は 404(存在するかどうかも分からないようにする)。
        $this->actingAs($other)->post(route('notifications.markAsRead', $notification->id))->assertNotFound();

        // 【解説】持ち主の通知は未読のまま。
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_unknown_id_returns_404(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $this->actingAs($student)->post(route('notifications.markAsRead', 'no-such-id'))->assertNotFound();
    }

    public function test_falls_back_to_index_when_url_is_missing_or_external(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        // 【解説】url がない通知 / 外部サイトの URL / '//' で始まる URL は、すべて通知一覧に戻す(オープンリダイレクト対策)。
        $cases = [
            $this->createNotification($student, ['url' => null]),
            $this->createNotification($student, ['url' => 'https://evil.example.com']),
            $this->createNotification($student, ['url' => '//evil.example.com']),
        ];

        foreach ($cases as $notification) {
            $this->actingAs($student)
                ->post(route('notifications.markAsRead', $notification->id))
                ->assertRedirect(route('notifications.index'));
            $this->assertNotNull($notification->fresh()->read_at);
        }
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $notification = $this->createNotification(User::factory()->student()->inProgress()->create());

        $this->post(route('notifications.markAsRead', $notification->id))->assertRedirect(route('login'));
        $this->assertNull($notification->fresh()->read_at);
    }
}
