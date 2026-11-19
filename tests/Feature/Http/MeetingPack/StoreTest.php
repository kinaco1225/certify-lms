<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `GET /admin/meeting-packs/create` + `POST /admin/meeting-packs`: 下書きで作成 / 作成者の記録 / 入力検証。
 */
class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_form_is_shown(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();

        $this->actingAs($admin)->get(route('admin.meeting-packs.create'))
            ->assertOk()
            ->assertSee('面談パックの新規作成');
    }

    public function test_admin_creates_pack_as_draft(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();

        $response = $this->actingAs($admin)->post(route('admin.meeting-packs.store'), $this->payload([
            // 【解説】status を送っても無視される(rules にないので validated() に入らない)ことを確認するため、わざと送る。
            'status' => 'published',
        ]));

        // 【解説】DB から作成されたパックを取り出す。sole() = 「ちょうど 1 件」を取得(0 件や 2 件以上なら例外 = テスト失敗)。
        $pack = MeetingPack::sole();
        $response->assertRedirect(route('admin.meeting-packs.show', $pack))
            // 【解説】assertSessionHas('success', ...) = フラッシュメッセージがセッションに入っていること。
            ->assertSessionHas('success', '面談パックを作成しました。');

        $this->assertSame('5 回パック', $pack->name);
        $this->assertSame(5, $pack->meeting_count);
        $this->assertSame(12000, $pack->price);
        $this->assertSame('price_test123', $pack->stripe_price_id);
        $this->assertSame(20, $pack->sort_order);
        // 【解説】status=published を送ったのに、下書きで作られている。
        $this->assertSame(MeetingPackStatus::Draft, $pack->status);
        $this->assertSame($admin->id, $pack->created_by_user_id);
        $this->assertSame($admin->id, $pack->updated_by_user_id);
    }

    public function test_optional_fields_can_be_empty(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();

        // 【解説】任意項目を空欄で送る。Laravel が空文字を null に変換する。
        $this->actingAs($admin)->post(route('admin.meeting-packs.store'), $this->payload([
            'description' => '',
            'stripe_price_id' => '',
            'sort_order' => '',
        ]))->assertSessionHasNoErrors();

        $pack = MeetingPack::sole();
        $this->assertNull($pack->description);
        $this->assertNull($pack->stripe_price_id);
        // 【解説】sort_order は DB が NOT NULL なので、StoreAction で 0 に置き換えている。
        $this->assertSame(0, $pack->sort_order);
    }

    public function test_required_fields(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();

        $this->actingAs($admin)
            ->post(route('admin.meeting-packs.store'), ['name' => '', 'meeting_count' => '', 'price' => ''])
            ->assertSessionHasErrors([
                'name' => 'パック名を入力してください。',
                'meeting_count' => '面談回数を入力してください。',
                'price' => '価格を入力してください。',
            ]);

        // 【解説】assertDatabaseCount('テーブル名', 件数) = エラーなので何も保存されていないこと。
        $this->assertDatabaseCount('meeting_packs', 0);
    }

    public function test_values_out_of_range(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();

        // 【解説】すべて上限 + 1(または下限 - 1)の値で送る。
        $this->actingAs($admin)
            ->post(route('admin.meeting-packs.store'), [
                'name' => str_repeat('あ', 101),
                'description' => str_repeat('あ', 2001),
                'meeting_count' => 101,
                'price' => -1,
                'stripe_price_id' => str_repeat('a', 256),
                'sort_order' => 65536,
            ])
            ->assertSessionHasErrors([
                'name' => 'パック名は100文字以内で入力してください。',
                'description' => '説明は2000文字以内で入力してください。',
                'meeting_count' => '面談回数は100回以下で入力してください。',
                'price' => '価格は0円以上で入力してください。',
                'stripe_price_id' => 'Stripe Price IDは255文字以内で入力してください。',
                'sort_order' => '並び順は65535以下で入力してください。',
            ]);

        $this->actingAs($admin)
            ->post(route('admin.meeting-packs.store'), $this->payload(['meeting_count' => 0, 'price' => 1000001, 'sort_order' => -1]))
            ->assertSessionHasErrors([
                'meeting_count' => '面談回数は1回以上で入力してください。',
                'price' => '価格は1,000,000円以下で入力してください。',
                'sort_order' => '並び順は0以上で入力してください。',
            ]);

        $this->assertDatabaseCount('meeting_packs', 0);
    }

    public function test_non_integer_values_are_rejected(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();

        $this->actingAs($admin)
            ->post(route('admin.meeting-packs.store'), $this->payload(['meeting_count' => '1.5', 'price' => 'abc', 'sort_order' => 'x']))
            ->assertSessionHasErrors([
                'meeting_count' => '面談回数は整数で入力してください。',
                'price' => '価格は整数で入力してください。',
                'sort_order' => '並び順は整数で入力してください。',
            ]);
    }

    public function test_boundary_values_are_accepted(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();

        // 【解説】ちょうど上限の値はエラーにならないこと(境界値テスト)。
        $this->actingAs($admin)
            ->post(route('admin.meeting-packs.store'), [
                'name' => str_repeat('あ', 100),
                'description' => str_repeat('あ', 2000),
                'meeting_count' => 100,
                'price' => 1000000,
                'stripe_price_id' => str_repeat('a', 255),
                'sort_order' => 65535,
            ])
            ->assertSessionHasNoErrors();

        // 【解説】下限の値も同じく OK。
        $this->actingAs($admin)
            ->post(route('admin.meeting-packs.store'), $this->payload(['meeting_count' => 1, 'price' => 0, 'sort_order' => 0]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('meeting_packs', 2);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    // 【解説】正しい入力値のひな形。テストごとに変えたい項目だけ $overrides で上書きする。
    // 【解説】配列の + 演算子は「左側を優先して足す」ので、$overrides の値が使われる。
    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'name' => '5 回パック',
            'description' => 'まとめて相談したい方向け。',
            'meeting_count' => 5,
            'price' => 12000,
            'stripe_price_id' => 'price_test123',
            'sort_order' => 20,
        ];
    }
}
