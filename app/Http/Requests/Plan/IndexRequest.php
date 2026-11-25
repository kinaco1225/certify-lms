<?php

declare(strict_types=1);

namespace App\Http\Requests\Plan;

use App\Enums\PlanStatus;
use App\Models\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
// 【解説】Rule::enum() が返すルールのクラス。messages() のキーに使うために読み込む。
use Illuminate\Validation\Rules\Enum;

/**
 * admin 用プラン一覧の絞り込みリクエスト。プラン名のキーワード + 状態(下書き / 公開中 / アーカイブ)。
 */
// 【解説】Controller の引数に書くと、Controller の処理より先に ① authorize()(403 判定)② rules()(入力チェック)が実行される。
class IndexRequest extends FormRequest
{
    // 【解説】Policy の viewAny を呼ぶ。?-> と ?? false は未ログイン(user() が null)のときに false にするための書き方。
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Plan::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:100'],
            // 【解説】PlanStatus に存在する値('draft' / 'published' / 'archived')だけ許可する。
            'status' => ['nullable', Rule::enum(PlanStatus::class)],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    // 【解説】キーは '項目名.ルール名'。ただし Rule::enum(ルールオブジェクト)は Laravel 10 では '項目名.クラス名' になる。
    public function messages(): array
    {
        return [
            'keyword.string' => 'キーワードは文字列で入力してください。',
            'keyword.max' => 'キーワードは100文字以内で入力してください。',
            'status.'.Enum::class => 'ステータスは「下書き」「公開中」「アーカイブ」から選択してください。',
            'page.integer' => 'ページ番号は整数で指定してください。',
            'page.min' => 'ページ番号は1以上で指定してください。',
        ];
    }

    /**
     * @return array<string, string>
     */
    // 【解説】messages() に書き忘れたルールがあったときの保険(共通メッセージの :attribute に使われる)。
    public function attributes(): array
    {
        return [
            'keyword' => 'キーワード',
            'status' => 'ステータス',
            'page' => 'ページ番号',
        ];
    }

    /**
     * 画面(plan.management.index)へ渡す絞り込み値。未指定は空文字に揃える。
     *
     * @return array{keyword: string, status: string}
     */
    // 【解説】Blade の検索フォームに入力値を戻すための値。Blade は文字列として使うので、未指定は '' にする。
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'keyword' => (string) ($validated['keyword'] ?? ''),
            'status' => (string) ($validated['status'] ?? ''),
        ];
    }

    /**
     * 状態フィルタを Enum で返す(未指定なら null)。IndexAction に渡す。
     */
    // 【解説】PlanStatus::from('published') で文字列から Enum を作る。rules() で正しい値に絞っているので失敗しない。
    public function selectedStatus(): ?PlanStatus
    {
        $status = $this->validated()['status'] ?? null;

        return $status !== null ? PlanStatus::from($status) : null;
    }
}
