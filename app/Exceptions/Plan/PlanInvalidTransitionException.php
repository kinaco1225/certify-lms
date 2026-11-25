<?php

declare(strict_types=1);

namespace App\Exceptions\Plan;

// 【解説】ConflictHttpException = HTTP ステータス 409(今の状態と矛盾している)を表す例外クラス。
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * プランの状態遷移(publish / archive / unarchive)が不正な開始状態から呼ばれた際の例外(HTTP 409)。
 * バリエーションごとに static factory でメッセージを生成する(MeetingPackInvalidTransitionException と同じ形)。
 */
// 【解説】この例外が throw されると、app/Exceptions/Handler.php が「元の画面に戻して error メッセージを表示」に変換する。
final class PlanInvalidTransitionException extends ConflictHttpException
{
    // 【解説】static メソッドなので、使う側は throw PlanInvalidTransitionException::forPublish(); と書く。
    // 【解説】操作ごとにメソッドを分け、メッセージの文言をこのファイルに集めている。
    public static function forPublish(): self
    {
        return new self('下書きのプランのみ公開できます。');
    }

    public static function forArchive(): self
    {
        return new self('公開中のプランのみアーカイブできます。');
    }

    public static function forUnarchive(): self
    {
        return new self('アーカイブ済みのプランのみ下書きに戻せます。');
    }

    // 【解説】コンストラクタを private にして、必ず上の static メソッドを通して作らせる(メッセージがバラバラにならない)。
    private function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, $previous);
    }
}
