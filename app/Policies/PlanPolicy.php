<?php

// 【解説】型の自動変換をしない厳密モード。
declare(strict_types=1);

// 【解説】次の namespace はこのクラスの住所(名前空間)。app/Policies フォルダに置くクラスは App\Policies になる。

namespace App\Policies;

use App\Enums\PlanStatus;
use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\User;

/**
 * 受講プラン マスタの認可ルール。全操作を admin のみに許可する。
 *
 * delete のみ「削除できる状態か」も判定する(下書き かつ 受講者・プラン履歴の参照なし)。
 * 詳細画面の削除ボタンは @can('delete') だけで出し分けられているため、条件を Policy に含めて
 * 削除できないプランではボタン自体を表示しない。状態遷移の可否は各 Action が判定する(不正なら 409)。
 */
// 【解説】Policy = 「誰が何をしてよいか」を判定するクラス。true なら許可、false なら拒否(403)。
// 【解説】AuthServiceProvider で Plan と紐付けているので、Blade の @can('update', $plan) や
// 【解説】Controller の $this->authorize('update', $plan) から自動で呼ばれる。
class PlanPolicy
{
    // 【解説】一覧画面を見てよいか。特定の 1 件ではないので、引数はログインユーザーだけ。
    public function viewAny(User $auth): bool
    {
        return $this->isAdmin($auth);
    }

    // 【解説】詳細画面を見てよいか。$plan は使っていないが、Laravel が渡してくる形に合わせて受け取る。
    public function view(User $auth, Plan $plan): bool
    {
        return $this->isAdmin($auth);
    }

    // 【解説】新規作成してよいか。まだ存在しないデータなので Plan は受け取らない。
    public function create(User $auth): bool
    {
        return $this->isAdmin($auth);
    }

    // 【解説】編集してよいか(Blade の @can('update', $plan) から呼ばれる)。
    public function update(User $auth, Plan $plan): bool
    {
        return $this->isAdmin($auth);
    }

    // 【解説】削除してよいか。S-B-02(面談パック)と違い、ここで「削除できる状態か」まで判定する。
    // 【解説】理由: プランの詳細画面は @can('delete', $plan) だけで削除ボタンを出し分けているため、
    // 【解説】      ここで false を返さないと、公開中のプランにも削除ボタンが表示されてしまう。
    public function delete(User $auth, Plan $plan): bool
    {
        // 【解説】&& = 「かつ」。3 つすべて true のときだけ削除できる。左から順に判定し、false が出た時点で止まる。
        // 【解説】① 管理者 ② 下書き ③ 受講者もプラン履歴も紐づいていない(! は「否定」= true と false を反転する)。
        // 【解説】③ は DB に問い合わせるので、①②で false なら実行しない順番にしている(無駄なクエリを減らす)。
        return $this->isAdmin($auth)
            && $plan->status === PlanStatus::Draft
            && ! $plan->hasReferences();
    }

    // 【解説】公開してよいか。「下書きのみ公開できる」は状態のルールなので、ここではなく PublishAction で判定する。
    public function publish(User $auth, Plan $plan): bool
    {
        return $this->isAdmin($auth);
    }

    // 【解説】アーカイブしてよいか(Blade の @can('archive', $plan) から呼ばれる)。
    public function archive(User $auth, Plan $plan): bool
    {
        return $this->isAdmin($auth);
    }

    // 【解説】下書きに戻してよいか(Blade の @can('unarchive', $plan) から呼ばれる)。
    public function unarchive(User $auth, Plan $plan): bool
    {
        return $this->isAdmin($auth);
    }

    // 【解説】同じ判定を繰り返さないための補助メソッド。private なのでこのクラスの中からしか呼べない。
    private function isAdmin(User $auth): bool
    {
        return $auth->role === UserRole::Admin;
    }
}
