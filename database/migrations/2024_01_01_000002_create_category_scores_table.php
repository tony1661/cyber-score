<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('category_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->string('category_key', 50);
            $table->string('category_name', 100);
            $table->unsignedTinyInteger('score');
            $table->text('rationale');
            $table->json('raw_metrics_json')->nullable();
            $table->timestamps();

            $table->index(['submission_id', 'category_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_scores');
    }
};
