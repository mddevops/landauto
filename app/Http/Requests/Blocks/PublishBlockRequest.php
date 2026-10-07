<?php

namespace App\Http\Requests\Blocks;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Authorization is the Block lookup (own Block only) plus BlockPublisher; this validates the input.
 */
class PublishBlockRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['revision' => ['required', 'integer', 'min:0']];
    }
}
