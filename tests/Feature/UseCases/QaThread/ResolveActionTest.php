<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\QaThread;

use App\Enums\QaThreadStatus;
use App\Exceptions\QaBoard\QaThreadInvalidTransitionException;
use App\Models\QaThread;
use App\UseCases\QaThread\ResolveAction;
use App\UseCases\QaThread\UnresolveAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * 解決状態遷移(ResolveAction / UnresolveAction)の正常系と不正遷移を検証する。
 */
class ResolveActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_sets_status_and_resolved_at(): void
    {
        Carbon::setTestNow('2026-11-15 10:00:00');
        $thread = QaThread::factory()->open()->create();

        $result = (new ResolveAction)($thread);

        $this->assertSame(QaThreadStatus::Resolved, $result->status);
        $this->assertTrue($result->fresh()->resolved_at->equalTo(Carbon::parse('2026-11-15 10:00:00')));
    }

    public function test_resolve_throws_for_resolved_thread(): void
    {
        $thread = QaThread::factory()->resolved()->create();

        $this->expectException(QaThreadInvalidTransitionException::class);
        $this->expectExceptionMessage('この質問は既に「解決済」のため、「解決済」に変更できません。');

        (new ResolveAction)($thread);
    }

    public function test_unresolve_clears_resolved_at(): void
    {
        $thread = QaThread::factory()->resolved()->create();

        $result = (new UnresolveAction)($thread);

        $this->assertSame(QaThreadStatus::Open, $result->fresh()->status);
        $this->assertNull($result->fresh()->resolved_at);
    }

    public function test_unresolve_throws_for_open_thread(): void
    {
        $thread = QaThread::factory()->open()->create();

        $this->expectException(QaThreadInvalidTransitionException::class);

        (new UnresolveAction)($thread);
    }
}
