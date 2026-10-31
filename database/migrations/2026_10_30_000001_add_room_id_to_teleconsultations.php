<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('teleconsultations', function (Blueprint $table) {
            $table->uuid('room_id')->nullable()->unique();
        });
        DB::table('teleconsultations')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                DB::table('teleconsultations')->where('id', $row->id)->update(['room_id' => (string) Str::uuid()]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('teleconsultations', fn (Blueprint $table) => $table->dropColumn('room_id'));
    }
};
