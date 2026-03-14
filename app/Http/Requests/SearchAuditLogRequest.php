<?php

namespace App\Http\Requests;

use App\Models\AuditLog;
use Illuminate\Foundation\Http\FormRequest;

class SearchAuditLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth handled by controller
    }

    public function rules(): array
    {
        $categories = implode(',', AuditLog::categories());
        $severities = implode(',', AuditLog::severities());

        return [
            'category'    => ["nullable", "string", "in:{$categories}"],
            'severity'    => ["nullable", "string", "in:{$severities}"],
            'actor_id'    => ['nullable', 'uuid'],
            'action'      => ['nullable', 'string', 'max:100'],
            'entity_type' => ['nullable', 'string', 'max:100'],
            'entity_id'   => ['nullable', 'uuid'],
            'from'        => ['nullable', 'date'],
            'to'          => ['nullable', 'date', 'after_or_equal:from'],
            'ip_address'  => ['nullable', 'ip'],
            'request_id'  => ['nullable', 'string', 'max:64'],
            'search'      => ['nullable', 'string', 'max:200'],
            'sort_by'     => ['nullable', 'string', 'in:created_at,action,severity,category'],
            'sort_dir'    => ['nullable', 'string', 'in:asc,desc'],
            'per_page'    => ['nullable', 'integer', 'min:5', 'max:100'],
            'format'      => ['nullable', 'string', 'in:csv,json'],
        ];
    }
}
