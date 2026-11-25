<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Plan;
use App\Models\User;
use App\Models\UserPlanLog;
use App\Policies\PlanPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PlanPolicy: 全 ability が admin のみ。delete は「下書き かつ 受講者・プラン履歴の参照なし」のときだけ true。
 */
class PlanPolicyTest extends TestCase
{
    use RefreshDatabase;

    // 【解説】delete 以外の、対象プランを受け取る ability。ループでまとめて確認する。
    private const INSTANCE_ABILITIES = ['view', 'update', 'publish', 'archive', 'unarchive'];

    public function test_admin_can_do_everything_except_conditional_delete(): void
    {
        $admin = User::factory()->admin()->create();
        $policy = new PlanPolicy;

        $this->assertTrue($policy->viewAny($admin));
        $this->assertTrue($policy->create($admin));
        // 【解説】状態に関係なく、delete 以外は管理者なら true。
        foreach ([Plan::factory()->draft()->create(), Plan::factory()->published()->create(), Plan::factory()->archived()->create()] as $plan) {
            foreach (self::INSTANCE_ABILITIES as $ability) {
                $this->assertTrue($policy->$ability($admin, $plan), "admin は {$plan->status->value} の {$ability} ができるべき");
            }
        }
    }

    public function test_admin_can_delete_only_unreferenced_draft(): void
    {
        $admin = User::factory()->admin()->create();
        $policy = new PlanPolicy;

        // 【解説】削除できるのは「下書き かつ 参照なし」だけ。
        $this->assertTrue($policy->delete($admin, Plan::factory()->draft()->create()));

        // 【解説】下書き以外は、参照がなくても false。
        $this->assertFalse($policy->delete($admin, Plan::factory()->published()->create()));
        $this->assertFalse($policy->delete($admin, Plan::factory()->archived()->create()));

        // 【解説】下書きでも、受講者・ソフトデリートされた受講者・プラン履歴のどれかがあれば false。
        $withUser = Plan::factory()->draft()->create();
        User::factory()->student()->inProgress()->withPlan($withUser)->create();
        $this->assertFalse($policy->delete($admin, $withUser));

        $withTrashedUser = Plan::factory()->draft()->create();
        User::factory()->student()->withdrawn()->withPlan($withTrashedUser)->create()->delete();
        $this->assertFalse($policy->delete($admin, $withTrashedUser));

        $withLog = Plan::factory()->draft()->create();
        UserPlanLog::factory()->create(['plan_id' => $withLog->id]);
        $this->assertFalse($policy->delete($admin, $withLog));
    }

    public function test_student_and_coach_can_do_nothing(): void
    {
        // 【解説】削除の条件を満たすプランでも、管理者以外は false になること。
        $plan = Plan::factory()->draft()->create();
        $policy = new PlanPolicy;

        foreach ([User::factory()->student()->create(), User::factory()->coach()->create()] as $user) {
            $this->assertFalse($policy->viewAny($user));
            $this->assertFalse($policy->create($user));
            $this->assertFalse($policy->delete($user, $plan));
            foreach (self::INSTANCE_ABILITIES as $ability) {
                $this->assertFalse($policy->$ability($user, $plan), "{$user->role->value} は {$ability} できないべき");
            }
        }
    }
}
