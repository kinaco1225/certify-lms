<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use App\Policies\QaThreadPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class QaThreadPolicyTest extends TestCase
{
    use RefreshDatabase;

    private QaThreadPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new QaThreadPolicy;
    }

    // ---- viewAny / create ----

    public function test_all_roles_can_view_any(): void
    {
        $this->assertTrue($this->policy->viewAny(User::factory()->admin()->create()));
        $this->assertTrue($this->policy->viewAny(User::factory()->coach()->create()));
        $this->assertTrue($this->policy->viewAny(User::factory()->student()->create()));
    }

    public function test_only_student_can_create(): void
    {
        $this->assertTrue($this->policy->create(User::factory()->student()->create()));
        $this->assertFalse($this->policy->create(User::factory()->coach()->create()));
        $this->assertFalse($this->policy->create(User::factory()->admin()->create()));
    }

    // ---- view ----

    public function test_student_can_view_thread_of_published_certification_without_enrollment(): void
    {
        $student = User::factory()->student()->create();
        $thread = QaThread::factory()->create();

        $this->assertTrue($this->policy->view($student, $thread));
    }

    public function test_student_cannot_view_thread_of_unpublished_certification(): void
    {
        $student = User::factory()->student()->create();

        foreach ([Certification::factory()->draft(), Certification::factory()->archived()] as $certFactory) {
            $thread = QaThread::factory()->for($certFactory->create())->create();

            $this->assertFalse($this->policy->view($student, $thread));
        }
    }

    public function test_assigned_coach_can_view_thread_of_published_certification(): void
    {
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();
        $this->assignCoach($cert, $coach);
        $thread = QaThread::factory()->for($cert)->create();

        $this->assertTrue($this->policy->view($coach, $thread));
    }

    public function test_unassigned_coach_cannot_view_thread(): void
    {
        $coach = User::factory()->coach()->create();
        $thread = QaThread::factory()->create();

        $this->assertFalse($this->policy->view($coach, $thread));
    }

    public function test_coach_unassigned_later_cannot_view_thread(): void
    {
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();
        $this->assignCoach($cert, $coach, unassignedAt: now());
        $thread = QaThread::factory()->for($cert)->create();

        $this->assertFalse($this->policy->view($coach, $thread));
    }

    public function test_assigned_coach_cannot_view_thread_of_unpublished_certification(): void
    {
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->archived()->create();
        $this->assignCoach($cert, $coach);
        $thread = QaThread::factory()->for($cert)->create();

        $this->assertFalse($this->policy->view($coach, $thread));
    }

    public function test_admin_can_view_thread_of_unpublished_certification(): void
    {
        $admin = User::factory()->admin()->create();
        $thread = QaThread::factory()->for(Certification::factory()->archived()->create())->create();

        $this->assertTrue($this->policy->view($admin, $thread));
    }

    // ---- update / resolve / unresolve ----

    public function test_author_can_update_resolve_and_unresolve(): void
    {
        $author = User::factory()->student()->create();
        $thread = QaThread::factory()->for($author)->create();

        $this->assertTrue($this->policy->update($author, $thread));
        $this->assertTrue($this->policy->resolve($author, $thread));
        $this->assertTrue($this->policy->unresolve($author, $thread));
    }

    public function test_other_student_cannot_update_resolve_or_unresolve(): void
    {
        $other = User::factory()->student()->create();
        $thread = QaThread::factory()->create();

        $this->assertFalse($this->policy->update($other, $thread));
        $this->assertFalse($this->policy->resolve($other, $thread));
        $this->assertFalse($this->policy->unresolve($other, $thread));
    }

    public function test_admin_cannot_update_resolve_or_unresolve(): void
    {
        $admin = User::factory()->admin()->create();
        $thread = QaThread::factory()->create();

        $this->assertFalse($this->policy->update($admin, $thread));
        $this->assertFalse($this->policy->resolve($admin, $thread));
        $this->assertFalse($this->policy->unresolve($admin, $thread));
    }

    public function test_author_cannot_update_after_certification_is_unpublished(): void
    {
        $author = User::factory()->student()->create();
        $thread = QaThread::factory()->for($author)
            ->for(Certification::factory()->archived()->create())
            ->create();

        $this->assertFalse($this->policy->update($author, $thread));
        $this->assertFalse($this->policy->resolve($author, $thread));
        $this->assertFalse($this->policy->delete($author, $thread));
    }

    // ---- delete ----

    public function test_author_can_delete_thread_without_replies(): void
    {
        $author = User::factory()->student()->create();
        $thread = QaThread::factory()->for($author)->create();

        $this->assertTrue($this->policy->delete($author, $thread));
    }

    public function test_author_cannot_delete_thread_with_replies(): void
    {
        $author = User::factory()->student()->create();
        $thread = QaThread::factory()->for($author)->create();
        QaReply::factory()->for($thread, 'thread')->create();

        $this->assertFalse($this->policy->delete($author, $thread));
    }

    public function test_other_student_cannot_delete_thread(): void
    {
        $other = User::factory()->student()->create();
        $thread = QaThread::factory()->create();

        $this->assertFalse($this->policy->delete($other, $thread));
    }

    public function test_assigned_coach_cannot_delete_thread(): void
    {
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();
        $this->assignCoach($cert, $coach);
        $thread = QaThread::factory()->for($cert)->create();

        $this->assertFalse($this->policy->delete($coach, $thread));
    }

    public function test_admin_can_delete_any_thread_even_with_replies_or_unpublished(): void
    {
        $admin = User::factory()->admin()->create();
        $thread = QaThread::factory()->for(Certification::factory()->archived()->create())->create();
        QaReply::factory()->for($thread, 'thread')->create();

        $this->assertTrue($this->policy->delete($admin, $thread));
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
