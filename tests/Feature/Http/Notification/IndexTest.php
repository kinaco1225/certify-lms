<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Notification;

use App\Models\User;
use App\UseCases\Notification\IndexAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `GET /notifications`: 本人宛の通知だけを新着順に表示 / タブ(全件・未読) / 20 件ごとのページ分割 / 未読件数。
 */
class IndexTest extends TestCase
{
    use NotificationTestHelpers;
    use RefreshDatabase;

    public function test_shows_only_own_notifications_newest_first(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $old = $this->createNotification($student, ['title' => '古い通知'], createdAt: now()->subDay());
        $new = $this->createNotification($student, ['title' => '新しい通知']);
        // 【解説】他人宛の通知。一覧に出てはいけない。
        $this->createNotification(User::factory()->student()->inProgress()->create(), ['title' => '他人の通知']);

        $response = $this->actingAs($student)->get(route('notifications.index'));

        $response->assertOk()
            ->assertSee('新しい通知')
            ->assertSee('古い通知')
            ->assertDontSee('他人の通知');
        // 【解説】新しい順に並んでいること。
        $this->assertSame([$new->id, $old->id], $response->viewData('notifications')->pluck('id')->all());
        // 【解説】行のフォームの送信先が既読化のルートになっていること。
        $response->assertSee(route('notifications.markAsRead', $new->id));
    }

    public function test_unread_tab_and_unread_count(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $unread = $this->createNotification($coach, ['title' => '未読の通知']);
        $this->createNotification($coach, ['title' => '既読の通知'], read: true);

        $all = $this->actingAs($coach)->get(route('notifications.index'));
        $all->assertOk()->assertSee('現在 <span class="font-semibold text-primary-700">1</span> 件の未読通知があります。', false);
        $this->assertSame('all', $all->viewData('tab'));
        $this->assertCount(2, $all->viewData('notifications'));
        $this->assertSame(1, $all->viewData('unreadCount'));

        $unreadTab = $this->actingAs($coach)->get(route('notifications.index', ['tab' => 'unread']));
        $this->assertSame('unread', $unreadTab->viewData('tab'));
        $this->assertSame([$unread->id], $unreadTab->viewData('notifications')->pluck('id')->all());
        $unreadTab->assertDontSee('既読の通知');
    }

    public function test_paginates_by_20(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        for ($i = 0; $i < IndexAction::PER_PAGE + 1; $i++) {
            $this->createNotification($student, createdAt: now()->subMinutes($i));
        }

        $page1 = $this->actingAs($student)->get(route('notifications.index'))->viewData('notifications');
        $page2 = $this->actingAs($student)->get(route('notifications.index', ['page' => 2]))->viewData('notifications');

        $this->assertCount(20, $page1);
        $this->assertCount(1, $page2);
    }

    public function test_empty_state_and_graduated_user_can_open(): void
    {
        // 【解説】卒業したユーザーも通知一覧は開ける(active-learning を付けていない)。通知が 0 件なら空表示。
        $graduated = User::factory()->student()->graduated()->create();

        $this->actingAs($graduated)->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('通知はありません')
            ->assertSee('未読の通知はありません。');
    }

    public function test_invalid_tab_is_rejected(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $this->actingAs($student)
            ->from(route('notifications.index'))
            ->get(route('notifications.index', ['tab' => 'xxx']))
            ->assertRedirect(route('notifications.index'))
            ->assertSessionHasErrors(['tab' => 'タブは「全件」か「未読のみ」を選択してください。']);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('notifications.index'))->assertRedirect(route('login'));
    }

    public function test_sidebar_shows_notification_link(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $this->createNotification($student);

        // 【解説】ルートを登録したので、サイドバーに「通知」メニューが出る(Route::has で判定されている)。
        $this->actingAs($student)->get(route('notifications.index'))
            ->assertSee(route('notifications.index'))
            ->assertSee('通知 (1 件未読)');
    }
}
