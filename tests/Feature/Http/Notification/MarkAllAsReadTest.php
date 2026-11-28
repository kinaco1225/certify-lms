<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Notification;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `POST /notifications/read-all`: 本人の未読だけをまとめて既読にする(他人の未読は変わらない)。
 */
class MarkAllAsReadTest extends TestCase
{
    use NotificationTestHelpers;
    use RefreshDatabase;

    public function test_marks_only_own_unread_notifications(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $mine = [$this->createNotification($student), $this->createNotification($student)];
        $other = User::factory()->coach()->inProgress()->create();
        $othersUnread = $this->createNotification($other);

        $this->actingAs($student)
            ->post(route('notifications.markAllAsRead'))
            ->assertRedirect(route('notifications.index'))
            ->assertSessionHas('success', 'すべての通知を既読にしました。');

        foreach ($mine as $notification) {
            $this->assertNotNull($notification->fresh()->read_at);
        }
        // 【解説】他人の未読はそのまま。
        $this->assertNull($othersUnread->fresh()->read_at);
        $this->assertSame(0, $student->unreadNotifications()->count());
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->post(route('notifications.markAllAsRead'))->assertRedirect(route('login'));
    }
}
