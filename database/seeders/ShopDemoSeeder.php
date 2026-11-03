<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ShopDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Demo products are restricted to local/testing.');
        }
        foreach ([['DEMO-THERM', 'Demo digital thermometer', 'Health devices', 6500, 20], ['DEMO-BAND', 'Demo adhesive bandages', 'First aid', 2500, 30], ['DEMO-MASK', 'Demo face masks (box)', 'Personal care', 3500, 25], ['DEMO-RX', 'Demo prescription item', 'Prescription', 5000, 10]] as [$sku,$name,$category,$price,$stock]) {
            Product::firstOrCreate(['sku' => $sku], ['name' => $name, 'category' => $category, 'price_cents' => $price, 'stock' => $stock, 'description' => 'Demonstration product for local checkout testing. No real sale or delivery.', 'is_active' => true, 'requires_prescription' => $sku === 'DEMO-RX', 'is_demo' => true]);
        }
    }
}
