<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('email', 150)->unique();
            $table->string('phone', 30);
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropForeign(['customer_id']);
            $table->dropColumn('customer_id');
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropForeign(['customer_id']);
            $table->dropColumn('customer_id');
        });

        Schema::dropIfExists('customers');
    }
};
