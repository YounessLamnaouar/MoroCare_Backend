<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Teleconsultation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingAccess
{
    public static function query($query, User $user)
    {
        return match ($user->role) {
            'admin' => $query,
            'doctor' => $query->where('doctor_id', $user->id),
            default => $query->where('patient_id', $user->id),
        };
    }

    public static function authorize($booking): void
    {
        abort_unless(self::query($booking->newQuery(), request()->user())->whereKey($booking->id)->exists(), 403);
    }

    public static function create(string $model, $request, array $data)
    {
        $user = $request->user();
        abort_unless(in_array($user->role, ['user', 'admin']), 403);
        if ($user->role !== 'admin') {
            abort_unless((int) $data['patient_id'] === $user->id, 403);
            $data['patient_id'] = $user->id;
        }
        return DB::transaction(function () use ($model, $data) {
            self::validateSlot($data);
            return $model::create($data)->refresh()->load(['patient:id,name,email,phone,profile_photo', 'doctor']);
        });
    }

    public static function update($booking, array $data): void
    {
        DB::transaction(function () use ($booking, $data) {
            self::validateSlot($data, $booking);
            $booking->update($data);
        });
    }

    public static function status($booking, string $status): void
    {
        $user = request()->user();
        if ($user->role === 'user') {
            abort_unless($status === 'Canceled' && in_array($booking->status, ['Pending', 'Confirmed']), 403);
        }
        self::update($booking, [
            'patient_id' => $booking->patient_id,
            'doctor_id' => $booking->doctor_id,
            'date' => $booking->date->format('Y-m-d'),
            'time' => substr($booking->time, 0, 5),
            'status' => $status,
        ]);
    }

    private static function validateSlot(array $data, $booking = null): void
    {
        if (! User::whereKey($data['patient_id'])->where('role', 'user')->exists()) {
            throw ValidationException::withMessages(['patient_id' => 'Select a patient account.']);
        }
        if (in_array($data['status'] ?? 'Pending', ['Canceled', 'Completed'])) {
            return;
        }
        // Serialize both appointment types on the same doctor row to prevent races.
        if (! User::whereKey($data['doctor_id'])->where('role', 'doctor')->where('is_active', true)->lockForUpdate()->first()) {
            throw ValidationException::withMessages(['doctor_id' => 'Select an active doctor.']);
        }
        if (\Carbon\Carbon::parse($data['date'].' '.$data['time'])->isPast()) {
            throw ValidationException::withMessages(['date' => 'Choose a future date and time.']);
        }
        foreach ([Appointment::class, Teleconsultation::class] as $model) {
            $query = $model::where('doctor_id', $data['doctor_id'])
                ->whereDate('date', $data['date'])->whereTime('time', \Carbon\Carbon::parse($data['time'])->format('H:i:s'))
                ->whereIn('status', ['Pending', 'Confirmed']);
            if ($booking instanceof $model) {
                $query->whereKeyNot($booking->id);
            }
            if ($query->exists()) {
                throw ValidationException::withMessages(['time' => 'This doctor already has a booking at this time.']);
            }
        }
    }
}
