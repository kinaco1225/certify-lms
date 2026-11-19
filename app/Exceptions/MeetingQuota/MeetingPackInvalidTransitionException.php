<?php

declare(strict_types=1);

namespace App\Exceptions\MeetingQuota;

// 【解説】ConflictHttpException = HTTP ステータス 409(Conflict = 今の状態と矛盾している)を表す例外クラス。
// 【解説】Symfony(Laravel の土台になっているライブラリ)が用意しているものを読み込む。
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 面談パックの状態遷移(publish / archive / unarchive)が不正な開始状態から呼ばれた際の例外(HTTP 409)。
 * バリエーションごとに static factory でメッセージを生成する(CertificationInvalidTransitionException と同じ形)。
 */
// 【解説】例外(Exception)= 「これ以上処理を続けられない」ときに throw(投げる)もの。throw した時点で処理が止まる。
// 【解説】extends ConflictHttpException なので、この例外が投げられると Laravel は 409 として扱う。
// 【解説】さらにこのプロジェクトの app/Exceptions/Handler.php が 409 を「元の画面に戻して、エラーメッセージを表示」に変換する。
// 【解説】final = このクラスを継承(extends)して別のクラスを作ることを禁止する。
final class MeetingPackInvalidTransitionException extends ConflictHttpException
{
    // 【解説】static メソッド = new しなくても「クラス名::メソッド名()」で呼べるメソッド。
    // 【解説】使う側は throw MeetingPackInvalidTransitionException::forPublish(); と書く。
    // 【解説】操作ごとにメソッドを分けると、メッセージの文言をこのファイルに集められ、呼ぶ側も読みやすい。
    // 【解説】戻り値の型 self = 「このクラス自身」(MeetingPackInvalidTransitionException)のこと。
    public static function forPublish(): self
    {
        return new self('下書きの面談パックのみ公開できます。');
    }

    public static function forArchive(): self
    {
        return new self('公開中の面談パックのみアーカイブできます。');
    }

    public static function forUnarchive(): self
    {
        return new self('アーカイブ済みの面談パックのみ下書きに戻せます。');
    }

    // 【解説】コンストラクタ(new したときに最初に実行されるメソッド)を private にしている。
    // 【解説】→ 外から new MeetingPackInvalidTransitionException('好きな文言') と書けなくなり、
    // 【解説】  必ず上の forPublish() などを通して作らせることができる(メッセージがバラバラにならない)。
    // 【解説】?\Throwable $previous = null … 「元になった別の例外」を受け取れる引数。? は null も可、= null は省略可の意味。
    private function __construct(string $message, ?\Throwable $previous = null)
    {
        // 【解説】parent:: = 親クラス(ConflictHttpException)のコンストラクタを呼び、メッセージを渡す。
        parent::__construct($message, $previous);
    }
}
