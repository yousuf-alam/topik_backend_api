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
        Schema::create('payment_submissions', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id');
            $table->string('payment_method')->default('bkash');
            $table->string('trans_id');
            $table->string('sender_phone')->nullable();
            $table->integer('amount')->default(0);
            $table->integer('package_id');
            $table->string('status')->default('pending');
            $table->string('message')->nullable();
            $table->softDeletes();


            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_submissions');
    }
};
