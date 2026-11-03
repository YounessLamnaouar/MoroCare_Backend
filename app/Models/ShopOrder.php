<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopOrder extends Model
{
    protected static function booted(): void
    {
        static::created(function ($order) {
            $order->forceFill(['number' => 'CMD-'.$order->created_at->timezone(config('app.timezone'))->format('Ymd').'-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT)])->saveQuietly();
        });
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'user_id')->select(['id', 'name', 'profile_photo']);
    }

    protected $guarded = ['id'];

    protected $hidden = ['request_hash', 'client_id'];

    protected function casts(): array
    {
        return ['user_id' => 'integer', 'recipient' => 'encrypted', 'phone' => 'encrypted', 'address' => 'encrypted'];
    }

    public function items()
    {
        return $this->hasMany(ShopOrderItem::class);
    }

    public function events()
    {
        return $this->hasMany(ShopOrderEvent::class)->orderBy('id');
    }
}
