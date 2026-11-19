<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 面談パックの基本情報を更新するユースケース。status は本 Action では変更せず、状態遷移用 Action に分離する。
 */
final class UpdateAction
{
    /**
     * @param array{name: string, description?: ?string, meeting_count: int, price: int, stripe_price_id?: ?string, sort_order?: ?int} $validated MeetingPack/UpdateRequest で検証済
     */
    // 【解説】$pack = 更新する面談パック、$admin = 操作している管理者、$validated = チェック済みの入力値。
    public function __invoke(MeetingPack $pack, User $admin, array $validated): MeetingPack
    {
        // 【解説】処理が 2 行以上あるので、アロー関数(fn)ではなく通常の function を使う。
        // 【解説】use ($pack, $admin, $validated) = 外側の変数を、この無名関数の中で使えるように持ち込む指定。
        return DB::transaction(function () use ($pack, $admin, $validated) {
            // 【解説】$pack->update([...]) = 指定したカラムだけを UPDATE する。
            // 【解説】'status' はここに書いていないので、編集フォームから状態が変わることはない。
            $pack->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'meeting_count' => $validated['meeting_count'],
                'price' => $validated['price'],
                // 【解説】編集で Price ID を消した場合も null に戻す。
                'stripe_price_id' => $validated['stripe_price_id'] ?? null,
                'sort_order' => $validated['sort_order'] ?? 0,
                // 【解説】最終更新者を、今操作している管理者に書き換える。
                'updated_by_user_id' => $admin->id,
            ]);

            // 【解説】fresh() = DB から読み直した最新のモデルを返す(DB 側で変わった updated_at なども反映される)。
            return $pack->fresh();
        });
    }
}
