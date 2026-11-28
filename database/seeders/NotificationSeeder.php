<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\MeetingStatus;
use App\Models\ChatMember;
use App\Models\ChatMessage;
use App\Models\Meeting;
use App\Models\QaReply;
use App\Models\User;
use App\Notifications\BaseNotification;
use App\Notifications\Chat\ChatMessageReceivedNotification;
use App\Notifications\Mentoring\MeetingCanceledNotification;
use App\Notifications\Mentoring\MeetingReservedNotification;
use App\Notifications\QaBoard\QaReplyReceivedNotification;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * 開発用 通知シーダー。固定の受講生 / コーチに、既読・未読と種別を混在させた通知を投入する。
 *
 * - 受講生(student@): 25 件(1 ページ 20 件なので 2 ページ目まである)。回答 / チャット(+ 相手が取り消した面談があればキャンセル)
 * - コーチ(coach@): 12 件。面談予約 / 面談キャンセル / チャット
 *
 * 面談キャンセルの通知は、実際にキャンセルされた面談(相手側がキャンセルしたもの)からのみ作る。
 *
 * 通知の中身は実際の通知クラスの toArray() で作り、遷移先 URL には他 Seeder が作った実在のデータを使う
 * (行クリックで実際の画面へ遷移できる)。notifications テーブルへ直接保存するため、メールは送られない。
 * 依存: UserSeeder / MentoringSeeder / ChatSeeder / QaBoardSeeder(DatabaseSeeder の最後に呼ぶ)。
 */
class NotificationSeeder extends Seeder
{
    private const STUDENT_COUNT = 25;

    private const COACH_COUNT = 12;

    public function run(): void
    {
        $student = User::query()->where('email', 'student@certify-lms.test')->first();
        $coach = User::query()->where('email', 'coach@certify-lms.test')->first();

        if ($student === null || $coach === null) {
            $this->command?->warn('NotificationSeeder: 固定の受講生 / コーチが存在しません。先に UserSeeder を実行してください。');

            return;
        }

        $this->seedFor($student, $this->studentNotifications($student), self::STUDENT_COUNT);
        $this->seedFor($coach, $this->coachNotifications($coach), self::COACH_COUNT);
    }

    /**
     * 受講生が受け取る通知の候補(自分の質問への他人の回答 / コーチによる面談キャンセル / 他メンバーのチャット)。
     *
     * @return Collection<int, BaseNotification>
     */
    private function studentNotifications(User $student): Collection
    {
        // 【解説】自分の質問に、自分以外の人がつけた回答。
        $replies = QaReply::query()
            ->whereHas('thread', fn ($q) => $q->where('user_id', $student->id))
            ->where('user_id', '!=', $student->id)
            ->get()
            ->map(fn (QaReply $reply) => new QaReplyReceivedNotification($reply));

        // 【解説】面談キャンセルの通知は、実際に相手がキャンセルした面談だけから作る(meetingCanceledNotifications)。
        // 【解説】固定の受講生にはキャンセル済みの面談がないので、通常は 0 件になる(回答とチャットの 2 種類が並ぶ)。
        return $replies
            ->concat($this->meetingCanceledNotifications($student, 'student_id'))
            ->concat($this->chatNotifications($student));
    }

    /**
     * コーチが受け取る通知の候補(担当面談の予約 / 受講生による面談キャンセル / 他メンバーのチャット)。
     *
     * @return Collection<int, BaseNotification>
     */
    private function coachNotifications(User $coach): Collection
    {
        $meetings = Meeting::query()
            ->with('student')
            ->where('coach_id', $coach->id)
            ->latest('scheduled_at')
            ->limit(8)
            ->get();

        $reserved = $meetings->map(fn (Meeting $meeting) => new MeetingReservedNotification($meeting));

        return $reserved
            ->concat($this->meetingCanceledNotifications($coach, 'coach_id'))
            ->concat($this->chatNotifications($coach));
    }

    /**
     * 実際にキャンセルされた面談のうち、相手側がキャンセルしたものの通知(通知内容と面談詳細画面の状態を一致させる)。
     *
     * @param 'student_id'|'coach_id' $column 受け取る人が面談のどちら側か
     *
     * @return Collection<int, BaseNotification>
     */
    // 【解説】キャンセル通知は「キャンセルしなかった側」に届くので、自分がキャンセルした面談は除く(本物の通知と同じルール)。
    private function meetingCanceledNotifications(User $user, string $column): Collection
    {
        return Meeting::query()
            ->with('canceledBy')
            // 【解説】$column には 'student_id' か 'coach_id' が入る。自分が当事者の面談に絞る。
            ->where($column, $user->id)
            ->where('status', MeetingStatus::Canceled->value)
            ->whereNotNull('canceled_by_user_id')
            ->where('canceled_by_user_id', '!=', $user->id)
            ->get()
            ->map(fn (Meeting $meeting) => new MeetingCanceledNotification($meeting, $meeting->canceledBy));
    }

    /**
     * 自分がメンバーのチャットルームで、他のメンバーが送ったメッセージの通知。
     *
     * @return Collection<int, BaseNotification>
     */
    private function chatNotifications(User $user): Collection
    {
        $roomIds = ChatMember::query()->where('user_id', $user->id)->pluck('chat_room_id');

        return ChatMessage::query()
            ->with('sender')
            ->whereIn('chat_room_id', $roomIds)
            ->where('sender_user_id', '!=', $user->id)
            ->latest()
            ->get()
            ->map(fn (ChatMessage $message) => new ChatMessageReceivedNotification($message));
    }

    /**
     * 候補を順番に使い回して $count 件を保存する。新しい方の約半分を未読、古い方を既読にする。
     *
     * @param Collection<int, BaseNotification> $candidates
     */
    private function seedFor(User $user, Collection $candidates, int $count): void
    {
        if ($candidates->isEmpty()) {
            $this->command?->warn("NotificationSeeder: {$user->email} の通知の元になるデータがありません。");

            return;
        }

        // 【解説】種別が偏らないよう、候補をシャッフルしてから順番に使い回す。
        $candidates = $candidates->shuffle()->values();

        for ($i = 0; $i < $count; $i++) {
            // 【解説】% は割った余り。候補が 10 件なら 0〜9 を繰り返し使う。
            $notification = $candidates[$i % $candidates->count()];
            // 【解説】新しい通知ほど上に来るよう、i が増えるほど 5 時間ずつ過去の日時にする。
            $createdAt = now()->subHours($i * 5);

            // 【解説】$user->notifications()->create([...]) で、このユーザー宛の通知を 1 件保存する(notifiable は自動で入る)。
            $user->notifications()->create([
                'id' => (string) Str::uuid(),
                // 【解説】type には通知クラスの名前が入る(Laravel が送信するときと同じ形)。
                'type' => $notification::class,
                // 【解説】中身は実際の通知クラスの toArray() で作るので、本物の通知と同じ形になる。
                'data' => $notification->toArray($user),
                // 【解説】新しい方の約半分(i が小さい方)は未読(null)、残りは既読にする。
                'read_at' => $i < (int) ceil($count / 2) ? null : $createdAt->copy()->addHour(),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }
}
