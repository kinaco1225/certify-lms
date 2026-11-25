<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Enums\PlanStatus;
use App\Models\Plan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * admin 用のプラン一覧を、プラン名のキーワード検索 + 状態フィルタ付きで取得するユースケース。
 * 各行の受講者数は withCount('users') で一括取得する(N+1 を防ぐ)。
 * 並び順は sort_order 昇順 → 作成日時の降順(Plan::scopeOrdered)→ id 降順(ページ間の重複・欠落を防ぐ)。
 */
// 【解説】面談パックの IndexAction と同じ形。違いは withCount('users') で受講者数も取ること。
final class IndexAction
{
    // 【解説】1 ページの件数。テストからも self::PER_PAGE(IndexAction::PER_PAGE)で参照できる。
    public const PER_PAGE = 20;

    // 【解説】Controller からは $action($keyword, $status) と関数のように呼ばれる(__invoke)。
    public function __invoke(?string $keyword, ?PlanStatus $status): LengthAwarePaginator
    {
        return Plan::query()
            // 【解説】when(条件, 処理) = 条件が true のときだけ処理を足す。キーワードがあれば名前の部分一致で絞り込む。
            ->when(
                $keyword !== null && $keyword !== '',
                fn ($query) => $query->where('name', 'LIKE', '%'.$keyword.'%'),
            )
            // 【解説】状態が選ばれていれば、その状態で絞り込む($status->value で 'draft' などの文字列を取り出す)。
            ->when(
                $status !== null,
                fn ($query) => $query->where('status', $status->value),
            )
            // 【解説】withCount('users') = 各プランの受講者数を、サブクエリで一緒に数えて users_count という値で持たせる。
            // 【解説】Blade の $plan->users_count で使う。1 行ずつ数えに行かないので、件数が増えてもクエリは 1 回で済む。
            // 【解説】users リレーションはソフトデリートされたユーザー(退会者)を自動で除外する。
            ->withCount('users')
            // 【解説】ordered() = Plan モデルの scopeOrdered(並び順の小さい順 → 作成日時の新しい順)。
            ->ordered()
            // 【解説】並び順も作成日時も同じプランがあっても順番が変わらないよう、最後に id で固定する。
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            // 【解説】ページ番号のリンクに、今の絞り込み条件(?keyword=...&status=...)を引き継ぐ。
            ->withQueryString();
    }
}
