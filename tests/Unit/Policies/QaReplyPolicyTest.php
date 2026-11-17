<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use App\Policies\QaReplyPolicy;
use App\Policies\QaThreadPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class QaReplyPolicyTest extends TestCase
{
    use RefreshDatabase;

    private QaReplyPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new QaReplyPolicy(new QaThreadPolicy);
    }

    // ---- create ----

    public function test_student_can_reply_to_visible_thread(): void
    {
        $student = User::factory()->student()->create();
        $thread = QaThread::factory()->create();

        $this->assertTrue($this->policy->create($student, $thread));
    }

    public function test_student_can_reply_to_resolved_thread(): void
    {
        $student = User::factory()->student()->create();
        $thread = QaThread::factory()->resolved()->create();

        $this->assertTrue($this->policy->create($student, $thread));
    }

    public function test_student_cannot_reply_to_thread_of_unpublished_certification(): void
    {
        $student = User::factory()->student()->create();
        $thread = QaThread::factory()->for(Certification::factory()->archived()->create())->create();

        $this->assertFalse($this->policy->create($student, $thread));
    }

    public function test_assigned_coach_can_reply(): void
    {
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();
        $this->assignCoach($cert, $coach);
        $thread = QaThread::factory()->for($cert)->create();

        $this->assertTrue($this->policy->create($coach, $thread));
    }

    public function test_unassigned_coach_cannot_reply(): void
    {
        $coach = User::factory()->coach()->create();
        $thread = QaThread::factory()->create();

        $this->assertFalse($this->policy->create($coach, $thread));
    }

    public function test_admin_cannot_reply(): void
    {
        $admin = User::factory()->admin()->create();
        $thread = QaThread::factory()->create();

        $this->assertFalse($this->policy->create($admin, $thread));
    }

    // ---- update / delete ----

    public function test_author_can_update_and_delete_own_reply(): void
    {
        $author = User::factory()->student()->create();
        $reply = QaReply::factory()->for($author)->create();

        $this->assertTrue($this->policy->update($author, $reply));
        $this->assertTrue($this->policy->delete($author, $reply));
    }

    public function test_other_user_cannot_update_or_delete_reply(): void
    {
        $other = User::factory()->student()->create();
        $reply = QaReply::factory()->create();

        $this->assertFalse($this->policy->update($other, $reply));
        $this->assertFalse($this->policy->delete($other, $reply));
    }

    public function test_coach_author_loses_access_after_unassignment(): void
    {
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();
        $this->assignCoach($cert, $coach, unassignedAt: now());
        $thread = QaThread::factory()->for($cert)->create();
        $reply = QaReply::factory()->for($thread, 'thread')->for($coach)->create();

        $this->assertFalse($this->policy->update($coach, $reply));
        $this->assertFalse($this->policy->delete($coach, $reply));
    }

    public function test_admin_can_delete_but_not_update_any_reply(): void
    {
        $admin = User::factory()->admin()->create();
        $thread = QaThread::factory()->for(Certification::factory()->archived()->create())->create();
        $reply = QaReply::factory()->for($thread, 'thread')->create();

        $this->assertFalse($this->policy->update($admin, $reply));
        $this->assertTrue($this->policy->delete($admin, $reply));
    }

    private function assignCoach(Certification $cert, User $coach, mixed $unassignedAt = null): void
    {
        $cert->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => User::factory()->admin()->create()->id,
            'assigned_at' => now()->subDay(),
            'unassigned_at' => $unassignedAt,
        ]);
    }
}
