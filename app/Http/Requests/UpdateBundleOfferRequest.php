<?php

namespace App\Http\Requests;

use App\Support\Romantausch\InputRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBundleOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return InputRules::bundle(true);
    }

    public function messages(): array
    {
        return InputRules::messages();
    }
}
