<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use Illuminate\Http\Request;
use App\Services\BookingAccess;

class AppointmentController extends Controller
{
    public function index(Request $request)
    {
        return BookingAccess::query(Appointment::query(), $request->user())->with(['patient:id,name,email,phone', 'doctor'])->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'patient_id' => 'required|exists:users,id',
            'doctor_id' => 'required|exists:users,id',
            'date' => 'required|date_format:Y-m-d|after_or_equal:today',
            'time' => 'required|date_format:H:i',
            'notes' => 'nullable|string|max:2000',
        ]);

        return BookingAccess::create(Appointment::class, $request, $data);
    }

    public function show(Appointment $appointment)
    {
        BookingAccess::authorize($appointment);
        return $appointment->load(['patient:id,name,email,phone', 'doctor']);
    }

    public function update(Request $request, Appointment $appointment)
    {
        BookingAccess::authorize($appointment);
        $data = $request->validate([
            'patient_id' => 'required|exists:users,id',
            'doctor_id' => 'required|exists:users,id',
            'date' => 'required|date',
            'time' => 'required|date_format:H:i',
            'status' => 'required|in:Pending,Confirmed,Canceled',
            'notes' => 'nullable|string|max:2000',
        ]);

        abort_unless(request()->user()->role === 'admin', 403);
        BookingAccess::update($appointment, $data);

        return response()->json([
            'message' => 'Appointment updated successfully',
            'appointment' => $appointment->load(['patient:id,name,email,phone', 'doctor'])
        ]);
    }

    public function destroy(Appointment $appointment)
    {
        BookingAccess::authorize($appointment);
        abort_unless(request()->user()->role === 'admin', 403);
        $appointment->delete();

        return response()->json(['message' => 'Appointment deleted']);
    }

    public function updateStatus(Request $request, Appointment $appointment)
    {
        BookingAccess::authorize($appointment);
        $data = $request->validate([
            'status' => 'required|in:Pending,Confirmed,Canceled'
        ]);

        BookingAccess::status($appointment, $request->status);

        return response()->json([
            'message' => 'Appointment status updated successfully',
            'appointment' => $appointment->load(['patient:id,name,email,phone', 'doctor'])
        ]);
    }
} 