<?php

declare(strict_types=1);

namespace App\Notifications\Mentoring;

use App\Models\Meeting;
use App\Notifications\BaseNotification;

/**
 * 受講生が面談を予約したときに、割り当てられたコーチへ送る通知。
 */
final class MeetingReservedNotification extends BaseNotification
{
    public function __construct(private readonly Meeting $meeting)
    {
        // 【解説】メッセージに受講生の名前を使うので、受講生(student リレーション)を読み込んでおく。
        $this->meeting->loadMissing('student');
    }

    public function type(): string
    {
        return 'meeting_reserved';
    }

    public function title(): string
    {
        return '面談が予約されました';
    }

    public function message(): string
    {
        // 【解説】scheduled_at は日時(Carbon)なので、format('Y/m/d H:i') で「2026/11/25 10:00」の形の文字列にする。
        $at = $this->meeting->scheduled_at->format('Y/m/d H:i');

        return "{$this->meeting->student->name} さんが {$at} の面談を予約しました。";
    }

    public function url(): string
    {
        return route('meetings.show', $this->meeting, false);
    }
}
