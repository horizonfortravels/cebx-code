<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class CustomsDeclaration extends Model {
    use HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $guarded = [];
    public function shipment(): BelongsTo { return $this->belongsTo(Shipment::class); }
    public function broker(): BelongsTo { return $this->belongsTo(CustomsBroker::class, 'broker_id'); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class, 'branch_id'); }
    public function inspector(): BelongsTo { return $this->belongsTo(User::class, 'inspector_user_id'); }
    public function documents(): HasMany { return $this->hasMany(CustomsDocument::class, 'declaration_id'); }
    public function items(): HasMany { return $this->hasMany(ShipmentItem::class, 'declaration_id'); }

    public function canTransitionTo(string $status): bool
    {
        $transitions = [
            'draft' => ['documents_pending', 'submitted', 'cancelled'],
            'documents_pending' => ['submitted', 'cancelled'],
            'submitted' => ['under_review', 'inspection_required', 'rejected'],
            'under_review' => ['inspection_required', 'duty_assessment', 'rejected'],
            'inspection_required' => ['inspecting', 'rejected'],
            'inspecting' => ['duty_assessment', 'held', 'rejected'],
            'duty_assessment' => ['payment_pending', 'held', 'rejected'],
            'payment_pending' => ['duty_paid', 'held'],
            'duty_paid' => ['cleared'],
            'held' => ['under_review', 'inspection_required', 'rejected'],
            'rejected' => [],
            'cancelled' => [],
            'cleared' => [],
        ];

        $current = (string) ($this->customs_status ?? $this->status ?? '');

        if ($current === '' || $current === $status) {
            return false;
        }

        return in_array($status, $transitions[$current] ?? [], true);
    }
}
