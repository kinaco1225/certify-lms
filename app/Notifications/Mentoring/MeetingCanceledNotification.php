<?php

declare(strict_types=1);

namespace App\Notifications\Mentoring;

use App\Models\Meeting;
use App\Models\User;
use App\Notifications\BaseNotification;

/**
 * 面談がキャンセルされたときに、キャンセルしなかった側(受講生 ↔ コーチ)へ送る通知。
 */
final class MeetingCanceledNotification extends BaseNotification
{
    // 【解説】$canceledBy = キャンセル操作をした人。メッセージに名前を出すために受け取る。
    public function __construct(
        private readonly Meeting $meeting,
        private readonly User $canceledBy,
    ) {}

    public function type(): string
    {
        return 'meeting_canceled';
    }

    public function title(): string
    {
        return '面談がキャンセルされました';
    }

    public function message(): string
    {
        $at = $this->meeting->scheduled_at->format('Y/m/d H:i');

        return "{$this->canceledBy->name} さんが {$at} の面談をキャンセルしました。";
    }

    public function url(): string
    {
        return route('meetings.show', $this->meeting, false);
    }
}
