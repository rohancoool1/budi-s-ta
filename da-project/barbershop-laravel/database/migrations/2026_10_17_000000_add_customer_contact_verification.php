<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
        });

        Schema::create('customer_verification_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 10);
            $table->string('destination', 150);
            $table->uuid('generation');
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('issued_at');
            $table->timestamp('expires_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            $table->unique(['customer_id', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_verification_codes');
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn(['email_verified_at', 'phone_verified_at']);
        });
    }
};
