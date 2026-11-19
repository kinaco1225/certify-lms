<?php

declare(strict_types=1);

namespace App\Http\Requests\MeetingPack;

use App\Models\MeetingPack;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 面談パック新規作成リクエスト(admin のみ)。状態(status)は受け付けず、StoreAction が下書きで作成する。
 *
 * 文字数・数値の範囲は画面(create.blade.php)の maxlength / hint と DB のカラム定義に合わせる。
 */
// 【解説】UpdateRequest と同じルールだが、既存コード(Certification / QaThread)に合わせて別ファイルにしている。
// 【解説】作成と更新で将来ルールが変わったときに、片方だけ直せるという利点もある。
class StoreRequest extends FormRequest
{
    // 【解説】権限チェック。Policy の create(新規作成してよいか)を呼ぶ。まだ存在しないデータなので MeetingPack::class を渡す。
    public function authorize(): bool
    {
        return $this->user()?->can('create', MeetingPack::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    // 【解説】ここに書いていない項目(status / created_by_user_id など)は、送られてきても validated() に含まれない。
    // 【解説】→ 画面を改ざんして status=published を送られても、Action には届かない。
    public function rules(): array
    {
        return [
            // 【解説】required = 必須。max:100 は DB の string('name', 100) と画面の maxlength="100" に合わせた。
            'name' => ['required', 'string', 'max:100'],
            // 【解説】任意項目。max:2000 は画面の hint「最大 2000 文字」に合わせた。
            'description' => ['nullable', 'string', 'max:2000'],
            // 【解説】integer = 整数。min:1 / max:100 は数値の範囲(画面の hint「1 〜 100 の整数」)。
            // 【解説】integer があると、min / max は「文字数」ではなく「数値の大きさ」として判定される。
            'meeting_count' => ['required', 'integer', 'min:1', 'max:100'],
            // 【解説】価格は 0 円〜100 万円(画面の hint「0 〜 1,000,000 の整数」)。
            'price' => ['required', 'integer', 'min:0', 'max:1000000'],
            // 【解説】任意。max:255 は DB の string('stripe_price_id', 255) に合わせた。
            'stripe_price_id' => ['nullable', 'string', 'max:255'],
            // 【解説】任意。上限 65535 は既存の QuestionCategory の並び順に合わせた(PM 確認事項)。
            // 【解説】空欄のときは null になり、StoreAction で 0 に置き換える。
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    /**
     * @return array<string, string>
     */
    // 【解説】すべてのルールのメッセージをここに書く(S-B-01 と同じ方針)。キーは '項目名.ルール名'。
    public function messages(): array
    {
        return [
            'name.required' => 'パック名を入力してください。',
            'name.string' => 'パック名は文字列で入力してください。',
            'name.max' => 'パック名は100文字以内で入力してください。',
            'description.string' => '説明は文字列で入力してください。',
            'description.max' => '説明は2000文字以内で入力してください。',
            'meeting_count.required' => '面談回数を入力してください。',
            'meeting_count.integer' => '面談回数は整数で入力してください。',
            'meeting_count.min' => '面談回数は1回以上で入力してください。',
            'meeting_count.max' => '面談回数は100回以下で入力してください。',
            'price.required' => '価格を入力してください。',
            'price.integer' => '価格は整数で入力してください。',
            'price.min' => '価格は0円以上で入力してください。',
            'price.max' => '価格は1,000,000円以下で入力してください。',
            'stripe_price_id.string' => 'Stripe Price IDは文字列で入力してください。',
            'stripe_price_id.max' => 'Stripe Price IDは255文字以内で入力してください。',
            'sort_order.integer' => '並び順は整数で入力してください。',
            'sort_order.min' => '並び順は0以上で入力してください。',
            'sort_order.max' => '並び順は65535以下で入力してください。',
        ];
    }

    /**
     * @return array<string, string>
     */
    // 【解説】messages() に書き忘れたルールがあったときの保険(共通メッセージの :attribute がこの名前になる)。
    public function attributes(): array
    {
        return [
            'name' => 'パック名',
            'description' => '説明',
            'meeting_count' => '面談回数',
            'price' => '価格',
            'stripe_price_id' => 'Stripe Price ID',
            'sort_order' => '並び順',
        ];
    }
}
