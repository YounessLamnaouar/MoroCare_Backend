<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ShopOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private function image()
    {
        return UploadedFile::fake()->createWithContent('product.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a1ioAAAAASUVORK5CYII='));
    }

    private function fields(array $extra = []): array
    {
        return array_replace(['sku' => 'TEST', 'name' => 'Demo item', 'description' => 'Demo description', 'category' => 'First aid', 'price_cents' => 1000, 'stock' => 4, 'is_active' => true, 'requires_prescription' => false], $extra);
    }

    private function order(User $user, array $extra = []): ShopOrder
    {
        return ShopOrder::create(array_replace(['reference' => (string) \Illuminate\Support\Str::uuid(), 'user_id' => $user->id, 'client_id' => (string) \Illuminate\Support\Str::uuid(), 'request_hash' => str_repeat('a', 64), 'recipient' => $user->name, 'phone' => '0600000000', 'address' => 'Demo Street 10', 'city' => 'Demo City', 'subtotal_cents' => 1000, 'delivery_fee_cents' => 2000, 'total_cents' => 3000], $extra));
    }

    public function test_admin_image_upload_replacement_removal_and_permissions(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'sanctum');
        $created = $this->post('/api/shop/products', $this->fields(['image' => $this->image()]), ['Accept' => 'application/json'])->assertCreated();
        $id = $created->json('id');
        $p = Product::find($id);
        $first = $p->image_path;
        Storage::disk('local')->assertExists('product-images/'.$first);
        $this->get($created->json('image_url'))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->post('/api/shop/products/'.$id, $this->fields(['_method' => 'PUT', 'image' => $this->image()]), ['Accept' => 'application/json'])->assertOk();
        Storage::disk('local')->assertMissing('product-images/'.$first);
        $second = $p->fresh()->image_path;
        $this->putJson('/api/shop/products/'.$id, $this->fields(['remove_image' => true]))->assertOk()->assertJsonPath('image_url', null);
        Storage::disk('local')->assertMissing('product-images/'.$second);
        $this->post('/api/shop/products', $this->fields(['sku' => 'BAD', 'image' => UploadedFile::fake()->createWithContent('bad.svg', '<svg/>')]), ['Accept' => 'application/json'])->assertUnprocessable();
        $this->actingAs(User::factory()->create(['role' => 'user']), 'sanctum')->putJson('/api/shop/products/'.$id, $this->fields())->assertForbidden();
    }

    public function test_product_filters_apply_before_pagination_and_validate_dates(): void
    {
        Product::create($this->fields(['sku' => 'ONE', 'name' => 'Needle demo', 'stock' => 0, 'created_at' => '2026-01-10 12:00:00']));
        Product::create($this->fields(['sku' => 'TWO', 'name' => 'Bandage demo', 'stock' => 3, 'created_at' => '2026-02-10 12:00:00']));
        $this->getJson('/api/shop/products?category=First%20aid&stock=low&from=2026-02-01&to=2026-02-28')->assertOk()->assertJsonPath('products.total', 1)->assertJsonPath('products.data.0.sku', 'TWO');
        $this->getJson('/api/shop/products?q=Needle&stock=out')->assertJsonPath('products.total', 1);
        $this->getJson('/api/shop/products?from=2026-03-01&to=2026-01-01')->assertUnprocessable();
    }

    public function test_order_numbers_are_unique_stable_and_searchable_with_private_customer_photos(): void
    {
        $user = User::factory()->create(['name' => 'Alpha Patient', 'profile_photo' => 'fake.png']);
        $other = User::factory()->create(['name' => 'Beta Patient']);
        $a = $this->order($user);
        $b = $this->order($other, ['status' => 'Delivered', 'payment_status' => 'Paid']);
        $this->assertMatchesRegularExpression('/^CMD-\d{8}-\d{6,}$/', $a->number);
        $this->assertNotSame($a->number, $b->number);
        $number = $a->number;
        $a->update(['status' => 'Confirmed']);
        $this->assertSame($number, $a->fresh()->number);
        $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum')->getJson('/api/shop/orders?q=Alpha&status=Confirmed&payment=Unpaid')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.customer.name', 'Alpha Patient')->assertJsonPath('data.0.customer.photo_url', url('/api/profile-photos/fake.png'));
        $this->getJson('/api/shop/orders?q='.$b->number.'&payment=Paid')->assertJsonPath('total', 1);
        $this->actingAs($user, 'sanctum')->getJson('/api/shop/orders?q=Beta')->assertOk()->assertJsonPath('total', 0);
    }

    public function test_contact_search_and_dates_are_admin_only(): void
    {
        \Illuminate\Support\Facades\DB::table('contact_messages')->insert([['name' => 'Alpha', 'email' => 'alpha@example.test', 'message' => 'Need help', 'created_at' => '2026-02-10 10:00:00', 'updated_at' => '2026-02-10 10:00:00'], ['name' => 'Beta', 'email' => 'beta@example.test', 'message' => 'Other message', 'created_at' => '2026-03-10 10:00:00', 'updated_at' => '2026-03-10 10:00:00']]);
        $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum')->getJson('/api/contact?q=Alpha&from=2026-02-01&to=2026-02-28')->assertOk()->assertJsonPath('total', 1);
        $this->actingAs(User::factory()->create(), 'sanctum')->getJson('/api/contact?q=Alpha')->assertForbidden();
    }

    public function test_stale_inventory_edit_cannot_restore_stock_reserved_by_an_order(): void
    {
        Storage::fake('local');
        $p = Product::create($this->fields());
        $p->decrement('stock', 1);
        $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum')->post('/api/shop/products/'.$p->id, $this->fields(['_method' => 'PUT', 'expected_stock' => 4, 'image' => $this->image()]), ['Accept' => 'application/json'])->assertStatus(409);
        $this->assertSame(3, $p->fresh()->stock);
        $this->assertNull($p->fresh()->image_path);
        $this->assertSame([], Storage::disk('local')->allFiles('product-images'));
    }
}
