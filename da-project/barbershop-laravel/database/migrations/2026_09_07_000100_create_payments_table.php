<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->uuid('reference')->unique();
            $table->string('method', 20)->index();
            $table->string('provider', 30)->index();
            $table->string('provider_reference', 120)->nullable()->index();
            $table->unsignedBigInteger('amount');
            $table->string('status', 20)->default('pending')->index();
            $table->text('qr_payload')->nullable();
            $table->text('qr_url')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        DB::table('orders')->orderBy('id')->each(function (object $order): void {
            $status = match ($order->payment_status) {
                'paid' => 'paid',
                'refunded' => 'refunded',
                default => 'pending',
            };

            DB::table('payments')->insert([
                'order_id' => $order->id,
                'reference' => (string) Str::uuid(),
                'method' => $order->payment_method,
                'provider' => 'legacy',
                'amount' => $order->total,
                'status' => $status,
                'paid_at' => $order->paid_at,
                'metadata' => json_encode(['migrated' => true]),
                'created_at' => $order->created_at,
                'updated_at' => $order->updated_at,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
