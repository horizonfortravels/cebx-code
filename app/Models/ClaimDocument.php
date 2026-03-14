<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ClaimDocument extends Model
{
    use HasUuids;
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['claim_id', 'document_type', 'title', 'file_path', 'file_type', 'file_size', 'uploaded_by', 'notes'];

    public function claim() { return $this->belongsTo(Claim::class); }
    public function uploader() { return $this->belongsTo(User::class, 'uploaded_by'); }
}
