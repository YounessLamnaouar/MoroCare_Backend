<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    public function index()
    {
        return Appointment::with(['patient', 'doctor'])->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'patient_id' => 'required|exists:users,id',
            'doctor_id' => 'required|exists:doctors,id',
            'date' => 'required|date|after:today',
            'time' => 'required|date_format:H:i',
            'notes' => 'nullable|string',
        ]);

        return Appointment::create($request->all());
    }

    public function show(Appointment $appointment)
    {
        return $appointment->with(['patient', 'doctor']);
    }

    public function update(Request $request, Appointment $appointment)
    {
        $request->validate([
            'patient_id' => 'required|exists:users,id',
            'doctor_id' => 'required|exists:doctors,id',
            'date' => 'required|date',
            'time' => 'required|date_format:H:i',
            'status' => 'required|in:Pending,Confirmed,Canceled',
            'notes' => 'nullable|string',
        ]);

        $appointment->update($request->all());

        return response()->json([
            'message' => 'Appointment updated successfully',
            'appointment' => $appointment->with(['patient', 'doctor'])
        ]);
    }

    public function destroy(Appointment $appointment)
    {
        $appointment->delete();

        return response()->json(['message' => 'Appointment deleted']);
    }

    public function updateStatus(Request $request, Appointment $appointment)
    {
        $request->validate([
            'status' => 'required|in:Pending,Confirmed,Canceled'
        ]);

        $appointment->update(['status' => $request->status]);

        return response()->json([
            'message' => 'Appointment status updated successfully',
            'appointment' => $appointment->with(['patient', 'doctor'])
        ]);
    }
} 