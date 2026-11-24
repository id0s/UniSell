<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('website')->nullable();
            $table->string('docs_url')->nullable();
            $table->string('auth_method', 50)->default('api_key'); // api_key, bearer, basic, manual
            $table->string('status', 30)->default('inactive'); // active, inactive, suspended
            $table->string('health_status', 30)->default('unknown'); // healthy, degraded, down, unknown
            $table->bigInteger('balance')->default(0); // integer rupiah
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('supplier_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->text('payload'); // encrypted JSON payload
            $table->timestamps();

            $table->unique('supplier_id');
        });

        Schema::create('supplier_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->string('name');
            $table->bigInteger('price')->default(0); // integer rupiah
            $table->unsignedInteger('period_days')->default(30);
            $table->unsignedInteger('product_limit')->nullable();
            $table->timestamps();
        });

        Schema::create('supplier_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('package_id')->constrained('supplier_packages')->restrictOnDelete();
            $table->string('status', 30)->default('active'); // active, expired, cancelled
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamps();

            $table->index(['supplier_id', 'status']);
        });

        Schema::create('supplier_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->string('external_id', 150);
            $table->string('name');
            $table->bigInteger('cost_price')->default(0); // integer rupiah
            $table->string('stock_status', 30)->default('available'); // available, out_of_stock, discontinued
            $table->json('raw')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['supplier_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_products');
        Schema::dropIfExists('supplier_subscriptions');
        Schema::dropIfExists('supplier_packages');
        Schema::dropIfExists('supplier_credentials');
        Schema::dropIfExists('suppliers');
    }
};
