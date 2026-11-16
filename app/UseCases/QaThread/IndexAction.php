<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Models\QaThread;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * 質問掲示板のスレッド一覧を絞り込み付きで新着順に取得するユースケース。
 *
 * 閲覧範囲は QaThread::scopeVisibleTo(admin = 全資格 / student = 公開中資格 / coach = 公開中の担当資格)。
 * 一覧カードが参照する投稿者・資格・回答数は Eager Loading + withCount で一括取得し、
 * スレッド件数が増えても発行クエリ数が増えないようにする。
 */
final class IndexAction
{
    public const PER_PAGE = 20;

    /**
     * @param array{status?: string, certification_id?: string, keyword?: string} $filters
     */
    public function __invoke(User $viewer, array $filters = []): LengthAwarePaginator
    {
        return QaThread::query()
            ->visibleTo($viewer)
            ->filterStatus($filters['status'] ?? null)
            ->filterCertification($filters['certification_id'] ?? null)
            ->keyword($filters['keyword'] ?? null)
            ->with(['user', 'certification'])
            ->withCount('replies')
            ->latest()
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }
}
