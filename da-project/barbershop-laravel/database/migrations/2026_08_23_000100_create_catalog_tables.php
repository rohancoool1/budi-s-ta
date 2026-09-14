<?php

use Database\Seeders\BarbershopSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barbers', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 40)->unique();
            $table->string('initials', 5);
            $table->string('name', 100);
            $table->string('role', 150);
            $table->string('color', 20)->default('#9faa8d');
            $table->text('bio')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 40)->unique();
            $table->string('name', 120);
            $table->unsignedSmallInteger('duration_minutes');
            $table->unsignedBigInteger('price');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->string('name', 150);
            $table->string('category', 80)->index();
            $table->unsignedBigInteger('price');
            $table->string('size', 50);
            $table->string('color', 20)->default('#c9c3b8');
            $table->string('badge', 40)->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('gallery_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barber_id')->nullable()->constrained()->nullOnDelete();
            $table->string('style', 120);
            $table->string('client', 100);
            $table->text('quote');
            $table->string('image_path')->default('haircut-gallery.png');
            $table->string('position', 20)->default('50% 50%');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->string('group', 50)->default('general');
            $table->timestamps();
        });

        (new BarbershopSeeder)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
        Schema::dropIfExists('gallery_entries');
        Schema::dropIfExists('products');
        Schema::dropIfExists('services');
        Schema::dropIfExists('barbers');
    }
};
