<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `GET /admin/meeting-packs/{plan}/edit` + `PATCH /admin/meeting-packs/{plan}`: 基本情報の更新(状態は変えない)。
 */
class UpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_form_shows_current_values(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $pack = MeetingPack::factory()->create(['name' => '編集前のパック']);

        $this->actingAs($admin)->get(route('admin.meeting-packs.edit', $pack))
            ->assertOk()
            ->assertSee('編集前のパック の編集')
            // 【解説】フォームの送信先が update ルートになっていること。
            ->assertSee(route('admin.meeting-packs.update', $pack));
    }

    public function test_admin_updates_basic_info_and_updater(): void
    {
        // 【解説】作成者とは別の管理者で更新し、最終更新者が書き換わることを確認する。
        $creator = User::factory()->admin()->inProgress()->create();
        $editor = User::factory()->admin()->inProgress()->create();
        $pack = MeetingPack::factory()->published()->create([
            'created_by_user_id' => $creator->id,
            'updated_by_user_id' => $creator->id,
        ]);

        $this->actingAs($editor)
            ->patch(route('admin.meeting-packs.update', $pack), [
                'name' => '新しい名前',
                'description' => '',
                'meeting_count' => 3,
                'price' => 9000,
                'stripe_price_id' => '',
                'sort_order' => '',
                // 【解説】状態を送っても変わらないことを確認するため、わざと送る。
                'status' => 'archived',
            ])
            ->assertRedirect(route('admin.meeting-packs.show', $pack))
            ->assertSessionHas('success', '面談パックを更新しました。');

        // 【解説】$pack は更新前の値のままなので、fresh() で DB から読み直して確認する。
        $fresh = $pack->fresh();
        $this->assertSame('新しい名前', $fresh->name);
        $this->assertNull($fresh->description);
        $this->assertSame(3, $fresh->meeting_count);
        $this->assertSame(9000, $fresh->price);
        $this->assertNull($fresh->stripe_price_id);
        $this->assertSame(0, $fresh->sort_order);
        // 【解説】状態は公開中のまま(編集フォームでは状態を変更しない)。
        $this->assertSame(MeetingPackStatus::Published, $fresh->status);
        $this->assertSame($creator->id, $fresh->created_by_user_id);
        $this->assertSame($editor->id, $fresh->updated_by_user_id);
    }

    public function test_validation(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $pack = MeetingPack::factory()->create(['name' => '元の名前']);

        $this->actingAs($admin)
            ->patch(route('admin.meeting-packs.update', $pack), ['name' => '', 'meeting_count' => 0, 'price' => 1000001])
            ->assertSessionHasErrors([
                'name' => 'パック名を入力してください。',
                'meeting_count' => '面談回数は1回以上で入力してください。',
                'price' => '価格は1,000,000円以下で入力してください。',
            ]);

        // 【解説】エラーなので、名前は元のまま。
        $this->assertSame('元の名前', $pack->fresh()->name);
    }
}
