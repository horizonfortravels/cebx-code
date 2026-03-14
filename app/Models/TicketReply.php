<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class TicketReply extends Model {
    use HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $guarded = [];
    protected $casts = ['is_agent' => 'boolean'];
    public function ticket(): BelongsTo { return $this->belongsTo(SupportTicket::class, 'support_ticket_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
