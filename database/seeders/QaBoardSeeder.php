<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CertificationStatus;
use App\Enums\QaThreadStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * 開発用 質問掲示板(qa-board)シーダー。
 *
 * **設計思想(状態網羅 + 固定アカウント)**:
 *
 * 1. **公開中の資格ごとにスレッドを散布**: 未解決 / 解決済、回答 0 件 / 数件、作成日時(過去 60 日)をばらつかせ、
 *    絞り込み・新着順・未回答バッジを確認できるようにする。先頭の資格はスレッドを多めに作り、
 *    1 ページ(20 件)を超えてページネーションを確認できるようにする。
 *
 * 2. **非公開資格(アーカイブ)にも少数のスレッド**: 受講生 / コーチからは見えず、管理者モデレーション画面でのみ
 *    横断閲覧できることを確認するため。
 *
 * 3. **固定 student(student@certify-lms.test)の投稿**: 「回答 0 件 = 削除可」「コーチ回答あり = 解決マーク可」
 *    「解決済 = 未解決に戻す」の各動線と、他人のスレッドへの自分の回答(編集 / 削除)を確認できるようにする。
 *
 * 回答の投稿は親スレッドの updated_at を更新する($touches)ため、Seeder では touch を無効化して
 * 最終活動日時を明示的にセットする。
 *
 * 依存順序: `UserSeeder` → `CertificationSeeder`(担当コーチ割当含む)→ 本 Seeder。
 */
final class QaBoardSeeder extends Seeder
{
    private const FIXED_STUDENT_EMAIL = 'student@certify-lms.test';

    private const THREADS_PER_CERTIFICATION = 10;

    private const THREADS_FOR_PAGINATION = 24;

    /** @var array<int, array{title: string, body: string}> */
    private const QUESTIONS = [
        ['title' => '過去問の解説を読んでも計算の途中式が理解できません', 'body' => "過去問の解説を読んだのですが、途中の式変形がなぜそうなるのか分かりません。\n教材の該当 Section も読み直しましたが、前提となる考え方が抜けている気がします。どこから復習すべきでしょうか。"],
        ['title' => '似た用語の違いがいつも混乱します', 'body' => "教材に出てくる 2 つの用語の違いが曖昧なままです。\n自分なりに表にまとめてみましたが、試験でどちらを選ぶべきか迷います。見分けるコツがあれば教えてください。"],
        ['title' => '模試の分野別スコアで特定分野だけ極端に低いです', 'body' => "模試を 2 回受けて、どちらも同じ分野だけ正答率が 3 割台でした。\n苦手分野ドリルも回していますが伸び悩んでいます。おすすめの学習順序はありますか。"],
        ['title' => '1 日の学習時間の配分について相談です', 'body' => "平日は 1 時間、休日は 3 時間ほど確保できます。\nインプットと演習の比率はどれくらいが良いでしょうか。皆さんの配分も参考にしたいです。"],
        ['title' => '教材の図の読み方が分かりません', 'body' => "Chapter 内の図で、矢印の向きが何を表しているのか分かりません。\n凡例が見当たらず、解説文と照らし合わせても理解できませんでした。"],
        ['title' => '暗記に頼らず理解する方法はありますか', 'body' => "演習問題は正解できても、少し聞き方を変えられると解けなくなります。\n丸暗記になっている気がするので、理解を深める勉強法を知りたいです。"],
        ['title' => '試験直前 1 週間の過ごし方', 'body' => '試験まで残り 1 週間です。新しい範囲に手を出すべきか、模試の復習に集中すべきか迷っています。'],
        ['title' => '演習問題の選択肢 C が正解になる理由', 'body' => "Section 末尾の演習問題で、選択肢 B も条件を満たしているように見えます。\nなぜ C だけが正解になるのか、判断のポイントを教えてください。"],
        ['title' => '用語集のような資料はありますか', 'body' => "教材を読み進める中で、略語が多くて毎回調べ直しています。\n一覧で確認できる資料や、皆さんが使っているまとめ方があれば知りたいです。"],
        ['title' => 'ケアレスミスを減らすコツ', 'body' => "模試の見直しをすると、分かっていたのに読み違えた問題が毎回 3〜4 問あります。\n問題文の読み方で気をつけていることはありますか。"],
    ];

