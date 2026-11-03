<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ShopOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShopTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $extra = []): Product
    {
        return Product::create(array_replace(['sku' => (string) Str::uuid(), 'name' => 'Demo bandage', 'description' => 'Demo only', 'category' => 'First aid', 'price_cents' => 2500, 'stock' => 5, 'is_active' => true, 'requires_prescription' => false, 'is_demo' => true], $extra));
    }

    private function payload(Product $p): array
    {
        return ['client_id' => (string) Str::uuid(), 'recipient' => 'Test Patient', 'phone' => '0600000000', 'address' => '10 Demo Street', 'city' => 'Demo City', 'expected_total_cents' => 7000, 'items' => [['product_id' => $p->id, 'quantity' => 2]]];
    }

    public function test_checkout_computes_totals_is_idempotent_and_preserves_snapshots(): void
    {
        $p = $this->product();
        $body = $this->payload($p);
        $body['total_cents'] = 1;
        $this->actingAs(User::factory()->create(['role' => 'user']), 'sanctum');
        $id = $this->postJson('/api/shop/orders', $body)->assertCreated()->assertJsonPath('total_cents', 7000)->json('id');
        $this->postJson('/api/shop/orders', $body)->assertCreated()->assertJsonPath('id', $id);
        $this->assertEquals(3, $p->fresh()->stock);
        $this->assertDatabaseCount('shop_orders', 1);
        $body['city'] = 'Changed';
        $this->postJson('/api/shop/orders', $body)->assertStatus(409);
        $p->update(['name' => 'Changed name', 'price_cents' => 3000]);
        $this->getJson('/api/shop/orders')->assertOk()->assertJsonPath('data.0.items.0.name', 'Demo bandage')->assertJsonPath('data.0.items.0.unit_price_cents', 2500);
        $this->assertStringNotContainsString('Demo Street', \Illuminate\Support\Facades\DB::table('shop_orders')->value('address'));
    }

    public function test_stock_and_prescription_checks_are_atomic_and_price_changes_require_review(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'user']), 'sanctum');
        $p = $this->product();
        $body = $this->payload($p);
        $this->postJson('/api/shop/quote', ['items' => $body['items']])->assertOk()->assertJsonPath('total_cents', 7000);
        $rx = $this->product(['requires_prescription' => true]);
        $body['items'][] = ['product_id' => $rx->id, 'quantity' => 1];
        $this->postJson('/api/shop/orders', $body)->assertUnprocessable();
        $this->assertEquals(5, $p->fresh()->stock);
        $body = $this->payload($p);
        $body['expected_total_cents'] = 1;
        $this->postJson('/api/shop/orders', $body)->assertUnprocessable();
        $this->assertEquals(5, $p->fresh()->stock);
        $body = $this->payload($p);
        $body['items'][0]['quantity'] = 6;
        $this->postJson('/api/shop/orders', $body)->assertUnprocessable();
        $p->update(['is_active' => false]);
        $this->postJson('/api/shop/quote', ['items' => $body['items']])->assertUnprocessable();
        $this->assertDatabaseCount('shop_orders', 0);
    }

    public function test_orders_are_private_and_cancel_restores_stock_once(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $p = $this->product();
        $this->actingAs($user, 'sanctum');
        $id = $this->postJson('/api/shop/orders', $this->payload($p))->json('id');
        $this->patchJson('/api/shop/orders/'.$id.'/status', ['status' => 'Confirmed'])->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'user']), 'sanctum');
        $this->getJson('/api/shop/orders')->assertJsonCount(0, 'data');
        $this->patchJson('/api/shop/orders/'.$id.'/status', ['status' => 'Canceled'])->assertForbidden();
        $this->actingAs($user, 'sanctum');
        $this->patchJson('/api/shop/orders/'.$id.'/status', ['status' => 'Canceled'])->assertOk();
        $this->patchJson('/api/shop/orders/'.$id.'/status', ['status' => 'Canceled'])->assertOk();
        $this->assertEquals(5, $p->fresh()->stock);
        $this->assertDatabaseCount('shop_order_events', 2);
    }

    public function test_admin_fulfillment_state_and_product_permissions(): void
    {
        $patient = User::factory()->create(['role' => 'user']);
        $p = $this->product();
        $this->actingAs($patient, 'sanctum');
        $id = $this->postJson('/api/shop/orders', $this->payload($p))->json('id');
        $this->postJson('/api/shop/products', [])->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'doctor']), 'sanctum')->getJson('/api/shop/orders')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
        $this->patchJson('/api/shop/orders/'.$id.'/status', ['status' => 'Delivered'])->assertStatus(409);
        foreach (['Confirmed', 'Shipped', 'Delivered'] as $status) {
            $this->patchJson('/api/shop/orders/'.$id.'/status', ['status' => $status])->assertOk()->assertJsonPath('status', $status);
        }
        $this->assertEquals('Paid', ShopOrder::find($id)->payment_status);
        $this->patchJson('/api/shop/orders/'.$id.'/status', ['status' => 'Canceled'])->assertStatus(409);
        $this->assertEquals(3, $p->fresh()->stock);
        $data = $p->only(['sku', 'name', 'description', 'category', 'stock', 'price_cents', 'is_active', 'requires_prescription']);
        $data['sku'] = 'NEW';
        $this->postJson('/api/shop/products', $data)->assertCreated();
        $data['stock'] = 8;
        $this->putJson('/api/shop/products/'.$p->id, $data)->assertUnprocessable(); // duplicate SKU cannot overwrite another product
    }

    public function test_specialty_list_rejects_unlisted_specialties(): void
    {
        $this->getJson('/api/specialities')->assertOk()->assertJsonFragment(['Cardiology']);
        $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum')->postJson('/api/doctors',['name' => 'Doctor', 'email' => 'test@doctor.test', 'password' => 'Doctor-Password-2026!', 'speciality' => 'Made up'])->assertUnprocessable();
    }
}
