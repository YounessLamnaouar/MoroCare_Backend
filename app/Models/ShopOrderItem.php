<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopOrderItem extends Model
{
    public function product()
    {
        return $this->belongsTo(Product::class)->select(['id', 'image_path']);
    }

    public $timestamps = false;

    protected $guarded = ['id'];
}