    /** @var array<int, string> */
    private const STUDENT_REPLIES = [
        '私も同じところでつまずきました。教材の 1 つ前の Section に戻って例題を解き直したら理解できました。',
        '自分は図を手書きで写しながら解説を読むようにしたら、流れがつかめるようになりました。',
        '横から失礼します。過去問を年度別ではなく分野別に解き直すと、出題パターンが見えてきますよ。',
        '同じ悩みを持っていたので参考になりました。ありがとうございます！',
    ];

    /** @var array<int, string> */
    private const COACH_REPLIES = [
        '良い質問ですね。ポイントは前提条件の読み取りです。問題文のどの条件が答えを決めているかに印を付けてから解いてみてください。',
        'この分野は基礎の定義を正確に押さえると一気に解けるようになります。まずは教材の定義部分を自分の言葉で説明できるか確認しましょう。',
        '模試の結果を拝見すると、知識よりも時間配分が課題に見えます。1 問あたりの目安時間を決めて演習してみてください。',
    ];

    /** @var Collection<int, User> */
    private Collection $students;

    public function run(): void
    {
        $this->students = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value)
            ->where('email', '!=', self::FIXED_STUDENT_EMAIL)
            ->get();

        $publishedCertifications = Certification::query()
            ->where('status', CertificationStatus::Published->value)
            ->with('coaches')
            ->orderBy('created_at')
            ->get();

        if ($this->students->isEmpty() || $publishedCertifications->isEmpty()) {
            $this->command?->warn('QaBoardSeeder: 受講中の受講生または公開中の資格が存在しません。先に UserSeeder / CertificationSeeder を実行してください。');

            return;
        }

