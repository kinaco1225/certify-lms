<?php

declare(strict_types=1);

namespace App\UseCases\Notification;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * ログインユーザー本人宛の通知一覧を新着順に取得するユースケース。'unread' タブでは未読のみ。
 */
final class IndexAction
{
    public const PER_PAGE = 20;

    public function __invoke(User $user, string $tab): LengthAwarePaginator
    {
        // 【解説】$user->notifications() = Notifiable トレイトが用意しているリレーション。「このユーザー宛の通知」だけに絞られる。
        // 【解説】  最初から新しい順(created_at の降順)に並ぶようになっている。他人の通知は絶対に混ざらない。
        return $user->notifications()
            // 【解説】未読タブのときだけ、read_at が null(未読)のものに絞る。
            ->when($tab === 'unread', fn ($query) => $query->whereNull('read_at'))
            ->paginate(self::PER_PAGE)
            // 【解説】ページ番号のリンクに ?tab=unread を引き継ぐ。
            ->withQueryString();
    }
}
