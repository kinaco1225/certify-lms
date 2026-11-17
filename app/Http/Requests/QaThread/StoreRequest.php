<?php

declare(strict_types=1);

namespace App\Http\Requests\QaThread;

use App\Enums\CertificationStatus;
use App\Models\QaThread;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 質問スレッド新規投稿リクエスト(受講生のみ)。投稿先は公開中の資格に限る(受講していない資格も可)。
 */
class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', QaThread::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'certification_id' => [
                'required',
                'ulid',
                Rule::exists('certifications', 'id')
                    ->where('status', CertificationStatus::Published->value),
            ],
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'certification_id.required' => '資格を選択してください。',
            'certification_id.ulid' => '資格の指定が正しくありません。',
            'certification_id.exists' => '公開中の資格を選択してください。',
            'title.required' => 'タイトルを入力してください。',
            'title.string' => 'タイトルは文字列で入力してください。',
            'title.max' => 'タイトルは200文字以内で入力してください。',
            'body.required' => '本文を入力してください。',
            'body.string' => '本文は文字列で入力してください。',
            'body.max' => '本文は5000文字以内で入力してください。',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'certification_id' => '資格',
            'title' => 'タイトル',
            'body' => '本文',
        ];
    }
}