        Model::withoutTouching(function () use ($publishedCertifications): void {
            foreach ($publishedCertifications as $index => $certification) {
                $count = $index === 0 ? self::THREADS_FOR_PAGINATION : self::THREADS_PER_CERTIFICATION;

                for ($i = 0; $i < $count; $i++) {
                    $this->seedRandomThread($certification);
                }
            }

            $this->seedUnpublishedCertificationThreads();
            $this->seedFixedStudentThreads($publishedCertifications);
        });
    }

    private function seedRandomThread(Certification $certification): void
    {
        $createdAt = now()->subDays(fake()->numberBetween(0, 60))->subMinutes(fake()->numberBetween(0, 1440));
        // 約 3 割は未回答(回答 0 件)
        $replyCount = fake()->boolean(30) ? 0 : fake()->numberBetween(1, 4);
        // 回答がある場合のみ、約半数を解決済にする
        $resolved = $replyCount > 0 && fake()->boolean(50);

        $this->createThread(
            certification: $certification,
            author: $this->students->random(),
            question: fake()->randomElement(self::QUESTIONS),
            createdAt: $createdAt,
            replyCount: $replyCount,
            resolved: $resolved,
        );
    }

    /**
     * アーカイブ資格のスレッド(管理者モデレーション画面でのみ閲覧できる)。
     */
    private function seedUnpublishedCertificationThreads(): void
    {
        $archived = Certification::query()
            ->where('status', CertificationStatus::Archived->value)
            ->with('coaches')
            ->first();

        if ($archived === null) {
            return;
        }

        foreach ([0, 2] as $replyCount) {
            $this->createThread(
                certification: $archived,
                author: $this->students->random(),
                question: fake()->randomElement(self::QUESTIONS),
                createdAt: now()->subDays(fake()->numberBetween(90, 120)),
                replyCount: $replyCount,
                resolved: false,
            );
        }
    }

    /**
     * 固定 student の投稿パターン(削除可 / 解決マーク可 / 解決済)と、他人のスレッドへの自分の回答。
     *
     * @param Collection<int, Certification> $certifications
     */
    private function seedFixedStudentThreads(Collection $certifications): void
    {
        $student = User::query()->where('email', self::FIXED_STUDENT_EMAIL)->first();

        if ($student === null) {
            return;
        }

        $certification = $certifications->first();

        // 回答 0 件: 編集 / 削除の動線確認用(最新に表示されるよう直近に作成)
        $this->createThread($certification, $student, [
            'title' => '【自分の質問】2 進数の補数表現がイメージできません',
            'body' => "教材の Section を読んだのですが、2 の補数で負の数を表す理由が腑に落ちません。\n具体例で説明していただけると助かります。",
        ], now()->subHours(2), replyCount: 0, resolved: false);

        // 回答あり・未解決: 解決済マークの動線確認用
        $this->createThread($certification, $student, [
            'title' => '【自分の質問】サブネットマスクの計算手順を教えてください',
            'body' => "/26 のようなプレフィックス表記からホスト数を求める手順が分かりません。\n毎回どこから計算すればよいのか迷ってしまいます。",
        ], now()->subDays(1), replyCount: 2, resolved: false);

        // 解決済: 未解決に戻す動線確認用
        $this->createThread($certification, $student, [
            'title' => '【自分の質問】正規化の第 3 正規形の判定方法',
            'body' => '推移的関数従属があるかどうかの見分け方が分かりませんでした。',
        ], now()->subDays(5), replyCount: 3, resolved: true);

        // 他人のスレッドへの自分の回答: 回答の編集 / 削除の動線確認用
        $othersThread = QaThread::query()
            ->where('certification_id', $certification->id)
            ->where('user_id', '!=', $student->id)
            ->latest()
            ->first();

        if ($othersThread !== null) {
            $repliedAt = now()->subHours(1);
            QaReply::create([
                'qa_thread_id' => $othersThread->id,
                'user_id' => $student->id,
                'body' => '【自分の回答】私は教材の例題を 3 回解き直したら理解できました。参考になれば幸いです。',
                'created_at' => $repliedAt,
                'updated_at' => $repliedAt,
            ]);
            $othersThread->forceFill(['updated_at' => $repliedAt])->saveQuietly();
        }
    }

    /**
     * スレッドと回答を作成し、作成日時 / 回答日時 / 解決日時 / 最終活動日時を時系列が矛盾しないようにセットする。
     *
     * @param array{title: string, body: string} $question
     */
    private function createThread(
        Certification $certification,
        User $author,
        array $question,
        CarbonInterface $createdAt,
        int $replyCount,
        bool $resolved,
    ): QaThread {
        $thread = QaThread::create([
            'certification_id' => $certification->id,
            'user_id' => $author->id,
            'title' => $question['title'],
            'body' => $question['body'],
            'status' => QaThreadStatus::Open->value,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        $lastActivityAt = $createdAt->copy();

        for ($i = 0; $i < $replyCount; $i++) {
            $lastActivityAt = $lastActivityAt->copy()->addMinutes(fake()->numberBetween(30, 60 * 24));
            if ($lastActivityAt->isFuture()) {
                $lastActivityAt = now();
            }

            [$replier, $body] = $this->pickReplier($certification, $author);

            QaReply::create([
                'qa_thread_id' => $thread->id,
                'user_id' => $replier->id,
                'body' => $body,
                'created_at' => $lastActivityAt,
                'updated_at' => $lastActivityAt,
            ]);
        }

        $attributes = ['updated_at' => $lastActivityAt];

        if ($resolved) {
            $resolvedAt = $lastActivityAt->copy()->addHours(fake()->numberBetween(1, 12));
            $resolvedAt = $resolvedAt->isFuture() ? now() : $resolvedAt;
            $attributes = [
                'status' => QaThreadStatus::Resolved->value,
                'resolved_at' => $resolvedAt,
                'updated_at' => $resolvedAt,
            ];
        }

        $thread->forceFill($attributes)->saveQuietly();

        return $thread;
    }

    /**
     * 回答者を選ぶ。担当コーチがいれば約半数はコーチ、それ以外は投稿者以外の受講生。
     *
     * @return array{0: User, 1: string}
     */
    private function pickReplier(Certification $certification, User $author): array
    {
        if ($certification->coaches->isNotEmpty() && fake()->boolean(50)) {
            return [$certification->coaches->random(), fake()->randomElement(self::COACH_REPLIES)];
        }

        $candidates = $this->students->reject(fn (User $s) => $s->id === $author->id);
        $replier = $candidates->isNotEmpty() ? $candidates->random() : $this->students->random();

        return [$replier, fake()->randomElement(self::STUDENT_REPLIES)];
    }
}
