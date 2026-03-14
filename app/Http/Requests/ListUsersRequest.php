<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status'   => ['sometimes', 'in:active,inactive,suspended'],
            'search'   => ['sometimes', 'string', 'max:100'],
            'sort_by'  => ['sometimes', 'in:name,email,created_at,status'],
            'sort_dir' => ['sometimes', 'in:asc,desc'],
            'per_page' => ['sometimes', 'integer', 'min:5', 'max:50'],
        ];
    }
}
