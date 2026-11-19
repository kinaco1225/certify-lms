<?php

declare(strict_types=1);

namespace App\Http\Requests\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Models\MeetingPack;
// 【解説】FormRequest = 入力チェック(バリデーション)と権限チェックを Controller の前に行ってくれるクラス。
use Illuminate\Foundation\Http\FormRequest;
// 【解説】Rule = 文字列では書きにくいルール(Enum の値かどうか など)を組み立てるためのクラス。
use Illuminate\Validation\Rule;
// 【解説】Rule::enum() が返すルールのクラス。messages() のキーに使うために読み込む。
use Illuminate\Validation\Rules\Enum;

/**
 * admin 用面談パック一覧の絞り込みリクエスト。パック名のキーワード + 状態(下書き / 公開中 / アーカイブ)。
 */
// 【解説】Controller の引数に IndexRequest $request と書くと、Controller の処理が始まる前に
// 【解説】① authorize() で権限チェック → false なら 403
// 【解説】② rules() で入力チェック → 失敗したら元の画面に戻してエラーを表示
// 【解説】の順に自動で実行される。両方通ったときだけ Controller の中身が動く。
class IndexRequest extends FormRequest
{
    // 【解説】① 権限チェック。Policy の viewAny(一覧を見てよいか)を呼ぶ。
    // 【解説】$this->user() = ログイン中のユーザー。?-> は「null なら呼ばずに null を返す」書き方(未ログイン対策)。
    // 【解説】?? false = 結果が null(未ログイン)なら false(拒否)にする。
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', MeetingPack::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    // 【解説】② 入力チェックのルール。'項目名' => [ルールの配列] の形で書く。
    // 【解説】一覧の絞り込みは GET の URL パラメータ(?keyword=...&status=...)で届く。
    public function rules(): array
    {
        return [
            // 【解説】nullable = 空でもよい(絞り込みなし)。string = 文字列。max:100 = 100 文字以内。
            'keyword' => ['nullable', 'string', 'max:100'],
            // 【解説】Rule::enum(...) = MeetingPackStatus に存在する値('draft' / 'published' / 'archived')だけ許可する。
            // 【解説】URL を書き換えて ?status=xxx のような値を送られても、ここで弾ける。
            'status' => ['nullable', Rule::enum(MeetingPackStatus::class)],
            // 【解説】ページ番号。1 以上の整数だけ許可する。
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    // 【解説】エラーメッセージ。キーは '項目名.ルール名'。例: 'keyword.max' = keyword が max ルールに違反したとき。
    // 【解説】ただし Rule::enum(...) のような「ルールオブジェクト」は、Laravel 10 ではキーが '項目名.クラス名' になる。
    // 【解説】Rule::enum() が作るのは Illuminate\Validation\Rules\Enum クラスなので、'status.'.Enum::class と書く。
    // 【解説】('status.enum' と書いても効かず、英語の共通メッセージが出てしまう。tinker で確認済み)
    public function messages(): array
    {
        return [
            'keyword.string' => 'キーワードは文字列で入力してください。',
            'keyword.max' => 'キーワードは100文字以内で入力してください。',
            // 【解説】. は文字列の連結。Enum::class は 'Illuminate\Validation\Rules\Enum' という文字列になる。
            'status.'.Enum::class => 'ステータスは「下書き」「公開中」「アーカイブ」から選択してください。',
            'page.integer' => 'ページ番号は整数で指定してください。',
            'page.min' => 'ページ番号は1以上で指定してください。',
        ];
    }

    /**
     * @return array<string, string>
     */
    // 【解説】項目名の日本語表示。messages() に書き忘れたルールがあったとき、共通メッセージの :attribute に使われる(保険)。
    public function attributes(): array
    {
        return [
            'keyword' => 'キーワード',
            'status' => 'ステータス',
            'page' => 'ページ番号',
        ];
    }

    /**
     * 画面(meeting-pack.management.index)へ渡す絞り込み値。未指定は空文字に揃える。
     *
     * @return array{keyword: string, status: string}
     */
    // 【解説】Blade の検索フォームに今の入力値を戻すための値を返す自作メソッド。
    // 【解説】Blade は $keyword / $status を文字列として使う(value="{{ $keyword }}" や $status === $s->value)ので、
    // 【解説】未指定(null)のときは空文字 '' に揃えておく。
    public function filters(): array
    {
        // 【解説】validated() = rules() のチェックを通った値だけを配列で返す。
        $validated = $this->validated();

        return [
            // 【解説】(string) は文字列への型変換。?? '' で「未指定なら空文字」。
            'keyword' => (string) ($validated['keyword'] ?? ''),
            'status' => (string) ($validated['status'] ?? ''),
        ];
    }

    /**
     * 状態フィルタを Enum で返す(未指定なら null)。IndexAction に渡す。
     */
    // 【解説】IndexAction は状態を Enum(MeetingPackStatus)で受け取るので、文字列から Enum に変換して返す。
    // 【解説】rules() で正しい値だけに絞っているので、ここで変換に失敗することはない。
    public function selectedStatus(): ?MeetingPackStatus
    {
        $status = $this->validated()['status'] ?? null;

        // 【解説】三項演算子: 条件 ? 真のときの値 : 偽のときの値。
        // 【解説】MeetingPackStatus::from('published') で、文字列から Enum(MeetingPackStatus::Published)を作る。
        return $status !== null ? MeetingPackStatus::from($status) : null;
    }
}
