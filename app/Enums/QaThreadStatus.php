<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * 質問掲示板のスレッド (QaThread) の解決状態を表す Enum。
 *
 * - [*] → Open: 受講生がスレッドを投稿
 * - Open → Resolved: 投稿者本人が解決済にマーク(resolved_at を記録)
 * - Resolved → Open: 投稿者本人が未解決に戻す(resolved_at をクリア)
 *
 * 管理者による解決マークの代行はできない。
 */
enum QaThreadStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::Open => '未解決',
            self::Resolved => '解決済',
        };
    }

    /**
     * `<x-badge>` 等で利用するカラートークン名。
     */
    public function color(): string
    {
        return match ($this) {
            self::Open => 'warning',
            self::Resolved => 'success',
        };
    }
}
