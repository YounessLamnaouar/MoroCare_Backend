<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $appends = ['image_url'];

    protected $hidden = ['image_path'];

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? url('/api/product-images/'.$this->image_path) : null;
    }

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['price_cents' => 'integer', 'stock' => 'integer', 'is_active' => 'boolean', 'requires_prescription' => 'boolean', 'is_demo' => 'boolean'];
    }
}
