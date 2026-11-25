<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Plan\IndexRequest;
use App\Http\Requests\Plan\StoreRequest;
use App\Http\Requests\Plan\UpdateRequest;
use App\Models\Plan;
use App\UseCases\Plan\ArchiveAction;
use App\UseCases\Plan\DestroyAction;
use App\UseCases\Plan\IndexAction;
use App\UseCases\Plan\PublishAction;
use App\UseCases\Plan\ShowAction;
use App\UseCases\Plan\StoreAction;
use App\UseCases\Plan\UnarchiveAction;
use App\UseCases\Plan\UpdateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * admin 用の受講プラン マスタ管理 Controller。CRUD と状態遷移(publish / archive / unarchive)を提供する。
 *
 * 削除できないプラン(下書き以外 / 受講者・プラン履歴の参照あり)は PlanPolicy::delete が false を返すため、
 * 詳細画面に削除ボタンは表示されず、直接リクエストされた場合は 403 になる。
 */
// 【解説】Controller の役割は「受け取る → Action に任せる → 画面かリダイレクトを返す」だけ。面談パックの Controller と同じ形。
class PlanController extends Controller
{
    // 【解説】一覧画面(GET /admin/plans)。IndexRequest は渡される前に authorize() と rules() が実行済み。
    public function index(IndexRequest $request, IndexAction $action): View
    {
        $filters = $request->filters();

        // 【解説】resources/views/plan/management/index.blade.php を表示する。配列のキーが Blade の変数名になる。
        return view('plan.management.index', [
            'plans' => $action($filters['keyword'], $request->selectedStatus()),
            'keyword' => $filters['keyword'],
            'status' => $filters['status'],
        ]);
    }

    // 【解説】詳細画面(GET /admin/plans/{plan})。引数名 $plan と URL の {plan} が一致しているので、
    // 【解説】Laravel が DB から Plan を取り出して渡してくれる(ルートモデルバインディング)。存在しない ID なら 404。
    public function show(Plan $plan, ShowAction $action): View
    {
        // 【解説】Policy の view() を呼び、false なら 403 にする。
        $this->authorize('view', $plan);

        return view('plan.management.show', [
            // 【解説】ShowAction で作成者・更新者・受講者一覧を読み込んだモデルを渡す。
            'plan' => $action($plan),
        ]);
    }

    // 【解説】新規作成フォーム(GET /admin/plans/create)。
    public function create(): View
    {
        // 【解説】まだ存在しないデータなので、Policy にはクラス名(Plan::class)を渡す。
        $this->authorize('create', Plan::class);

        return view('plan.management.create');
    }

    // 【解説】新規作成(POST /admin/plans)。
    public function store(StoreRequest $request, StoreAction $action): RedirectResponse
    {
        // 【解説】$request->validated() = チェック済みの入力値だけ。status などは含まれない。
        $plan = $action($request->user(), $request->validated());

        // 【解説】->with('success', ...) = 次の画面に 1 回だけ表示するメッセージ(フラッシュメッセージ)。
        return redirect()
            ->route('admin.plans.show', $plan)
            ->with('success', 'プランを作成しました。');
    }

    // 【解説】編集フォーム(GET /admin/plans/{plan}/edit)。
    public function edit(Plan $plan): View
    {
        $this->authorize('update', $plan);

        return view('plan.management.edit', [
            'plan' => $plan,
        ]);
    }

    // 【解説】更新(PUT /admin/plans/{plan})。権限チェックは UpdateRequest の authorize() で済んでいる。
    public function update(Plan $plan, UpdateRequest $request, UpdateAction $action): RedirectResponse
    {
        $action($plan, $request->user(), $request->validated());

        return redirect()
            ->route('admin.plans.show', $plan)
            ->with('success', 'プランを更新しました。');
    }

    // 【解説】削除(DELETE /admin/plans/{plan})。
    public function destroy(Plan $plan, DestroyAction $action): RedirectResponse
    {
        // 【解説】PlanPolicy::delete は「管理者 かつ 下書き かつ 参照なし」のときだけ true。
        // 【解説】削除できないプランなら、ここで 403 になる(画面には削除ボタン自体が出ない)。
        $this->authorize('delete', $plan);

        // 【解説】認可の後に受講者が紐づいた場合などは、DestroyAction が 409 の例外を投げる(元の画面にエラー表示)。
        $action($plan);

        // 【解説】削除したプランの詳細画面はもう無いので、一覧画面へ移動する。
        return redirect()
            ->route('admin.plans.index')
            ->with('success', 'プランを削除しました。');
    }

    // 【解説】公開(POST /admin/plans/{plan}/publish)。入力値がないので FormRequest は使わず、普通の Request を受け取る。
    public function publish(Plan $plan, Request $request, PublishAction $action): RedirectResponse
    {
        $this->authorize('publish', $plan);

        // 【解説】下書き以外なら PublishAction が 409 の例外を投げ、ここから下は実行されない。
        $action($plan, $request->user());

        return redirect()
            ->route('admin.plans.show', $plan)
            ->with('success', 'プランを公開しました。');
    }

    // 【解説】アーカイブ(POST /admin/plans/{plan}/archive)。publish と同じ形。
    public function archive(Plan $plan, Request $request, ArchiveAction $action): RedirectResponse
    {
        $this->authorize('archive', $plan);

        $action($plan, $request->user());

        return redirect()
            ->route('admin.plans.show', $plan)
            ->with('success', 'プランをアーカイブしました。');
    }

    // 【解説】下書きに戻す(POST /admin/plans/{plan}/unarchive)。publish と同じ形。
    public function unarchive(Plan $plan, Request $request, UnarchiveAction $action): RedirectResponse
    {
        $this->authorize('unarchive', $plan);

        $action($plan, $request->user());

        return redirect()
            ->route('admin.plans.show', $plan)
            ->with('success', 'プランを下書きに戻しました。');
    }
}
