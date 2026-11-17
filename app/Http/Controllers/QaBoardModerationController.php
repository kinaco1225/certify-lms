<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CertificationStatus;
use App\Http\Requests\QaThread\IndexRequest;
use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\UseCases\QaReply\DestroyAction as DestroyReplyAction;
use App\UseCases\QaThread\DestroyAction as DestroyThreadAction;
use App\UseCases\QaThread\IndexAction;
use App\UseCases\QaThread\ShowAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * 質問掲示板の管理者モデレーション Controller(admin 専用ルート)。
 *
 * - 公開停止中の資格を含む全資格のスレッドを横断閲覧できる(QaThread::scopeVisibleTo の admin 分岐)
 * - 任意のスレッド(回答の有無を問わない)・回答を削除できる
 * - 内容の編集・解決マークの代行・回答投稿は提供しない
 *
 * 画面は公開側と同じ qa-thread.* Blade を共用し、Blade 側が `admin.*` ルートかどうかで表示を切り替える。
 */
class QaBoardModerationController extends Controller
{
    public function index(IndexRequest $request, IndexAction $action): View
    {
        $admin = $request->user();
        $filters = $request->filters();

        return view('qa-thread.index', [
            'threads' => $action($admin, $filters),
            'filters' => $filters,
            'certifications' => Certification::query()->visibleForQaBoard($admin)->orderBy('name')->get(),
            'publishedStatus' => CertificationStatus::Published,
        ]);
    }

    public function show(QaThread $thread, ShowAction $action): View
    {
        $this->authorize('view', $thread);

        return view('qa-thread.show', [
            'thread' => $action($thread),
        ]);
    }

    public function destroy(QaThread $thread, DestroyThreadAction $action): RedirectResponse
    {
        $this->authorize('delete', $thread);

        $action($thread, allowWithReplies: true);

        return redirect()
            ->route('admin.qa-board.index')
            ->with('success', '質問を削除しました。');
    }

    public function destroyReply(QaThread $thread, QaReply $reply, DestroyReplyAction $action): RedirectResponse
    {
        $this->authorize('delete', $reply);

        $action($reply);

        return redirect()
            ->route('admin.qa-board.show', $thread)
            ->with('success', '回答を削除しました。');
    }
}
