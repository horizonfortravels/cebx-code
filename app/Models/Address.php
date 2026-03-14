<?php
namespace App\Models;
use App\Models\Traits\BelongsToAccount;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class Address extends Model {
    use HasFactory, HasUuids, BelongsToAccount;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $guarded = [];
}
