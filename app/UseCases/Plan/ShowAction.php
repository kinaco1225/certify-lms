<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Models\Plan;

/**
 * admin 用のプラン詳細を取得するユースケース。
 * メタ情報の作成者 / 最終更新者と、受講者一覧(名前順)を Eager Loading する。
 */
final class ShowAction
{
    public function __invoke(Plan $plan): Plan
    {
        // 【解説】load([...]) = 関連データをまとめて読み込む(Eager Loading)。
        // 【解説】'users' => fn ($query) => ... のように書くと、読み込むときの条件や並び順を指定できる。
        // 【解説】ここでは受講者一覧を名前順に並べる。Blade の $plan->users でこの一覧が使われる。
        return $plan->load([
            'createdBy',
            'updatedBy',
            'users' => fn ($query) => $query->orderBy('name'),
        ]);
    }
}
