<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', fn (Blueprint $t) => $t->string('image_path')->nullable());
        Schema::table('shop_orders', fn (Blueprint $t) => $t->string('number')->nullable()->unique());
        DB::table('shop_orders')->orderBy('id')->chunkById(200, function ($orders) {
            foreach ($orders as $order) {
                $date = \Carbon\Carbon::parse($order->created_at, config('app.timezone'))->format('Ymd');
                DB::table('shop_orders')->where('id', $order->id)->update(['number' => 'CMD-'.$date.'-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('shop_orders', function (Blueprint $t) {
            $t->dropUnique(['number']);
            $t->dropColumn('number');
        });
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn('image_path'));
    }
};
