<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ConsultationMessage extends Model
{
    protected $fillable = ['teleconsultation_id', 'sender_id', 'client_id', 'body'];
    protected function casts(): array { return ['body' => 'encrypted']; }
}
