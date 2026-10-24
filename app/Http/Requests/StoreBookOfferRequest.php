<?php

namespace App\Http\Requests;

use App\Support\Romantausch\InputRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreBookOfferRequest extends FormRequest
{
    public const ALLOWED_TYPES = InputRules::TYPES;

    public static function getAllowedTypes(): array
    {
        return self::ALLOWED_TYPES;
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return InputRules::single(true);
    }

    public function messages(): array
    {
        return InputRules::messages();
    }
}
