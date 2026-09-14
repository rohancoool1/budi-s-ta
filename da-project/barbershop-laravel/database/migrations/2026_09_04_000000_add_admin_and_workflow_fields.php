<?php

use Database\Seeders\AdminUserSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_admin')->default(false)->index();
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->string('status', 30)->default('pending')->index();
            $table->text('notes')->nullable();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->string('status', 30)->default('pending')->index();
            $table->text('notes')->nullable();
        });

        Schema::table('contact_messages', function (Blueprint $table): void {
            $table->string('status', 30)->default('new')->index();
            $table->text('notes')->nullable();
        });

        (new AdminUserSeeder)->run();
    }

    public function down(): void
    {
        Schema::table('contact_messages', function (Blueprint $table): void {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'notes']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'notes']);
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'notes']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['is_admin']);
            $table->dropColumn('is_admin');
        });
    }
};
