<?php

declare(strict_types=1);

namespace App\Exceptions\QaBoard;

use App\Enums\QaThreadStatus;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 質問スレッドの解決状態遷移(Open ⇄ Resolved)が現在の状態から許可されない場合に throw される。
 *
 * 画面上は状態に応じてボタンを出し分けているため、二重送信や別タブでの操作時の防御として機能する。
 * 利用先: QaThread/ResolveAction / UnresolveAction。
 */
final class QaThreadInvalidTransitionException extends ConflictHttpException
{
    public static function from(QaThreadStatus $from, QaThreadStatus $to): self
    {
        return new self(sprintf(
            'この質問は既に「%s」のため、「%s」に変更できません。',
            $from->label(),
            $to->label(),
        ));
    }

    private function __construct(string $message)
    {
        parent::__construct($message);
    }
}
