<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->string('sku')->unique();
            $t->string('name');
            $t->text('description');
            $t->string('category');
            $t->unsignedInteger('price_cents');
            $t->unsignedInteger('stock');
            $t->boolean('is_active')->default(true);
            $t->boolean('requires_prescription')->default(false);
            $t->boolean('is_demo')->default(true);
            $t->timestamps();
        });
        Schema::create('shop_orders', function (Blueprint $t) {
            $t->id();
            $t->uuid('reference')->unique();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->uuid('client_id');
            $t->string('request_hash', 64);
            $t->string('status')->default('Pending');
            $t->string('payment_method')->default('cash_on_delivery');
            $t->string('payment_status')->default('Unpaid');
            $t->text('recipient');
            $t->text('phone');
            $t->text('address');
            $t->string('city');
            $t->unsignedInteger('subtotal_cents');
            $t->unsignedInteger('delivery_fee_cents');
            $t->unsignedInteger('total_cents');
            $t->timestamps();
            $t->unique(['user_id', 'client_id']);
            $t->index(['user_id', 'created_at']);
        });
        Schema::create('shop_order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('shop_order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->string('name');
            $t->string('sku');
            $t->unsignedInteger('unit_price_cents');
            $t->unsignedInteger('quantity');
            $t->unsignedInteger('line_total_cents');
        });
        Schema::create('shop_order_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('shop_order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->string('status');
            $t->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_order_events');
        Schema::dropIfExists('shop_order_items');
        Schema::dropIfExists('shop_orders');
        Schema::dropIfExists('products');
    }
};
