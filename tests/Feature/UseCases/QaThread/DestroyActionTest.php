<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\QaThread;

use App\Exceptions\QaBoard\QaThreadNotDeletableException;
use App\Models\QaReply;
use App\Models\QaThread;
use App\UseCases\QaThread\DestroyAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestroyActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_deletes_thread_without_replies(): void
    {
        $thread = QaThread::factory()->create();

        (new DestroyAction)($thread);

        $this->assertModelMissing($thread);
    }

    public function test_throws_when_thread_has_replies_for_author_deletion(): void
    {
        // 認可判定後に回答が投稿されたケースを想定
        $thread = QaThread::factory()->create();
        QaReply::factory()->for($thread, 'thread')->create();

        $this->expectException(QaThreadNotDeletableException::class);

        try {
            (new DestroyAction)($thread);
        } finally {
            $this->assertModelExists($thread);
        }
    }

    public function test_moderation_deletion_removes_thread_and_cascades_replies(): void
    {
        $thread = QaThread::factory()->create();
        $replies = QaReply::factory()->count(2)->for($thread, 'thread')->create();

        (new DestroyAction)($thread, allowWithReplies: true);

        $this->assertModelMissing($thread);
        $replies->each(fn (QaReply $reply) => $this->assertModelMissing($reply));
    }
}
