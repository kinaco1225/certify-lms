<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 質問掲示板(qa-board)の質問スレッド。資格に紐付く公開 Q&A で、投稿は受講生のみ。
 *
 * 削除は物理削除のみ(論理削除・削除履歴は持たない)。配下の回答(qa_replies)は FK cascade で同時に消える。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qa_threads', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('certification_id')
                ->constrained('certifications')
                ->cascadeOnDelete();
            $table->foreignUlid('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('title', 200);
            $table->text('body');
            $table->string('status')->default('open');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            // 一覧の資格 / 状態絞り込み + 新着順、コーチダッシュボードの「担当資格 × open」集計用
            $table->index(['certification_id', 'status', 'created_at']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qa_threads');
    }
};
