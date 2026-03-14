<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class RetentionPolicy extends Model
{
    use HasUuids;
    protected $guarded = ['id'];
    protected $casts = ['retention_days' => 'integer', 'is_active' => 'boolean'];
}
