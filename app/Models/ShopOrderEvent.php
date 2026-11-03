<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopOrderEvent extends Model
{
    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id')->select(['id', 'name', 'profile_photo']);
    }

    public $timestamps = false;

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    protected $guarded = ['id'];
}
