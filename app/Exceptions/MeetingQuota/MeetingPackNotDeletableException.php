<?php

declare(strict_types=1);

namespace App\Exceptions\MeetingQuota;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 公開中の面談パックを削除しようとした際の例外(HTTP 409)。
 * `MeetingPack\DestroyAction` が「公開中は削除不可(購入履歴の整合性を守る)」のルールから throw する。
 */
// 【解説】こちらはメッセージが 1 種類だけなので、static メソッドは作らず、コンストラクタで固定の文言を渡している。
// 【解説】使う側は throw new MeetingPackNotDeletableException; と書く(引数なしで new できる)。
final class MeetingPackNotDeletableException extends ConflictHttpException
{
    // 【解説】public なので外から new できる。メッセージはこの中で決め打ちなので、呼ぶ側は文言を気にしなくてよい。
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('公開中の面談パックは削除できません。', $previous);
    }
}
