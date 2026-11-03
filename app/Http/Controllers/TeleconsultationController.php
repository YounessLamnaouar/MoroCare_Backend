<?php

namespace App\Http\Controllers;

use App\Models\Teleconsultation;
use Illuminate\Http\Request;
use App\Services\BookingAccess;

class TeleconsultationController extends Controller
{
    public function index(Request $request)
    {
        return BookingAccess::query(Teleconsultation::query(), $request->user())->with(['patient:id,name,email,phone,profile_photo', 'doctor'])->get();
    }

    public function getUserTeleconsultations(Request $request)
    {
        $user = $request->user();
        return Teleconsultation::with(['patient:id,name,email,phone,profile_photo', 'doctor'])
            ->where('patient_id', $user->id)
            ->orderBy('date', 'asc')
            ->orderBy('time', 'asc')
            ->get();
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

        return BookingAccess::create(Teleconsultation::class, $request, $data);
    }

    public function show(Teleconsultation $teleconsultation)
    {
        BookingAccess::authorize($teleconsultation);
        return $teleconsultation->load(['patient:id,name,email,phone,profile_photo', 'doctor']);
    }

    public function update(Request $request, Teleconsultation $teleconsultation)
    {
        BookingAccess::authorize($teleconsultation);
        $data = $request->validate([
            'patient_id' => 'required|exists:users,id',
            'doctor_id' => 'required|exists:users,id',
            'date' => 'required|date',
            'time' => 'required|date_format:H:i',
            'status' => 'required|in:Pending,Confirmed,Canceled,Completed',
            'notes' => 'nullable|string|max:2000',
        ]);

        abort_unless(request()->user()->role === 'admin', 403);
        BookingAccess::update($teleconsultation, $data);

        return response()->json([
            'message' => 'Teleconsultation updated successfully',
            'teleconsultation' => $teleconsultation->load(['patient:id,name,email,phone,profile_photo', 'doctor'])
        ]);
    }

    public function destroy(Teleconsultation $teleconsultation)
    {
        BookingAccess::authorize($teleconsultation);
        abort_unless(request()->user()->role === 'admin', 403);
        $teleconsultation->delete();

        return response()->json(['message' => 'Teleconsultation deleted']);
    }

    public function updateStatus(Request $request, Teleconsultation $teleconsultation)
    {
        BookingAccess::authorize($teleconsultation);
        $data = $request->validate([
            'status' => 'required|in:Pending,Confirmed,Canceled,Completed'
        ]);

        BookingAccess::status($teleconsultation, $request->status);

        return response()->json([
            'message' => 'Teleconsultation status updated successfully',
            'teleconsultation' => $teleconsultation->load(['patient:id,name,email,phone,profile_photo', 'doctor'])
        ]);
    }

} 