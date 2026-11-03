<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ShopOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ShopController extends Controller
{
    private function available(): void
    {
        abort_unless(app()->environment(['local', 'testing']) || config('shop.enabled'), 503, 'Shop is not enabled.');
    }

    public function products(Request $r)
    {
        $this->available();
        $admin = $r->user('sanctum')?->role === 'admin';
        \App\Services\ListFilters::validate($r, ['category' => 'nullable|string|max:80', 'active' => 'nullable|in:active,inactive', 'stock' => 'nullable|in:available,low,out', 'sort' => 'nullable|in:name,price_asc,price_desc,newest']);
        $base = Product::query()->when(! $admin, fn ($q) => $q->where('is_active', true));
        $categories = (clone $base)->select('category')->distinct()->orderBy('category')->pluck('category');
        $query = (clone $base)->when($r->filled('q'), fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$r->input('q').'%')->orWhere('sku', 'like', '%'.$r->input('q').'%')))
            ->when($r->filled('category'), fn ($q) => $q->where('category', $r->input('category')))
            ->when($admin && $r->filled('active'), fn ($q) => $q->where('is_active', $r->input('active') === 'active'));
        if ($r->input('stock') === 'available') {
            $query->where('stock', '>', 0);
        }
        if ($r->input('stock') === 'low') {
            $query->whereBetween('stock', [1, 5]);
        }
        if ($r->input('stock') === 'out') {
            $query->where('stock', 0);
        }
        \App\Services\ListFilters::dates($query, $r);
        match ($r->input('sort')) {
            'price_asc' => $query->orderBy('price_cents'),'price_desc' => $query->orderByDesc('price_cents'),'newest' => $query->latest('created_at'),default => $query->orderBy('name')
        };

        return response()->json(['products' => $query->orderBy('id')->paginate(12), 'categories' => $categories, 'delivery_fee_cents' => (int) config('shop.delivery_fee_cents'), 'currency' => 'MAD', 'demo' => true]);
    }

    public function saveProduct(Request $r, ?Product $product = null)
    {
        $this->available();
        $data = $r->validate(['sku' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('products')->ignore($product?->id)], 'name' => 'required|string|max:160', 'description' => 'required|string|max:2000', 'category' => 'required|string|max:80', 'price_cents' => 'required|integer|min:1|max:10000000', 'stock' => 'required|integer|min:0|max:100000', 'is_active' => 'required|boolean', 'requires_prescription' => 'required|boolean', 'image' => \App\Services\ProfilePhoto::RULE, 'remove_image' => 'sometimes|boolean', 'expected_stock' => 'sometimes|integer|min:0']);
        if ($r->hasFile('image') && $r->boolean('remove_image')) {
            throw ValidationException::withMessages(['image' => 'Choose an upload or remove the image, not both.']);
        }
        unset($data['image'],$data['remove_image']);
        $newPath = null;
        $oldPath = null;
        if ($r->hasFile('image')) {
            $name = Str::uuid().'.'.$r->file('image')->extension();
            $stored = \Illuminate\Support\Facades\Storage::disk('local')->putFileAs('product-images', $r->file('image'), $name);
            if (! $stored) {
                throw new \RuntimeException('Image upload failed');
            }$newPath = $name;
        }
        try {
            $product = DB::transaction(function () use ($product, $data, $r, $newPath, &$oldPath) {
                $record = $product ? Product::whereKey($product->id)->lockForUpdate()->firstOrFail() : new Product(['is_demo' => true]);
                if ($record->exists && $r->filled('expected_stock') && $record->stock !== $r->integer('expected_stock')) {
                    abort(409, 'Product stock changed. Refresh and reopen the product before saving.');
                }
                $oldPath = $record->image_path;
                $record->fill($data);
                if ($newPath || $r->boolean('remove_image')) {
                    $record->image_path = $newPath;
                }
                $record->save();

                return $record;
            }, 3);
        } catch (\Throwable $e) {
            if ($newPath) {
                \Illuminate\Support\Facades\Storage::disk('local')->delete('product-images/'.$newPath);
            }throw $e;
        }
        if ($oldPath && $oldPath !== $product->image_path) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete('product-images/'.$oldPath);
        }

        return response()->json($product, $r->isMethod('post') ? 201 : 200);
    }

    public function quote(Request $r)
    {
        $this->available();
        $data = $r->validate(['items' => 'required|array|min:1|max:30', 'items.*.product_id' => 'required|integer|distinct', 'items.*.quantity' => 'required|integer|min:1|max:20']);
        $items = [];
        $subtotal = 0;
        foreach ($data['items'] as $line) {
            $p = Product::find($line['product_id']);
            if (! $p || ! $p->is_active || $p->requires_prescription || $p->stock < $line['quantity']) {
                throw ValidationException::withMessages(['items' => 'A product is unavailable or has insufficient stock.']);
            }
            $total = $p->price_cents * $line['quantity'];
            $subtotal += $total;
            if ($subtotal > 100000000) {
                throw ValidationException::withMessages(['items' => 'Order exceeds the maximum total.']);
            }
            $items[] = ['product_id' => $p->id, 'name' => $p->name, 'unit_price_cents' => $p->price_cents, 'image_url' => $p->image_url, 'quantity' => $line['quantity'], 'stock' => $p->stock, 'line_total_cents' => $total];
        }

        return response()->json(['items' => $items, 'subtotal_cents' => $subtotal, 'delivery_fee_cents' => (int) config('shop.delivery_fee_cents'), 'total_cents' => $subtotal + (int) config('shop.delivery_fee_cents')]);
    }

    public function orders(Request $r)
    {
        $this->available();
        \App\Services\ListFilters::validate($r, ['status' => 'nullable|in:Pending,Confirmed,Shipped,Delivered,Canceled', 'payment' => 'nullable|in:Paid,Unpaid']);
        $query = ShopOrder::with(['items.product', 'events.actor', 'customer'])->when($r->user()->role !== 'admin', fn ($q) => $q->where('user_id', $r->user()->id));
        $query->when($r->filled('q'), fn ($q) => $q->where(fn ($q) => $q->where('number', 'like', '%'.$r->input('q').'%')->orWhere('reference', 'like', '%'.$r->input('q').'%')->orWhere('city', 'like', '%'.$r->input('q').'%')->orWhereHas('customer', fn ($q) => $q->where('name', 'like', '%'.$r->input('q').'%'))))
            ->when($r->filled('status'), fn ($q) => $q->where('status', $r->input('status')))->when($r->filled('payment'), fn ($q) => $q->where('payment_status', $r->input('payment')));

        return \App\Services\ListFilters::dates($query, $r)->latest('id')->paginate(10);
    }

    public function createOrder(Request $r)
    {
        $this->available();
        $data = $r->validate(['client_id' => 'required|uuid', 'expected_total_cents' => 'required|integer|min:1', 'recipient' => 'required|string|max:160', 'phone' => 'required|string|min:10|max:30', 'address' => 'required|string|min:8|max:500', 'city' => 'required|string|max:100',
            'items' => 'required|array|min:1|max:30', 'items.*.product_id' => 'required|integer|distinct', 'items.*.quantity' => 'required|integer|min:1|max:20']);
        usort($data['items'], fn ($a, $b) => $a['product_id'] <=> $b['product_id']);
        $hash = hash('sha256', json_encode($data));
        $order = DB::transaction(function () use ($r, $data, $hash) {
            // Serialize duplicate checkout requests for this patient, then lock stock in ID order.
            \App\Models\User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();
            $existing = ShopOrder::where('user_id', $r->user()->id)->where('client_id', $data['client_id'])->first();
            if ($existing) {
                abort_unless(hash_equals($existing->request_hash, $hash), 409);

                return $existing;
            }
            $lines = [];
            $subtotal = 0;
            foreach ($data['items'] as $line) {
                $p = Product::whereKey($line['product_id'])->lockForUpdate()->first();
                if (! $p || ! $p->is_active || $p->requires_prescription || $p->stock < $line['quantity']) {
                    throw ValidationException::withMessages(['items' => 'A product is unavailable, requires prescription review, or has insufficient stock.']);
                }
                $total = $p->price_cents * $line['quantity'];
                $subtotal += $total;
                if ($subtotal > 100000000) {
                    throw ValidationException::withMessages(['items' => 'Order exceeds the maximum total.']);
                }
                $lines[] = ['product_id' => $p->id, 'name' => $p->name, 'sku' => $p->sku, 'unit_price_cents' => $p->price_cents, 'quantity' => $line['quantity'], 'line_total_cents' => $total];
                $p->decrement('stock', $line['quantity']);
            }
            $fee = (int) config('shop.delivery_fee_cents');
            if ($subtotal + $fee !== $data['expected_total_cents']) {
                throw ValidationException::withMessages(['total' => 'Prices changed. Refresh the cart before ordering.']);
            }
            $order = ShopOrder::create(['reference' => (string) Str::uuid(), 'user_id' => $r->user()->id, 'client_id' => $data['client_id'], 'request_hash' => $hash,
                'recipient' => $data['recipient'], 'phone' => $data['phone'], 'address' => $data['address'], 'city' => $data['city'], 'subtotal_cents' => $subtotal, 'delivery_fee_cents' => $fee, 'total_cents' => $subtotal + $fee]);
            $order->items()->createMany($lines);
            $order->events()->create(['actor_id' => $r->user()->id, 'status' => 'Pending', 'created_at' => now()]);

            return $order;
        }, 3);

        return response()->json($order->fresh()->load(['items.product', 'events.actor', 'customer']), 201);
    }

    public function status(Request $r, ShopOrder $order)
    {
        $this->available();
        abort_unless($r->user()->role === 'admin' || $order->user_id === $r->user()->id, 403);
        $data = $r->validate(['status' => 'required|in:Confirmed,Shipped,Delivered,Canceled']);
        $updated = DB::transaction(function () use ($r, $order, $data) {
            $order = ShopOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $next = $data['status'];
            if ($order->status === $next) {
                return $order;
            }
            $allowed = ['Pending' => ['Confirmed', 'Canceled'], 'Confirmed' => ['Shipped', 'Canceled'], 'Shipped' => ['Delivered']];
            abort_unless(in_array($next, $allowed[$order->status] ?? []), 409);
            if ($r->user()->role !== 'admin') {
                abort_unless($order->status === 'Pending' && $next === 'Canceled', 403);
            }
            if ($next === 'Canceled') {
                foreach ($order->items()->orderBy('product_id')->get() as $item) {
                    Product::whereKey($item->product_id)->lockForUpdate()->firstOrFail()->increment('stock', $item->quantity);
                }
            }
            $order->update(['status' => $next, 'payment_status' => $next === 'Delivered' ? 'Paid' : 'Unpaid']);
            $order->events()->create(['actor_id' => $r->user()->id, 'status' => $next, 'created_at' => now()]);

            return $order;
        }, 3);

        return $updated->load(['items.product', 'events.actor', 'customer']);
    }
}
