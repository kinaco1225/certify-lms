<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Exceptions\MeetingQuota\MeetingPackInvalidTransitionException;
use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 面談パックをアーカイブ(published → archived)するユースケース。受講生の購入画面から外す(終売)。
 * 公開中以外の状態からの呼出は MeetingPackInvalidTransitionException(409)。
 */
// 【解説】PublishAction と同じ形。違うのは「確認する状態(公開中)」と「新しい状態(アーカイブ)」だけ。
final class ArchiveAction
{
    /**
     * @throws MeetingPackInvalidTransitionException 公開中以外からの呼出
     */
    public function __invoke(MeetingPack $pack, User $admin): MeetingPack
    {
        // 【解説】① アーカイブできるのは「公開中」だけ。下書きをいきなりアーカイブすることはできない。
        if ($pack->status !== MeetingPackStatus::Published) {
            throw MeetingPackInvalidTransitionException::forArchive();
        }

        // 【解説】③ 状態を「アーカイブ」に更新する。受講生ダッシュボードは公開中だけを表示するので、購入画面から消える。
        return DB::transaction(function () use ($pack, $admin) {
            $pack->update([
                'status' => MeetingPackStatus::Archived->value,
                'updated_by_user_id' => $admin->id,
            ]);

            return $pack->fresh();
        });
    }
}
