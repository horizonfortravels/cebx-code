<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListInvitationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status'   => ['nullable', 'string', 'in:pending,accepted,expired,cancelled'],
            'search'   => ['nullable', 'string', 'max:100'],
            'sort_by'  => ['nullable', 'string', 'in:created_at,email,status,expires_at'],
            'sort_dir' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
        ];
    }
}
