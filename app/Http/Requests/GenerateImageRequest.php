<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $validModels = array_keys(config('ai_models', []));

        return [
            'model' => ['required', 'string', Rule::in($validModels)],
            'prompt' => ['required', 'string', 'max:1000'],
            'negative_prompt' => ['nullable', 'string', 'max:500'],
            'width' => ['nullable', 'integer', Rule::in([512, 768, 1024, 1536, 1792])],
            'height' => ['nullable', 'integer', Rule::in([512, 768, 1024, 1536, 1792])],
            'aspect_ratio' => ['nullable', 'string', 'max:10'],
            'style_preset_id' => ['nullable', 'integer', 'exists:style_presets,id'],
            'num_images' => ['nullable', 'integer', 'min:1', 'max:4'],
        ];
    }
}
