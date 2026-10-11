<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CheckIn extends Model
{
    use HasFactory;

    protected $fillable = ['tenant_id', 'room_id', 'child_id', 'checked_in_at', 'checked_out_at'];

    protected function casts(): array
    {
        return ['checked_in_at' => 'immutable_datetime', 'checked_out_at' => 'immutable_datetime'];
    }
}
