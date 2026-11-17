<?php

declare(strict_types=1);

namespace App\Http\Requests\QaReply;

use App\Models\QaReply;
use App\Models\QaThread;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 回答投稿リクエスト(受講生 / コーチのうち、親スレッドを閲覧できるユーザーのみ)。
 */
class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $thread = $this->route('thread');

        return $thread instanceof QaThread
            && ($this->user()?->can('create', [QaReply::class, $thread]) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required' => '回答本文を入力してください。',
            'body.string' => '回答本文は文字列で入力してください。',
            'body.max' => '回答本文は5000文字以内で入力してください。',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'body' => '回答本文',
        ];
    }
}
