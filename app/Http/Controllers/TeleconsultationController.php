<?php

namespace App\Http\Controllers;

use App\Models\Teleconsultation;
use Illuminate\Http\Request;

class TeleconsultationController extends Controller
{
    public function index()
    {
        return Teleconsultation::with(['patient', 'doctor'])->get();
    }

    public function getUserTeleconsultations(Request $request)
    {
        $user = $request->user();
        return Teleconsultation::with(['patient', 'doctor'])
            ->where('patient_id', $user->id)
            ->orderBy('date', 'asc')
            ->orderBy('time', 'asc')
            ->get();
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

        return Teleconsultation::create($request->all());
    }

    public function show(Teleconsultation $teleconsultation)
    {
        return Teleconsultation::with(['patient', 'doctor'])->find($teleconsultation->id);
    }

    public function update(Request $request, Teleconsultation $teleconsultation)
    {
        $request->validate([
            'patient_id' => 'required|exists:users,id',
            'doctor_id' => 'required|exists:doctors,id',
            'date' => 'required|date',
            'time' => 'required|date_format:H:i',
            'status' => 'required|in:Pending,Confirmed,Canceled,Completed',
            'notes' => 'nullable|string',
            'meeting_link' => 'nullable|string',
        ]);

        $teleconsultation->update($request->all());

        return response()->json([
            'message' => 'Teleconsultation updated successfully',
            'teleconsultation' => Teleconsultation::with(['patient', 'doctor'])->find($teleconsultation->id)
        ]);
    }

    public function destroy(Teleconsultation $teleconsultation)
    {
        $teleconsultation->delete();

        return response()->json(['message' => 'Teleconsultation deleted']);
    }

    public function updateStatus(Request $request, Teleconsultation $teleconsultation)
    {
        $request->validate([
            'status' => 'required|in:Pending,Confirmed,Canceled,Completed'
        ]);

        $teleconsultation->update(['status' => $request->status]);

        return response()->json([
            'message' => 'Teleconsultation status updated successfully',
            'teleconsultation' => $teleconsultation->with(['patient', 'doctor'])
        ]);
    }

} 