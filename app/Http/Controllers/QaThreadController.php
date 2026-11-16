<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CertificationStatus;
use App\Exceptions\QaBoard\QaThreadInvalidTransitionException;
use App\Exceptions\QaBoard\QaThreadNotDeletableException;
use App\Http\Requests\QaThread\IndexRequest;
use App\Http\Requests\QaThread\StoreRequest;
use App\Http\Requests\QaThread\UpdateRequest;
use App\Models\Certification;
use App\Models\QaThread;
use App\UseCases\QaThread\DestroyAction;
use App\UseCases\QaThread\IndexAction;
use App\UseCases\QaThread\ResolveAction;
use App\UseCases\QaThread\ShowAction;
use App\UseCases\QaThread\StoreAction;
use App\UseCases\QaThread\UnresolveAction;
use App\UseCases\QaThread\UpdateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * 質問掲示板(受講生 / コーチ向け公開画面)のスレッド Controller。
 *
 * 閲覧範囲は QaThread::scopeVisibleTo / QaThreadPolicy::view で絞り込み、
 * 投稿は受講生のみ、編集・削除・解決マークは投稿者本人のみ(QaThreadPolicy)。
 */
class QaThreadController extends Controller
{
    public function index(IndexRequest $request, IndexAction $action): View
    {
        $viewer = $request->user();
        $filters = $request->filters();

        return view('qa-thread.index', [
            'threads' => $action($viewer, $filters),
            'filters' => $filters,
            'certifications' => Certification::query()->visibleForQaBoard($viewer)->orderBy('name')->get(),
            'publishedStatus' => CertificationStatus::Published,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', QaThread::class);

        return view('qa-thread.create', [
            'certifications' => Certification::query()->published()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreRequest $request, StoreAction $action): RedirectResponse
    {
        $thread = $action($request->user(), $request->validated());

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '質問を投稿しました。');
    }

    public function show(QaThread $thread, ShowAction $action): View
    {
        $this->authorize('view', $thread);

        return view('qa-thread.show', [
            'thread' => $action($thread),
        ]);
    }

    public function edit(QaThread $thread): View
    {
        $this->authorize('update', $thread);

        return view('qa-thread.edit', [
            'thread' => $thread,
        ]);
    }

    public function update(QaThread $thread, UpdateRequest $request, UpdateAction $action): RedirectResponse
    {
        $action($thread, $request->validated());

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '質問を更新しました。');
    }

    /**
     * @throws QaThreadNotDeletableException
     */
    public function destroy(QaThread $thread, DestroyAction $action): RedirectResponse
    {
        $this->authorize('delete', $thread);

        $action($thread);

        return redirect()
            ->route('qa-board.index')
            ->with('success', '質問を削除しました。');
    }

    /**
     * @throws QaThreadInvalidTransitionException
     */
    public function resolve(QaThread $thread, ResolveAction $action): RedirectResponse
    {
        $this->authorize('resolve', $thread);

        $action($thread);

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '解決済にしました。');
    }

    /**
     * @throws QaThreadInvalidTransitionException
     */
    public function unresolve(QaThread $thread, UnresolveAction $action): RedirectResponse
    {
        $this->authorize('unresolve', $thread);

        $action($thread);

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '未解決に戻しました。');
    }
}
