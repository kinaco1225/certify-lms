<?php

declare(strict_types=1);

namespace App\UseCases\Notification;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * 本人宛の通知を 1 件既読にし、遷移先 URL を返すユースケース。
 *
 * 通知は $user->notifications() の中からのみ探すため、他人宛の通知 ID を指定すると 404(ModelNotFoundException)になる。
 * 遷移先は通知データの url(サイト内パス)。'/' で始まらない値(外部 URL 等)や未設定の場合は通知一覧へ戻す。
 */
final class MarkAsReadAction
{
    public function __invoke(User $user, string $notificationId): string
    {
        // 【解説】findOrFail(id) = 見つからなければ 404 にする。自分の通知の中から探すので、他人の通知も 404 になる。
        // 【解説】(ルートモデルバインディングを使うと、他人の通知まで取り出せてしまうので、あえてこの書き方にしている)
        $notification = $user->notifications()->findOrFail($notificationId);

        // 【解説】markAsRead() = read_at に今の日時を入れる(Laravel 標準のメソッド)。すでに既読なら何もしない。
        $notification->markAsRead();

        // 【解説】data は配列として取り出せる(DatabaseNotification が JSON を自動で配列に変換する)。
        $url = $notification->data['url'] ?? null;

        // 【解説】サイト内のパス('/' で始まり、'//' で始まらない)だけを移動先として使う。
        // 【解説】'//evil.example' のような値は外部サイトへ飛ばされる(オープンリダイレクト)ので、通知一覧に戻す。
        if (is_string($url) && Str::startsWith($url, '/') && ! Str::startsWith($url, '//')) {
            return $url;
        }

        return route('notifications.index', absolute: false);
    }
}
