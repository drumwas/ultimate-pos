<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mpesa_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('type')->default('stk_push');          // stk_push | c2b | b2c
            $table->string('status')->default('pending')->index(); // pending | completed | failed | cancelled
            $table->string('phone', 15);
            $table->unsignedInteger('amount');
            $table->string('reference')->index();                  // order/invoice reference

            // STK Push
            $table->string('checkout_request_id')->nullable()->index();
            $table->string('merchant_request_id')->nullable();

            // C2B / B2C
            $table->string('conversation_id')->nullable()->index();
            $table->string('originator_conversation_id')->nullable();

            // Completion details
            $table->string('mpesa_receipt_number')->nullable()->index();
            $table->string('result_code')->nullable();
            $table->string('result_desc')->nullable();

            // Raw payloads
            $table->longText('raw_response')->nullable();
            $table->longText('raw_callback')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mpesa_transactions');
    }
};
