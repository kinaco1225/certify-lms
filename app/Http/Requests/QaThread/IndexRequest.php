<?php

declare(strict_types=1);

namespace App\Http\Requests\QaThread;

use App\Models\QaThread;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 質問掲示板一覧の絞り込み(解決状態 / 資格 / キーワード)。公開一覧と管理者モデレーション一覧で共用する。
 */
class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', QaThread::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'in:unresolved,resolved'],
            'certification_id' => ['nullable', 'ulid'],
            'keyword' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.in' => '解決状態は「未解決」か「解決済」から選択してください。',
            'certification_id.ulid' => '資格の指定が正しくありません。',
            'keyword.string' => 'キーワードは文字列で入力してください。',
            'keyword.max' => 'キーワードは100文字以内で入力してください。',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'status' => '解決状態',
            'certification_id' => '資格',
            'keyword' => 'キーワード',
        ];
    }

    /**
     * 画面(qa-thread._filter)へ渡す絞り込み値。未指定は空文字に揃える。
     *
     * @return array{status: string, certification_id: string, keyword: string}
     */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'status' => (string) ($validated['status'] ?? ''),
            'certification_id' => (string) ($validated['certification_id'] ?? ''),
            'keyword' => (string) ($validated['keyword'] ?? ''),
        ];
    }
}
