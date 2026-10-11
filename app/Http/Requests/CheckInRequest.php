<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

class CheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['child_id' => ['bail', 'required', 'integer', 'min:1', function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_int($value) && ! is_string($value)) {
                $fail('The child id must be an integer.');
            }
        }]];
    }
}
