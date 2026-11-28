<?php

declare(strict_types=1);

namespace App\UseCases\Notification;

use App\Models\User;

/**
 * 本人宛の未読通知をまとめて既読にするユースケース。既読にした件数を返す。
 */
final class MarkAllAsReadAction
{
    public function __invoke(User $user): int
    {
        // 【解説】unreadNotifications() = このユーザー宛で、まだ未読(read_at が null)の通知。
        // 【解説】update([...]) = 1 回の SQL でまとめて更新する(1 件ずつ更新するより速い)。戻り値は更新した件数。
        return $user->unreadNotifications()->update(['read_at' => now()]);
    }
}
