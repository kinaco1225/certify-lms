<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 業務イベント通知(アプリ内 + メール)の共通親クラス。
 *
 * 子クラスは type / title / message / url の 4 つだけを実装する。DB に保存するデータの形
 * (notification_type / title / message / url)とメールの体裁はここで統一し、
 * 画面(notifications/_partials/notification-row.blade.php)が参照するキーと揃える。
 *
 * 送信は必ず App\Services\NotificationService 経由で行う(受信対象の絞り込み + コミット後送信)。
 */
// 【解説】abstract class = それ自体は new できない「ひな形」のクラス。子クラスが extends して使う。
// 【解説】Notification(Laravel 標準)を継承しているので、$user->notify(new 子クラス(...)) で送れる。
abstract class BaseNotification extends Notification
{
    // 【解説】abstract メソッド = 中身を書かず「子クラスで必ず実装してね」と約束させるメソッド。
    // 【解説】画面のアイコン出し分けに使う種別(例: 'qa_reply_received')。
    abstract public function type(): string;

    // 【解説】通知のタイトル(一覧の太字部分 / メールの件名)。
    abstract public function title(): string;

    // 【解説】通知の本文(一覧の 2 行目 / メールの本文)。
    abstract public function message(): string;

    // 【解説】通知をクリックしたときの移動先。ドメインなしのパス(例: '/qa-board/01xxx')で返す。
    abstract public function url(): string;

    /**
     * @return array<int, string>
     */
    // 【解説】via() = どの経路で送るか。'database' は notifications テーブルへの保存、'mail' はメール送信。
    // 【解説】$notifiable は通知を受け取る人(ここでは User)。Laravel が自動で渡してくる。
    public function via(User $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * @return array{notification_type: string, title: string, message: string, url: string}
     */
    // 【解説】toArray() = 'database' 経路で notifications.data カラムに保存する中身。
    // 【解説】画面は $notification->data['title'] のようにこのキーで読む。
    public function toArray(User $notifiable): array
    {
        return [
            'notification_type' => $this->type(),
            'title' => $this->title(),
            'message' => $this->message(),
            'url' => $this->url(),
        ];
    }

    // 【解説】toMail() = 'mail' 経路で送るメールの中身。MailMessage は Laravel 標準の見た目でメールを組み立てる。
    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            // 【解説】件名。
            ->subject('【Certify LMS】'.$this->title())
            // 【解説】最初のあいさつ行。
            ->greeting(($notifiable->name ?? '').' さん')
            // 【解説】本文 1 行。
            ->line($this->message())
            // 【解説】ボタン。url() はパスなので、url(...) ヘルパーで「http://localhost:8000/...」の完全な URL にする。
            ->action('Certify LMS で確認する', url($this->url()))
            // 【解説】最後の署名。
            ->salutation('Certify LMS 運営チーム');
    }
}
