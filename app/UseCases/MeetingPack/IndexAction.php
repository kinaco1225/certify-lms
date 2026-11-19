<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Models\MeetingPack;
// 【解説】LengthAwarePaginator = 「全部で何件・今何ページ目」まで分かるページ分割済みの結果。Blade の $plans->total() などで使う。
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * admin 用の面談パック一覧を、パック名のキーワード検索 + 状態フィルタ付きで取得するユースケース。
 * 並び順は sort_order 昇順 → 作成日時の降順(MeetingPack::scopeOrdered)→ id 降順(ページ間の重複・欠落を防ぐ)。
 */
// 【解説】Action(UseCase)= 1 つの処理だけを担当するクラス。Controller から呼ばれ、DB の取得や更新を行う。
final class IndexAction
{
    // 【解説】const = 定数(変わらない値)。1 ページの件数を名前付きで定義しておくと、テストからも self::PER_PAGE で参照できる。
    public const PER_PAGE = 20;

    // 【解説】__invoke = このクラスのインスタンスを関数のように呼べるようにする特別なメソッド。
    // 【解説】Controller では $action($keyword, $status) と書くだけで、このメソッドが実行される。
    // 【解説】?string = 文字列 または null。未指定(絞り込みなし)のときは null が来る。
    // 【解説】?MeetingPackStatus = 状態の Enum または null。文字列ではなく Enum で受け取るので、存在しない状態は渡せない。
    public function __invoke(?string $keyword, ?MeetingPackStatus $status): LengthAwarePaginator
    {
        // 【解説】MeetingPack::query() = meeting_packs テーブルへの SQL を組み立て始める。下の -> で条件を足していく。
        return MeetingPack::query()
            // 【解説】when(条件, 処理) = 条件が true のときだけ処理(where)を足す。false なら何もしない。
            // 【解説】キーワードが入力されているときだけ、名前の部分一致で絞り込む。
            ->when(
                $keyword !== null && $keyword !== '',
                // 【解説】fn ($query) => ... はアロー関数(短く書ける無名関数)。$query に組み立て中の SQL が渡される。
                // 【解説】LIKE '%5%' = 「5 を含む」。'%'.$keyword.'%' の . は PHP の文字列連結。
                fn ($query) => $query->where('name', 'LIKE', '%'.$keyword.'%'),
            )
            // 【解説】状態が選ばれているときだけ、その状態で絞り込む。
            // 【解説】$status->value で Enum から DB に保存されている文字列('published' など)を取り出す。
            ->when(
                $status !== null,
                fn ($query) => $query->where('status', $status->value),
            )
            // 【解説】ordered() は MeetingPack モデルの scopeOrdered(既存)。sort_order の小さい順 → 作成日時の新しい順に並べる。
            // 【解説】モデルに scopeXxx というメソッドを作ると、->xxx() として呼べる(ローカルスコープ)。
            ->ordered()
            // 【解説】並び順も作成日時も同じデータがあると順番が毎回変わり得るので、最後に id で順番を固定する。
            ->orderByDesc('id')
            // 【解説】paginate(20) = 20 件ずつに分けて取得。URL の ?page=2 を自動で読み取ってくれる。
            ->paginate(self::PER_PAGE)
            // 【解説】withQueryString() = ページ番号のリンクに、今の絞り込み条件(?keyword=...&status=...)を引き継ぐ。
            ->withQueryString();
    }
}
