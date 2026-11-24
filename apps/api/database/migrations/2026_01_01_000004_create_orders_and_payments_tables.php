<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 64)->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('guest_name')->nullable();
            $table->string('guest_email')->nullable();
            $table->string('channel', 30)->default('website'); // website, telegram, whatsapp
            $table->string('status', 30)->default('pending_payment');
            $table->bigInteger('total')->default(0); // integer rupiah
            $table->json('customer_input')->nullable();
            $table->timestamps();

            $table->index(['channel', 'status']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->unsignedInteger('qty')->default(1);
            $table->bigInteger('unit_price'); // integer rupiah
            $table->bigInteger('unit_cost')->default(0); // integer rupiah
            $table->timestamps();
        });

        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('gateway', 50);
            $table->string('ref', 150)->nullable()->unique();
            $table->string('status', 30)->default('pending'); // pending, paid, failed, expired, refunded
            $table->bigInteger('amount'); // integer rupiah
            $table->bigInteger('fee')->default(0); // integer rupiah
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
        });

        Schema::create('supplier_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->string('external_ref', 150)->nullable();
            $table->string('status', 30)->default('queued'); // queued, sent, success, failed, manual_review
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'status']);
        });

        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('type', 30)->default('code'); // code, file, voucher
            $table->text('payload'); // encrypted payload
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->bigInteger('amount'); // integer rupiah
            $table->text('reason')->nullable();
            $table->string('status', 30)->default('pending'); // pending, processed, failed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('deliveries');
        Schema::dropIfExists('supplier_orders');
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
