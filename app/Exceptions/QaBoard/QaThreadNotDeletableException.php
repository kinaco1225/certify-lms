<?php

declare(strict_types=1);

namespace App\Exceptions\QaBoard;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 投稿者による質問スレッド削除時、既に回答が付いている場合に throw される。
 *
 * 通常は QaThreadPolicy::delete で事前に拒否されるが、認可判定後に回答が投稿された場合の防御として
 * QaThread/DestroyAction で再確認する(管理者のモデレーション削除には適用しない)。
 */
final class QaThreadNotDeletableException extends ConflictHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('回答が付いた質問は削除できません。', $previous);
    }
}
