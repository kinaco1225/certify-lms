<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PlanStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 受講プラン マスタ。duration_days(受講期間) + default_meeting_quota(初期付与面談回数) のセット。
 *
 * 価格情報は LMS 内では保持しない(決済は LMS 外で完結)。受講生招待時に Plan を指定して User.plan_* 系カラムを初期化する。
 *
 * 関連: User(plan_id) / UserPlanLog(履歴) / Admin(created_by / updated_by)
 */
class Plan extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'name',
        'description',
        'duration_days',
        'default_meeting_quota',
        'status',
        'sort_order',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'status' => PlanStatus::class,
        'duration_days' => 'integer',
        'default_meeting_quota' => 'integer',
        'sort_order' => 'integer',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<UserPlanLog, $this>
     */
    public function userPlanLogs(): HasMany
    {
        return $this->hasMany(UserPlanLog::class);
    }

    /**
     * 受講者(ソフトデリート済みを含む)またはプラン履歴から参照されているか。
     *
     * users.plan_id / user_plan_logs.plan_id はどちらも restrictOnDelete のため、参照が 1 件でもあると
     * DB が削除を拒否する。削除可否の判定(PlanPolicy::delete / Plan\DestroyAction)で共通に使う。
     */
    // 【解説】Policy と DestroyAction の 2 か所で同じ判定を使うので、モデルに 1 つだけ書いておく(判定の食い違いを防ぐ)。
    public function hasReferences(): bool
    {
        // 【解説】$this->users() のようにカッコ付きで呼ぶと、データではなく「SQL を組み立てるクエリ」が返る。
        // 【解説】withTrashed() = ソフトデリート(論理削除)されたユーザーも含める。
        // 【解説】  退会などで deleted_at が入ったユーザーも行は DB に残っていて、外部キーの制約が効くため含める必要がある。
        // 【解説】exists() = 1 件でもあれば true。件数を数える count() より速い(見つかった時点で終わる)。
        // 【解説】|| = 「または」。左側が true なら右側は実行されない。
        return $this->users()->withTrashed()->exists()
            || $this->userPlanLogs()->exists();
    }

    /**
     * @param Builder<Plan> $query
     *
     * @return Builder<Plan>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', PlanStatus::Published);
    }

    /**
     * @param Builder<Plan> $query
     *
     * @return Builder<Plan>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderByDesc('created_at');
    }
}
