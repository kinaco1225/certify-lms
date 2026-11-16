<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\QaReplyFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 質問スレッド(QaThread)への回答。テキスト本文のみ。
 *
 * 投稿できるのは受講生 / コーチ。編集は投稿者本人のみ、削除は投稿者本人または管理者(モデレーション)。
 *
 * 関連: QaThread / User(回答者)
 */
class QaReply extends Model
{
    /** @use HasFactory<QaReplyFactory> */
    use HasFactory, HasUlids;

    /**
     * 回答の投稿 / 編集 / 削除で親スレッドの updated_at を更新し、一覧の「最終活動」表示に反映する。
     *
     * @var array<int, string>
     */
    protected $touches = ['thread'];

    protected $fillable = [
        'qa_thread_id',
        'user_id',
        'body',
    ];

    /**
     * @return BelongsTo<QaThread, $this>
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(QaThread::class, 'qa_thread_id');
    }

    /**
     * 回答者(受講生 / コーチ)。
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
