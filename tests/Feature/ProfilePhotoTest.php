<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
class ProfilePhotoTest extends TestCase {
 use RefreshDatabase;
 public function test_all_roles_can_upload_replace_and_publish_a_valid_photo(): void {
  Storage::fake('local');
  foreach (['user','doctor','admin'] as $role) {
   $user=User::factory()->create(['role'=>$role]);
   $this->actingAs($user,'sanctum');
   $image=fn()=>UploadedFile::fake()->createWithContent('avatar.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a1ioAAAAASUVORK5CYII='));
   $result=$this->post('/api/user/profile',['_method'=>'PUT','photo'=>$image()],['Accept'=>'application/json'])->assertOk();
   $old=$user->fresh()->profile_photo;
   Storage::disk('local')->assertExists('profile-photos/'.$old);
   $this->get($result->json('photo_url'))->assertOk()->assertHeader('X-Content-Type-Options','nosniff');
   $this->post('/api/user/profile',['_method'=>'PUT','photo'=>$image()],['Accept'=>'application/json'])->assertOk();
   Storage::disk('local')->assertMissing('profile-photos/'.$old);
   $this->post('/api/user/profile',['_method'=>'PUT','photo'=>UploadedFile::fake()->createWithContent('evil.svg','<svg/>')],['Accept'=>'application/json'])->assertUnprocessable();
  }
 }
}
