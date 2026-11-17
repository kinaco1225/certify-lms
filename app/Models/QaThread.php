<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QaThreadStatus;
use App\Enums\UserRole;
use Database\Factories\QaThreadFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 質問掲示板の質問スレッド。資格(Certification)に紐付く公開 Q&A。
 *
 * 閲覧範囲は scopeVisibleTo に集約する:
 * - admin: 全資格(公開停止中を含む)
 * - student: 公開中の資格すべて(受講していない資格も含む)
 * - coach: 公開中かつ担当資格のみ
 *
 * 関連: Certification / User(投稿者) / QaReply
 */
class QaThread extends Model
{
    /** @use HasFactory<QaThreadFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'certification_id',
        'user_id',
        'title',
        'body',
        'status',
        'resolved_at',
    ];

    protected $casts = [
        'status' => QaThreadStatus::class,
        'resolved_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Certification, $this>
     */
    public function certification(): BelongsTo
    {
        return $this->belongsTo(Certification::class);
    }

    /**
     * 投稿者(受講生)。
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 配下の回答(投稿順)。
     *
     * @return HasMany<QaReply, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(QaReply::class)->oldest();
    }

    public function isResolved(): bool
    {
        return $this->status === QaThreadStatus::Resolved;
    }

    /**
     * 閲覧者ロールに応じて閲覧可能なスレッドへ絞り込む。
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return match ($user->role) {
            UserRole::Admin => $query,
            UserRole::Student => $query->whereHas(
                'certification',
                fn (Builder $q) => $q->published(),
            ),
            UserRole::Coach => $query->whereHas(
                'certification',
                fn (Builder $q) => $q->published()->assignedTo($user),
            ),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * 一覧の解決状態フィルタ。画面のパラメータ値(unresolved / resolved)を Enum に読み替える。
     */
    public function scopeFilterStatus(Builder $query, ?string $status): Builder
    {
        return match ($status) {
            'unresolved' => $query->where('status', QaThreadStatus::Open->value),
            'resolved' => $query->where('status', QaThreadStatus::Resolved->value),
            default => $query,
        };
    }

    public function scopeFilterCertification(Builder $query, ?string $certificationId): Builder
    {
        if ($certificationId === null || $certificationId === '') {
            return $query;
        }

        return $query->where('certification_id', $certificationId);
    }

    /**
     * タイトル / 本文の部分一致検索。OR 条件は他の絞り込みと混ざらないようグルーピングする。
     */
    public function scopeKeyword(Builder $query, ?string $keyword): Builder
    {
        if ($keyword === null || $keyword === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($keyword) {
            $q->where('title', 'LIKE', '%'.$keyword.'%')
                ->orWhere('body', 'LIKE', '%'.$keyword.'%');
        });
    }
}
