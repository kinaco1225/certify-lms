<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Notification\IndexRequest;
use App\UseCases\Notification\IndexAction;
use App\UseCases\Notification\MarkAllAsReadAction;
use App\UseCases\Notification\MarkAsReadAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 通知一覧と既読化の Controller(認証ユーザー共通)。扱うのは常にログインユーザー本人宛の通知のみ。
 */
class NotificationController extends Controller
{
    // 【解説】通知一覧(GET /notifications)。
    public function index(IndexRequest $request, IndexAction $action): View
    {
        $user = $request->user();
        $tab = $request->tab();

        return view('notifications.index', [
            'notifications' => $action($user, $tab),
            // 【解説】画面上部の「現在 ○ 件の未読通知があります。」に使う。タブに関係なく未読の総数。
            'unreadCount' => $user->unreadNotifications()->count(),
            'tab' => $tab,
        ]);
    }

    // 【解説】1 件既読にして、関連する画面へ移動する(POST /notifications/{notification}/read)。
    // 【解説】string $notification = URL の {notification} の値(通知の ID)を文字列のまま受け取る(モデルには変換しない)。
    public function markAsRead(Request $request, string $notification, MarkAsReadAction $action): RedirectResponse
    {
        // 【解説】redirect()->to(パス) = 指定したパスへ移動させる。他人の通知なら Action の中で 404 になる。
        return redirect()->to($action($request->user(), $notification));
    }

    // 【解説】自分の未読をすべて既読にする(POST /notifications/read-all)。
    public function markAllAsRead(Request $request, MarkAllAsReadAction $action): RedirectResponse
    {
        $action($request->user());

        return redirect()
            ->route('notifications.index')
            ->with('success', 'すべての通知を既読にしました。');
    }
}
