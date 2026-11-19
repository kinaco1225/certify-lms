<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\MeetingPack\IndexRequest;
use App\Http\Requests\MeetingPack\StoreRequest;
use App\Http\Requests\MeetingPack\UpdateRequest;
use App\Models\MeetingPack;
use App\UseCases\MeetingPack\ArchiveAction;
use App\UseCases\MeetingPack\DestroyAction;
use App\UseCases\MeetingPack\IndexAction;
use App\UseCases\MeetingPack\PublishAction;
use App\UseCases\MeetingPack\ShowAction;
use App\UseCases\MeetingPack\StoreAction;
use App\UseCases\MeetingPack\UnarchiveAction;
use App\UseCases\MeetingPack\UpdateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * admin 用の面談パック(追加面談購入用 SKU)マスタ管理 Controller。
 * CRUD と状態遷移(publish / archive / unarchive)を提供する。
 *
 * 画面(resources/views/meeting-pack/management/*)は変数名 `$plans` / `$plan` を使うため、それに合わせて渡す。
 * 状態による可否は各 Action が判定し、不正な場合は 409 → Handler が元の画面に戻してエラーを表示する。
 */
// 【解説】Controller の役割は「受け取る → Action に任せる → 画面かリダイレクトを返す」だけ(薄く保つ)。
// 【解説】入力チェックは FormRequest、権限は Policy、DB の処理は Action が担当する。
class MeetingPackController extends Controller
{
    // 【解説】一覧画面(GET /admin/meeting-packs)。
    // 【解説】引数に型を書くと Laravel が自動で用意して渡してくれる(DI = 依存性注入)。
    // 【解説】IndexRequest は渡される前に authorize() と rules() が実行済み。IndexAction は new しなくても使える。
    // 【解説】戻り値の型 View = 画面を返す。
    public function index(IndexRequest $request, IndexAction $action): View
    {
        // 【解説】検索フォームに戻すための値(未指定なら空文字)。IndexRequest に作った自作メソッド。
        $filters = $request->filters();

        // 【解説】view('meeting-pack.management.index', [...]) = resources/views/meeting-pack/management/index.blade.php を表示する。
        // 【解説】フォルダの区切りは / ではなく . で書く。第 2 引数の配列のキーが Blade の変数名になる('plans' → $plans)。
        return view('meeting-pack.management.index', [
            // 【解説】$action(...) で IndexAction の __invoke が実行され、ページ分割された一覧が返る。
            'plans' => $action($filters['keyword'], $request->selectedStatus()),
            'keyword' => $filters['keyword'],
            'status' => $filters['status'],
        ]);
    }

    // 【解説】詳細画面(GET /admin/meeting-packs/{plan})。
    // 【解説】MeetingPack $plan … URL の {plan}(ULID)から Laravel が DB を検索してモデルを渡してくれる(ルートモデルバインディング)。
    // 【解説】引数名 $plan とルートのパラメータ名 {plan} が一致していることが条件。存在しない ID なら自動で 404 になる。
    public function show(MeetingPack $plan, ShowAction $action): View
    {
        // 【解説】authorize('view', $plan) = Policy の view() を呼び、false なら 403 にする。
        // 【解説】FormRequest を使わない画面(GET の詳細など)では、ここで権限チェックを書く。
        $this->authorize('view', $plan);

        return view('meeting-pack.management.show', [
            // 【解説】ShowAction で作成者・更新者を読み込んだモデルを渡す。
            'plan' => $action($plan),
        ]);
    }

    // 【解説】新規作成フォーム(GET /admin/meeting-packs/create)。フォームを表示するだけなので Action は使わない。
    public function create(): View
    {
        // 【解説】まだ存在しないデータなので、Policy にはモデルではなくクラス名(MeetingPack::class)を渡す。
        $this->authorize('create', MeetingPack::class);

        return view('meeting-pack.management.create');
    }

    // 【解説】新規作成(POST /admin/meeting-packs)。
    // 【解説】戻り値の型 RedirectResponse = 別の URL へ移動させる(画面は返さない)。
    public function store(StoreRequest $request, StoreAction $action): RedirectResponse
    {
        // 【解説】$request->user() = ログイン中の管理者。$request->validated() = チェック済みの入力値だけの配列。
        $plan = $action($request->user(), $request->validated());

        // 【解説】作成した面談パックの詳細画面へ移動する。
        // 【解説】->with('success', '...') = 次の画面に 1 回だけ表示するメッセージ(フラッシュメッセージ)をセッションに入れる。
        return redirect()
            ->route('admin.meeting-packs.show', $plan)
            ->with('success', '面談パックを作成しました。');
    }

    // 【解説】編集フォーム(GET /admin/meeting-packs/{plan}/edit)。
    public function edit(MeetingPack $plan): View
    {
        // 【解説】編集フォームを開けるかは、更新できるか(update)と同じ判定にする。
        $this->authorize('update', $plan);

        return view('meeting-pack.management.edit', [
            'plan' => $plan,
        ]);
    }

    // 【解説】更新(PATCH /admin/meeting-packs/{plan})。権限チェックは UpdateRequest の authorize() で済んでいる。
    public function update(MeetingPack $plan, UpdateRequest $request, UpdateAction $action): RedirectResponse
    {
        $action($plan, $request->user(), $request->validated());

        return redirect()
            ->route('admin.meeting-packs.show', $plan)
            ->with('success', '面談パックを更新しました。');
    }

    // 【解説】削除(DELETE /admin/meeting-packs/{plan})。
    public function destroy(MeetingPack $plan, DestroyAction $action): RedirectResponse
    {
        $this->authorize('delete', $plan);

        // 【解説】公開中なら DestroyAction が 409 の例外を投げる → ここから下は実行されず、元の画面にエラー表示で戻る。
        $action($plan);

        // 【解説】削除したデータの詳細画面はもう存在しないので、一覧画面へ移動する。
        return redirect()
            ->route('admin.meeting-packs.index')
            ->with('success', '面談パックを削除しました。');
    }

    // 【解説】公開(POST /admin/meeting-packs/{plan}/publish)。
    // 【解説】フォームの入力値はないので FormRequest は作らず、ログインユーザーを取るために普通の Request を受け取る。
    public function publish(MeetingPack $plan, Request $request, PublishAction $action): RedirectResponse
    {
        $this->authorize('publish', $plan);

        // 【解説】下書き以外なら PublishAction が 409 の例外を投げる。
        $action($plan, $request->user());

        return redirect()
            ->route('admin.meeting-packs.show', $plan)
            ->with('success', '面談パックを公開しました。');
    }

    // 【解説】アーカイブ(POST /admin/meeting-packs/{plan}/archive)。publish と同じ形。
    public function archive(MeetingPack $plan, Request $request, ArchiveAction $action): RedirectResponse
    {
        $this->authorize('archive', $plan);

        $action($plan, $request->user());

        return redirect()
            ->route('admin.meeting-packs.show', $plan)
            ->with('success', '面談パックをアーカイブしました。');
    }

    // 【解説】下書きに戻す(POST /admin/meeting-packs/{plan}/unarchive)。publish と同じ形。
    public function unarchive(MeetingPack $plan, Request $request, UnarchiveAction $action): RedirectResponse
    {
        $this->authorize('unarchive', $plan);

        $action($plan, $request->user());

        return redirect()
            ->route('admin.meeting-packs.show', $plan)
            ->with('success', '面談パックを下書きに戻しました。');
    }
}
