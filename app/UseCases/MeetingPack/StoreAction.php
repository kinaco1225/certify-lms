<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Models\MeetingPack;
use App\Models\User;
// 【解説】DB ファサード。DB::transaction() でトランザクション(まとめて成功 or まとめて取り消し)を使うために読み込む。
use Illuminate\Support\Facades\DB;

/**
 * 面談パックを新規作成するユースケース。初期状態は下書き(draft)で、admin を作成者 / 更新者に記録する。
 */
final class StoreAction
{
    /**
     * @param array{name: string, description?: ?string, meeting_count: int, price: int, stripe_price_id?: ?string, sort_order?: ?int} $validated MeetingPack/StoreRequest で検証済
     */
    // 【解説】上の @param は PHPDoc(コメントで書く型の説明)。$validated 配列にどんなキーが入っているかを示す。
    // 【解説】キー名の後ろの ? は「入っていないこともある」、型の前の ? は「null のこともある」という意味。
    // 【解説】$admin = 操作している管理者、$validated = FormRequest でチェック済みの入力値($request->validated())。
    public function __invoke(User $admin, array $validated): MeetingPack
    {
        // 【解説】DB::transaction(fn () => ...) = 中の処理を 1 つのトランザクションで実行する。
        // 【解説】途中でエラーが起きたら、それまでの DB 変更を全部取り消してくれる(中途半端なデータが残らない)。
        // 【解説】MeetingPack::create([...]) = 1 行 INSERT して、作成したモデルを返す。それをそのまま return する。
        return DB::transaction(fn () => MeetingPack::create([
            'name' => $validated['name'],
            // 【解説】?? = null 合体演算子。左側が存在しない / null なら右側の値を使う。説明が未入力なら null を保存する。
            'description' => $validated['description'] ?? null,
            'meeting_count' => $validated['meeting_count'],
            'price' => $validated['price'],
            'stripe_price_id' => $validated['stripe_price_id'] ?? null,
            // 空欄送信は null になるが、sort_order カラムは NOT NULL のため 0 に揃える
            // 【解説】Laravel はフォームの空欄を null に変換する。sort_order は DB が null を許さないので、0 を入れる。
            'sort_order' => $validated['sort_order'] ?? 0,
            // 【解説】状態は入力値を使わず、ここで「下書き」に固定する。->value で Enum から 'draft' という文字列を取り出す。
            'status' => MeetingPackStatus::Draft->value,
            // 【解説】作成者・更新者は入力値ではなく、ログイン中の管理者の id を入れる(なりすましを防ぐ)。
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]));
    }
}
