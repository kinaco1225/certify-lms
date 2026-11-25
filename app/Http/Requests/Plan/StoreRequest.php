<?php

declare(strict_types=1);

namespace App\Http\Requests\Plan;

use App\Models\Plan;
use Illuminate\Foundation\Http\FormRequest;

/**
 * プラン新規作成リクエスト(admin のみ)。状態(status)は受け付けず、StoreAction が下書きで作成する。
 *
 * 文字数・数値の範囲は画面(create.blade.php)の maxlength / hint と DB のカラム定義に合わせる。
 */
// 【解説】UpdateRequest と同じルール。既存コード(Certification / MeetingPack)に合わせて別ファイルにしている。
class StoreRequest extends FormRequest
{
    // 【解説】Policy の create を呼ぶ。まだ存在しないデータなので Plan::class(クラス名)を渡す。
    public function authorize(): bool
    {
        return $this->user()?->can('create', Plan::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    // 【解説】ここに書いていない項目(status / created_by_user_id など)は、送られてきても validated() に含まれない。
    public function rules(): array
    {
        return [
            // 【解説】max:100 は DB の string('name', 100) と画面の maxlength="100" に合わせた。
            'name' => ['required', 'string', 'max:100'],
            // 【解説】max:2000 は画面の hint「最大 2000 文字」に合わせた。
            'description' => ['nullable', 'string', 'max:2000'],
            // 【解説】integer があると、min / max は文字数ではなく数値の大きさとして判定される。画面の hint「1 〜 3650 の整数」。
            'duration_days' => ['required', 'integer', 'min:1', 'max:3650'],
            // 【解説】面談なしのプランもあり得るので 0 から。画面の hint「0 〜 1000 の整数」。
            'default_meeting_quota' => ['required', 'integer', 'min:0', 'max:1000'],
            // 【解説】上限 65535 は面談パック(S-B-02)に合わせた。空欄は null になり、Action で 0 に置き換える。
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    /**
     * @return array<string, string>
     */
    // 【解説】すべてのルールのメッセージ。キーは '項目名.ルール名'。
    public function messages(): array
    {
        return [
            'name.required' => 'プラン名を入力してください。',
            'name.string' => 'プラン名は文字列で入力してください。',
            'name.max' => 'プラン名は100文字以内で入力してください。',
            'description.string' => '説明は文字列で入力してください。',
            'description.max' => '説明は2000文字以内で入力してください。',
            'duration_days.required' => '受講期間を入力してください。',
            'duration_days.integer' => '受講期間は整数で入力してください。',
            'duration_days.min' => '受講期間は1日以上で入力してください。',
            'duration_days.max' => '受講期間は3650日以下で入力してください。',
            'default_meeting_quota.required' => '初期付与面談回数を入力してください。',
            'default_meeting_quota.integer' => '初期付与面談回数は整数で入力してください。',
            'default_meeting_quota.min' => '初期付与面談回数は0回以上で入力してください。',
            'default_meeting_quota.max' => '初期付与面談回数は1000回以下で入力してください。',
            'sort_order.integer' => '並び順は整数で入力してください。',
            'sort_order.min' => '並び順は0以上で入力してください。',
            'sort_order.max' => '並び順は65535以下で入力してください。',
        ];
    }

    /**
     * @return array<string, string>
     */
    // 【解説】messages() に書き忘れたルールがあったときの保険。
    public function attributes(): array
    {
        return [
            'name' => 'プラン名',
            'description' => '説明',
            'duration_days' => '受講期間',
            'default_meeting_quota' => '初期付与面談回数',
            'sort_order' => '並び順',
        ];
    }
}
