<?php

// 【解説】型の自動変換をしない厳密モード。int を受け取る引数に "1"(文字列)を渡すとエラーにしてくれる。
declare(strict_types=1);

// 【解説】次の namespace はこのクラスの住所(名前空間)。app/Policies フォルダに置くクラスは App\Policies になる。

namespace App\Policies;

// 【解説】use = 「このファイルで使う別のクラスを読み込む」宣言。これで UserRole などを短い名前で書ける。
use App\Enums\UserRole;
use App\Models\MeetingPack;
use App\Models\User;

/**
 * 面談パック(追加面談購入用 SKU マスタ)の認可ルール。全操作を admin のみに許可する。
 *
 * 状態による可否(公開中は削除不可 / 下書きのみ公開可 など)は Policy ではなく各 Action で判定し、
 * 不正な場合は 409 を返す(CertificationPolicy と同じ役割分担)。
 */
// 【解説】Policy = 「誰が何をしてよいか」を判定するクラス。各メソッドが true なら許可、false なら拒否(403)。
// 【解説】AuthServiceProvider で MeetingPack と紐付けているので、$user->can('update', $pack) や
// 【解説】Blade の @can('update', $plan)、Controller の $this->authorize('update', $pack) から自動で呼ばれる。
class MeetingPackPolicy
{
    // 【解説】一覧画面を見てよいか。対象が「特定の 1 件」ではないので、引数はログインユーザー($auth)だけ。
    public function viewAny(User $auth): bool
    {
        return $this->isAdmin($auth);
    }

    // 【解説】詳細画面を見てよいか。こちらは「この 1 件」についての判定なので、第 2 引数に MeetingPack を受け取る。
    // 【解説】今回は全員同じ判定なので $pack は使っていないが、Laravel が渡してくる形に合わせて書いておく。
    public function view(User $auth, MeetingPack $pack): bool
    {
        return $this->isAdmin($auth);
    }

    // 【解説】新規作成してよいか。まだ存在しないデータなので MeetingPack は受け取らない(viewAny と同じ形)。
    public function create(User $auth): bool
    {
        return $this->isAdmin($auth);
    }

    // 【解説】編集してよいか。
    public function update(User $auth, MeetingPack $pack): bool
    {
        return $this->isAdmin($auth);
    }

    // 【解説】削除してよいか。「公開中は削除できない」は状態のルールなので、ここではなく DestroyAction で判定する。
    public function delete(User $auth, MeetingPack $pack): bool
    {
        return $this->isAdmin($auth);
    }

    // 【解説】公開してよいか。メソッド名は Blade の @can('publish', $plan) の 'publish' と一致させる必要がある。
    public function publish(User $auth, MeetingPack $pack): bool
    {
        return $this->isAdmin($auth);
    }

    // 【解説】アーカイブしてよいか(Blade の @can('archive', $plan) から呼ばれる)。
    public function archive(User $auth, MeetingPack $pack): bool
    {
        return $this->isAdmin($auth);
    }

    // 【解説】下書きに戻してよいか(Blade の @can('unarchive', $plan) から呼ばれる)。
    public function unarchive(User $auth, MeetingPack $pack): bool
    {
        return $this->isAdmin($auth);
    }

    // 【解説】8 つのメソッドで同じ判定を繰り返さないよう、1 か所にまとめた補助メソッド。
    // 【解説】private = このクラスの中からしか呼べない。Laravel の権限名として外から呼ばれることもない。
    private function isAdmin(User $auth): bool
    {
        // 【解説】ユーザーのロール(Enum)が Admin と「完全に同じ」(===)なら true。
        return $auth->role === UserRole::Admin;
    }
}
