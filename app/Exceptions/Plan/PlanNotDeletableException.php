<?php

declare(strict_types=1);

namespace App\Exceptions\Plan;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 削除条件を満たさないプランを削除しようとした際の例外(HTTP 409)。
 * `Plan\DestroyAction` が「下書き かつ 受講者・プラン履歴の参照なし」のルールから throw する。
 */
// 【解説】面談パックの NotDeletable は理由が 1 つだったが、プランは削除できない理由が 2 種類あるので、
// 【解説】PlanInvalidTransitionException と同じく static メソッドで理由ごとにメッセージを分けている。
final class PlanNotDeletableException extends ConflictHttpException
{
    // 【解説】理由①: 下書き以外(公開中 / アーカイブ)のプラン。
    public static function notDraft(): self
    {
        return new self('下書きのプランのみ削除できます。');
    }

    // 【解説】理由②: 受講者(ソフトデリート済みを含む)またはプラン履歴が紐づいている。
    public static function inUse(): self
    {
        return new self('受講者またはプラン履歴が紐づいているプランは削除できません。');
    }

    private function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, $previous);
    }
}
