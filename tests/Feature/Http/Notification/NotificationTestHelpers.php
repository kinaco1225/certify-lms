<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Notification;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

/**
 * 通知画面のテスト用ヘルパー。notifications テーブルへ直接 1 件保存する(メールは送らない)。
 */
// 【解説】trait = 複数のクラスで共通のメソッドを使い回すための仕組み。テストクラスで use して使う。
trait NotificationTestHelpers
{
    /**
     * @param array<string, mixed> $data
     */
    private function createNotification(User $user, array $data = [], bool $read = false, ?\DateTimeInterface $createdAt = null): DatabaseNotification
    {
        $createdAt ??= now();

        return $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\QaBoard\\QaReplyReceivedNotification',
            // 【解説】$data で渡した項目だけ上書きする(配列の + は左側を優先)。
            'data' => $data + [
                'notification_type' => 'qa_reply_received',
                'title' => '質問に回答がつきました',
                'message' => 'テストのメッセージ',
                'url' => '/qa-board',
            ],
            'read_at' => $read ? now() : null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
