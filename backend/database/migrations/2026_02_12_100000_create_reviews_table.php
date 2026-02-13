<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->string('organization_id', 32)->index();
            $table->string('review_id', 64)->index();
            $table->string('author', 255)->default('Аноним');
            $table->text('text')->nullable();
            $table->unsignedTinyInteger('rating')->default(0);
            $table->string('published_at', 64)->nullable();
            $table->timestamp('review_date')->nullable()->index();
            $table->timestamps();

            $table->unique(['organization_id', 'review_id']);
        });

        Schema::create('parser_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('organization_id', 32)->index();
            $table->string('yandex_url', 512);
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedInteger('total_expected')->default(0);
            $table->unsignedInteger('total_parsed')->default(0);
            $table->unsignedInteger('current_page')->default(0);
            $table->unsignedInteger('total_pages')->default(0);
            $table->string('current_phase', 30)->default('ssr');
            $table->json('organization_data')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedInteger('retry_count')->default(0);
            $table->timestamp('next_run_at')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parser_tasks');
        Schema::dropIfExists('reviews');
    }
};
