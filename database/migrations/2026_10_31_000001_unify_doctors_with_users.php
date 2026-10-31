<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Preflight before any DDL: never merge a patient/admin into a doctor.
        $mapping = [];
        foreach (DB::table('doctors')->get() as $doctor) {
            $linked = DB::table('users')->where('doctor_id', $doctor->id)->get();
            $account = $linked->first() ?? DB::table('users')->where('email', $doctor->email)->first();
            if ($linked->count() > 1 || ($account && ($account->role !== 'doctor' || ($account->doctor_id && $account->doctor_id !== $doctor->id)))) {
                throw new RuntimeException("Resolve conflicting account for legacy doctor {$doctor->id} before migrating.");
            }
            $mapping[$doctor->id] = $account?->id;
        }
        Schema::table('users', function (Blueprint $table) {
            $table->string('speciality')->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('must_change_password')->default(false);
        });
        foreach (DB::table('doctors')->get() as $doctor) {
            $id = $mapping[$doctor->id];
            if (! $id) {
                $id = DB::table('users')->insertGetId([
                    'name' => $doctor->name, 'email' => $doctor->email,
                    'password' => $doctor->password, 'role' => 'doctor', 'phone' => $doctor->phone,
                    'created_at' => $doctor->created_at, 'updated_at' => now(),
                ]);
            }
            DB::table('users')->where('id', $id)->update(['speciality' => $doctor->speciality, 'address' => $doctor->address]);
            $mapping[$doctor->id] = $id;
        }
        foreach (['appointments', 'teleconsultations'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->foreignId('doctor_user_id')->nullable()->constrained('users')->restrictOnDelete());
            foreach ($mapping as $legacy => $userId) {
                DB::table($name)->where('doctor_id', $legacy)->update(['doctor_user_id' => $userId]);
            }
            if (DB::table($name)->whereNull('doctor_user_id')->exists()) {
                throw new RuntimeException("Unmapped doctor in {$name}; restore backup and repair references.");
            }
            Schema::table($name, fn (Blueprint $table) => $table->dropConstrainedForeignId('doctor_id'));
            Schema::table($name, fn (Blueprint $table) => $table->renameColumn('doctor_user_id', 'doctor_id'));
            Schema::table($name, fn (Blueprint $table) => $table->index(['doctor_id', 'date', 'time']));
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('doctor_id'));
        Schema::drop('doctors');
    }

    public function down(): void
    {
        throw new RuntimeException('Account consolidation is a forward-only data migration. Restore the pre-migration database backup to roll back.');
    }
};
