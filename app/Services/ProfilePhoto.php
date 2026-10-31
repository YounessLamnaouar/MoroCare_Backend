<?php
namespace App\Services;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
class ProfilePhoto {
 public static function save(Request $request, User $user): void {
  if (!$request->hasFile('photo')) return;
  $file = $request->file('photo');
  $name = Str::uuid().'.'.$file->extension();
  $old = $user->profile_photo;
  Storage::disk('local')->putFileAs('profile-photos', $file, $name);
  try { $user->profile_photo = $name; $user->save(); }
  catch (\Throwable $e) { Storage::disk('local')->delete('profile-photos/'.$name); throw $e; }
  if ($old) Storage::disk('local')->delete('profile-photos/'.$old);
 }
 public const RULE = 'nullable|file|image|mimes:jpg,jpeg,png,webp|max:2048|dimensions:max_width=4096,max_height=4096';
}
