<?php

declare(strict_types=1);

namespace App\Notifications\QaBoard;

use App\Models\QaReply;
use App\Notifications\BaseNotification;

/**
 * 質問掲示板で自分の質問に回答がついたときに、質問の投稿者へ送る通知。
 */
// 【解説】BaseNotification を継承し、4 つのメソッド(type / title / message / url)だけを実装する。
final class QaReplyReceivedNotification extends BaseNotification
{
    // 【解説】コンストラクタの引数に private readonly を付けると、「プロパティの宣言 + 代入」を 1 行で書ける
    // 【解説】(コンストラクタ・プロモーション)。readonly = 後から書き換えられない。
    public function __construct(private readonly QaReply $reply)
    {
        // 【解説】loadMissing = まだ読み込んでいなければ関連データを読み込む(スレッドと回答者の名前を使うため)。
        $this->reply->loadMissing(['thread', 'user']);
    }

    public function type(): string
    {
        return 'qa_reply_received';
    }

    public function title(): string
    {
        return '質問に回答がつきました';
    }

    public function message(): string
    {
        // 【解説】ダブルクォートの文字列の中では {$変数->プロパティ} と書くと値が埋め込まれる。
        return "「{$this->reply->thread->title}」に {$this->reply->user->name} さんが回答しました。";
    }

    public function url(): string
    {
        // 【解説】route(名前, パラメータ, false) の第 3 引数 false = ドメインなしのパス(/qa-board/...)で作る。
        // 【解説】'#reply-'.id を付けると、詳細画面のその回答の位置まで自動でスクロールする(回答投稿後の遷移と同じ形)。
        return route('qa-board.show', $this->reply->thread, false).'#reply-'.$this->reply->id;
    }
}
