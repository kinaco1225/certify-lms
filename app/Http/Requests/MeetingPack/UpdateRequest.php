<?php

declare(strict_types=1);

namespace App\Http\Requests\MeetingPack;

use App\Models\MeetingPack;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 面談パック更新リクエスト(admin のみ)。基本情報のみ更新でき、状態(status)はこのフォームでは変更しない
 * (rules に含めないため、送信されても validated() に現れない)。状態遷移は publish / archive / unarchive で行う。
 */
// 【解説】ルールとメッセージは StoreRequest と同じ。違うのは authorize()(update 権限を見る)だけ。
class UpdateRequest extends FormRequest
{
    // 【解説】権限チェック。更新は「この 1 件」についての判定なので、ルートから対象の面談パックを取り出して Policy に渡す。
    public function authorize(): bool
    {
        // 【解説】$this->route('plan') = URL の {plan} に対応する値。ルートモデルバインディングで MeetingPack になっている。
        $pack = $this->route('plan');

        // 【解説】instanceof = 「MeetingPack クラスのインスタンスか」を確認する(念のための型チェック)。
        // 【解説】&& = 左右どちらも true のときだけ true。can('update', $pack) で Policy の update() が呼ばれる。
        return $pack instanceof MeetingPack
            && ($this->user()?->can('update', $pack) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    // 【解説】'status' をあえて書いていない。→ 編集フォームから状態を変えられない(チケットの「状態はこのフォームでは変更しない」)。
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'meeting_count' => ['required', 'integer', 'min:1', 'max:100'],
            'price' => ['required', 'integer', 'min:0', 'max:1000000'],
            'stripe_price_id' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    /**
     * @return array<string, string>
     */
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
