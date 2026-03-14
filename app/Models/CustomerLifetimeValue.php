<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class CustomerLifetimeValue extends Model
{
    use HasUuids, BelongsToAccount;
    protected $guarded = ['id'];
    protected $casts = ['total_revenue' => 'decimal:2', 'total_shipments' => 'integer', 'avg_order_value' => 'decimal:2', 'first_order_date' => 'date', 'last_order_date' => 'date', 'predicted_ltv' => 'decimal:2'];
}
