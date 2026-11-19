<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Models\MeetingPack;

/**
 * admin 用の面談パック詳細を取得するユースケース。メタ情報に表示する作成者 / 最終更新者を Eager Loading する。
 *
 * 購入履歴(payments)は追加面談購入 Feature で導入予定のため本チケットでは読み込まない
 * (Blade 側が `class_exists(Payment::class)` で未導入時の表示を切り替える)。
 */
final class ShowAction
{
    // 【解説】引数の $pack は、ルートの {plan} から Laravel が自動で取り出した MeetingPack(ルートモデルバインディング)。
    public function __invoke(MeetingPack $pack): MeetingPack
    {
        // 【解説】load([...]) = 関連データ(リレーション)をまとめて読み込む(Eager Loading)。
        // 【解説】'createdBy' / 'updatedBy' は MeetingPack モデルにあるリレーションメソッド名(作成者・更新者の User)。
        // 【解説】詳細画面のメタ情報で $plan->createdBy->name を表示するので、先に読み込んでおく。
        // 【解説】load は読み込んだ後の $pack 自身を返すので、そのまま return できる。
        return $pack->load(['createdBy', 'updatedBy']);
    }
}
