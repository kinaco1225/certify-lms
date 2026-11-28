<?php

declare(strict_types=1);

namespace App\Http\Requests\Notification;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 通知一覧のタブ切替(全件 / 未読のみ)リクエスト。認証ユーザーなら誰でも自分の通知一覧を開ける。
 */
class IndexRequest extends FormRequest
{
    // 【解説】自分の通知しか取得しない(IndexAction で $user->notifications() に限定する)ので、ログインしていれば許可する。
    // 【解説】未ログインはルートの auth ミドルウェアでログイン画面へ移動させられるため、ここに来るのはログイン済みのユーザーだけ。
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // 【解説】in:all,unread = 'all' か 'unread' のどちらかだけ許可する(画面のタブ <x-tabs> の ?tab= の値)。
            'tab' => ['nullable', 'in:all,unread'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tab.in' => 'タブは「全件」か「未読のみ」を選択してください。',
            'page.integer' => 'ページ番号は整数で指定してください。',
            'page.min' => 'ページ番号は1以上で指定してください。',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'tab' => 'タブ',
            'page' => 'ページ番号',
        ];
    }

    /**
     * 選択中のタブ。未指定は 'all'。
     */
    // 【解説】画面の $tab と IndexAction に渡す値。未指定なら '全件'('all')にする。
    public function tab(): string
    {
        return (string) ($this->validated()['tab'] ?? 'all');
    }
}
