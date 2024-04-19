<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->string('country_code')->default('KR');
            $table->string('type')->default('reading');
            $table->mediumText('title')->nullable();
            $table->bigInteger('topic_code')->nullable();
            $table->string('description')->nullable();
            $table->string('image')->nullable();
            $table->string('audio')->nullable();
            $table->tinyInteger('difficulty_level')->nullable();
            $table->bigInteger('right_answer')->nullable();
            $table->integer('time')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
