<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DoctorController extends Controller
{
    public function index()
    {
        return Doctor::all();
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:doctors',
            'password' => 'required|string|min:6',
            'speciality' => 'required|string',
            'phone' => 'nullable|string|min:10',
            'address' => 'nullable|string|min:5',
        ]);

        return Doctor::create($request->all());
    }

    public function show(Doctor $doctor)
    {
        return $doctor;
    }

    public function update(Request $request, Doctor $doctor)
    {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:doctors,email,' . $doctor->id,
            'speciality' => 'required|string',
            'phone' => 'nullable|string|min:10',
            'address' => 'nullable|string|min:5',
        ]);

        $doctor->update($request->all());

        return response()->json([
            'message' => 'Doctor updated successfully',
            'doctor' => $doctor
        ]);
    }

    public function destroy(Doctor $doctor)
    {
        $doctor->delete();

        return response()->json(['message' => 'Doctor deleted']);
    }
}
