<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Notifications\BaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * 業務イベント通知の送信窓口。すべての通知はここを通して送る。
 *
 * 1. 受信対象の絞り込み: User::canReceiveNotifications()(受講中の受講生 / コーチのみ)
 * 2. コミット後送信: DB::afterCommit() で、呼び出し元のトランザクションが確定してから送る
 *    (ロールバックされた業務データについて通知・メールが出ないようにする。トランザクション外なら即時送信)
 *
 * メールはキューを使わず同期送信する(非同期化は本チケットのスコープ外)。
 */
// 【解説】Service = 複数の機能(質問掲示板・面談・チャット)から共通で使う処理をまとめたクラス。
final class NotificationService
{
    /**
     * @param User|iterable<User> $recipients
     */
    // 【解説】User|iterable = 1 人(User)でも、複数人(配列やコレクション)でも受け取れる、という型の書き方。
    public function send(User|iterable $recipients, BaseNotification $notification): void
    {
        // 【解説】collect() でコレクション(配列を便利に扱うクラス)にする。1 人のときも [ $user ] として同じように扱う。
        $targets = collect($recipients instanceof User ? [$recipients] : $recipients)
            // 【解説】filter = 条件が true のものだけ残す。受け取る資格のあるユーザーだけにする。
            ->filter(fn (User $user) => $user->canReceiveNotifications())
            // 【解説】同じ人が 2 回入っていても 1 回だけ送るよう、id で重複を取り除く。
            ->unique('id')
            // 【解説】values() = キー(番号)を 0, 1, 2... に振り直す。
            ->values();

        // 【解説】送る相手がいなければ何もしない。
        if ($targets->isEmpty()) {
            return;
        }

        // 【解説】DB::afterCommit(処理) = 今のトランザクションが確定(コミット)した後に処理を実行する。
        // 【解説】  途中でエラーになりロールバックされた場合は実行されない → 「保存されていないのに通知だけ届く」を防ぐ。
        // 【解説】  トランザクションの外で呼ばれた場合は、すぐに実行される。
        DB::afterCommit(function () use ($targets, $notification): void {
            // 【解説】Notification::send(相手, 通知) = 複数人にまとめて通知を送る(via() の 'database' と 'mail' へ)。
            Notification::send($targets, $notification);
        });
    }
}
