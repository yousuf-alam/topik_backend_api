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
        Schema::create('wallet_histories', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->string('type')->nullable();
            $table->string('description')->nullable();
            $table->integer('debit_coins')->default(0);
            $table->integer('credit_coins')->default(0);
            $table->integer('debit_gems')->default(0);
            $table->integer('credit_gems')->default(0);
            $table->integer('coin_balance')->default(0);
            $table->integer('gems_balance')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_histories');
    }
};
