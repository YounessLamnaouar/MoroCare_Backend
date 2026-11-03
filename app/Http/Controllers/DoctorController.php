<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DoctorController extends Controller
{
    private const FIELDS = ['id', 'name', 'speciality', 'address', 'phone', 'profile_photo'];

    public function index(Request $request)
    {
        $admin = $request->user('sanctum')?->role === 'admin';

        return User::where('role', 'doctor')->when(! $admin, fn ($q) => $q->where('is_active', true))
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q')->trim().'%';
                $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('speciality', 'like', $term)->orWhere('address', 'like', $term));
            })->orderBy('name')->get($admin ? [...self::FIELDS, 'email', 'is_active', 'created_at'] : self::FIELDS);
    }

    public function store(Request $request)
    {
        $request->merge(['email' => strtolower(trim((string) $request->email))]);
        $data = $request->validate($this->rules());
        $user = User::create($data + ['role' => 'doctor', 'must_change_password' => true]);

        return response()->json($user->only([...self::FIELDS, 'email', 'is_active', 'created_at']), 201);
    }

    public function show(Request $request, User $doctor)
    {
        abort_unless($doctor->role === 'doctor', 404);
        $viewer = $request->user('sanctum');
        $private = $viewer && ($viewer->role === 'admin' || $viewer->id === $doctor->id);
        abort_unless($doctor->is_active || $private, 404);

        return response()->json($doctor->only($private ? [...self::FIELDS, 'email', 'is_active', 'created_at'] : self::FIELDS));
    }

    public function update(Request $request, User $doctor)
    {
        abort_unless($doctor->role === 'doctor', 404);
        $request->merge(['email' => strtolower(trim((string) $request->email))]);
        $data = $request->validate($this->rules($doctor));
        if (empty($data['password'])) {
            unset($data['password']);
        }
        if (isset($data['password'])) {
            $data['must_change_password'] = true;
        }
        $doctor->update($data);
        if (isset($data['password']) || ! $doctor->is_active) {
            $doctor->tokens()->delete();
        }

        return response()->json(['message' => 'Doctor updated successfully', 'doctor' => $doctor->only([...self::FIELDS, 'email', 'is_active', 'created_at'])]);
    }

    public function destroy(User $doctor)
    {
        abort_unless($doctor->role === 'doctor', 404);
        // Keep appointment and consultation history; remove access and directory visibility.
        $doctor->update(['is_active' => false]);
        $doctor->tokens()->delete();

        return response()->json(['message' => 'Doctor deactivated']);
    }

    private function rules(?User $doctor = null): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($doctor?->id)],
            'password' => [$doctor ? 'nullable' : 'required', 'string', 'min:12', 'max:128'],
            'speciality' => ['required', 'string', Rule::in(array_unique([...config('specialities'), ...($doctor ? [$doctor->speciality] : [])]))],
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
