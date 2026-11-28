<?php

declare(strict_types=1);

namespace App\Notifications\Chat;

use App\Models\ChatMessage;
use App\Notifications\BaseNotification;
use Illuminate\Support\Str;

/**
 * チャットルームに新しいメッセージが投稿されたときに、送信者以外のメンバーへ送る通知。
 */
final class ChatMessageReceivedNotification extends BaseNotification
{
    // 【解説】本文のプレビューとして表示する最大文字数。
    private const PREVIEW_LENGTH = 50;

    public function __construct(private readonly ChatMessage $message)
    {
        $this->message->loadMissing('sender');
    }

    public function type(): string
    {
        return 'chat_message_received';
    }

    public function title(): string
    {
        return '新しいチャットメッセージ';
    }

    public function message(): string
    {
        // 【解説】本文が 50 文字を超えたら、先頭 50 文字で切って「...」を付ける。
        // 【解説】Str::limit() は「表示幅」で数える(全角 1 文字 = 幅 2)ため、日本語だと 25 文字で切れてしまう。
        // 【解説】そこで Str::length()(文字数)と Str::substr()(文字数で切り出し)を使い、文字数で 50 文字にしている。
        $body = $this->message->body;
        $preview = Str::length($body) > self::PREVIEW_LENGTH
            ? Str::substr($body, 0, self::PREVIEW_LENGTH).'...'
            : $body;

        return "{$this->message->sender->name} さん:{$preview}";
    }

    public function url(): string
    {
        return route('chat.show', $this->message->chat_room_id, false);
    }
}
