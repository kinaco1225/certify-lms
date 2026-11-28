<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laravel 標準のデータベース通知(Notifiable::notifications())用テーブル。
 *
 * `php artisan notifications:table` が生成する形と同じだが、users.id が ULID のため
 * notifiable は morphs(数値 ID)ではなく ulidMorphs で作る。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            // 【解説】通知の ID は Laravel が UUID(ランダムな文字列)を自動で付けるので、uuid 型の主キーにする。
            $table->uuid('id')->primary();
            // 【解説】type = 通知クラスの名前(例: App\Notifications\QaBoard\QaReplyReceivedNotification)が入る。
            $table->string('type');
            // 【解説】ulidMorphs('notifiable') = 「誰宛の通知か」を表す 2 カラム(notifiable_type + notifiable_id)と索引を作る。
            // 【解説】  notifiable_type には 'App\Models\User'、notifiable_id にはユーザーの ID が入る。
            // 【解説】  標準の morphs() は ID を数値で持つが、このプロジェクトのユーザー ID は ULID(文字列)なので ulidMorphs を使う。
            $table->ulidMorphs('notifiable');
            // 【解説】data = 通知の中身(タイトル・メッセージ・移動先 URL など)を JSON 文字列で保存する。
            $table->text('data');
            // 【解説】read_at = 既読にした日時。null なら未読。
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // 【解説】「このユーザーの未読」を数える(未読バッジ)ときに速く検索できるよう索引を付ける。
            $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
