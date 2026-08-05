<?php

namespace App\Domain\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VoidDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('void', $this->route('document'));
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
