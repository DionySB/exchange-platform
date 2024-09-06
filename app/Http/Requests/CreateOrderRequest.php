<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateOrderRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'symbol' => 'required|string',
            'tradeSide' => 'required|integer|in:1,2',
            'tradeType' => 'required|integer|in:1,2,3',
            'clientOrderId' => 'nullable|numeric',
            'qty' => 'nullable|numeric',
            'price' => 'nullable|numeric',
        ];
    }

    public function failedValidation($validator)
    {
        $errors = $validator->errors()->toArray();
        throw new \Illuminate\Validation\ValidationException($validator, response()->json($errors, 422));
    }
}

