<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Exceptions\MeetingQuota\MeetingPackInvalidTransitionException;
use App\Exceptions\MeetingQuota\MeetingPackNotDeletableException;
use App\Models\MeetingPack;
use App\Models\User;
use App\UseCases\MeetingPack\ArchiveAction;
use App\UseCases\MeetingPack\DestroyAction;
use App\UseCases\MeetingPack\PublishAction;
use App\UseCases\MeetingPack\UnarchiveAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 状態遷移 / 削除の Action を HTTP を通さずに直接呼び、例外(409)とガードを検証する。
 */
// 【解説】画面を通さず Action だけをテストすると、「Controller 以外から呼ばれてもルールが守られる」ことを保証できる。
class MeetingPackActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_publish_from_non_draft_throws_409(): void
    {
        $pack = MeetingPack::factory()->published()->create();

        // 【解説】expectException(クラス名) = この後の処理でその例外が投げられることを期待する(投げられなければ失敗)。
        $this->expectException(MeetingPackInvalidTransitionException::class);
        $this->expectExceptionMessage('下書きの面談パックのみ公開できます。');

        // 【解説】app(クラス名) = Laravel にインスタンスを作ってもらう。その後ろの (...) で __invoke を呼んでいる。
        app(PublishAction::class)($pack, User::factory()->admin()->create());
    }

    public function test_archive_from_non_published_throws_409(): void
    {
        $pack = MeetingPack::factory()->draft()->create();

        $this->expectException(MeetingPackInvalidTransitionException::class);
        $this->expectExceptionMessage('公開中の面談パックのみアーカイブできます。');

        app(ArchiveAction::class)($pack, User::factory()->admin()->create());
    }

    public function test_unarchive_from_non_archived_throws_409(): void
    {
        $pack = MeetingPack::factory()->published()->create();

        $this->expectException(MeetingPackInvalidTransitionException::class);
        $this->expectExceptionMessage('アーカイブ済みの面談パックのみ下書きに戻せます。');

        app(UnarchiveAction::class)($pack, User::factory()->admin()->create());
    }

    public function test_transition_exception_is_http_409(): void
    {
        $pack = MeetingPack::factory()->archived()->create();

        // 【解説】try-catch で例外を受け止め、ステータスコードが 409 であることを確かめる。
        try {
            app(PublishAction::class)($pack, User::factory()->admin()->create());
            // 【解説】ここまで来たら例外が投げられなかった = テスト失敗にする。
            $this->fail('例外が投げられるべき');
        } catch (MeetingPackInvalidTransitionException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }

        $this->assertSame(MeetingPackStatus::Archived, $pack->fresh()->status);
    }

    public function test_destroy_published_throws_409_and_keeps_record(): void
    {
        $pack = MeetingPack::factory()->published()->create();

        try {
            app(DestroyAction::class)($pack);
            $this->fail('例外が投げられるべき');
        } catch (MeetingPackNotDeletableException $e) {
            $this->assertSame(409, $e->getStatusCode());
            $this->assertSame('公開中の面談パックは削除できません。', $e->getMessage());
        }

        $this->assertModelExists($pack);
    }

    public function test_destroy_draft_and_archived_deletes_record(): void
    {
        $draft = MeetingPack::factory()->draft()->create();
        $archived = MeetingPack::factory()->archived()->create();

        app(DestroyAction::class)($draft);
        app(DestroyAction::class)($archived);

        $this->assertModelMissing($draft);
        $this->assertModelMissing($archived);
    }
}
