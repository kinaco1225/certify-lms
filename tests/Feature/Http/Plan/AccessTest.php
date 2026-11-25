<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * プラン管理の全 10 ルートが admin 専用であること(student / coach は 403、未ログインはログイン画面へ)。
 */
class AccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_and_coach_are_forbidden_on_all_routes(): void
    {
        $plan = Plan::factory()->draft()->create();

        foreach ([User::factory()->student()->inProgress()->create(), User::factory()->coach()->inProgress()->create()] as $user) {
            foreach ($this->routes($plan) as [$method, $url]) {
                // 【解説】actingAs = そのユーザーでログインした状態にして、$this->get() / post() などを呼ぶ。
                $this->actingAs($user)->$method($url)->assertForbidden();
            }
        }

        // 【解説】403 で弾かれたので、削除も状態変更もされていない。
        $this->assertModelExists($plan);
        $this->assertSame('draft', $plan->fresh()->status->value);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $plan = Plan::factory()->create();

        foreach ($this->routes($plan) as [$method, $url]) {
            $this->$method($url)->assertRedirect(route('login'));
        }
    }

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    // 【解説】チケットの HTTP インターフェース表と同じ 10 ルート(更新はチケットどおり PUT)。
    private function routes(Plan $plan): array
    {
        return [
            ['get', route('admin.plans.index')],
            ['get', route('admin.plans.create')],
            ['post', route('admin.plans.store')],
            ['get', route('admin.plans.show', $plan)],
            ['get', route('admin.plans.edit', $plan)],
            ['put', route('admin.plans.update', $plan)],
            ['delete', route('admin.plans.destroy', $plan)],
            ['post', route('admin.plans.publish', $plan)],
            ['post', route('admin.plans.archive', $plan)],
            ['post', route('admin.plans.unarchive', $plan)],
        ];
    }
}
