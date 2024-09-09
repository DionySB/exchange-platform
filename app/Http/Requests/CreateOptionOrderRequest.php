<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateOptionOrderRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'symbol' => 'nullable|string',
            'tradeSide' => 'required|integer|in:1,2',
            'tradeType' => 'required|integer|in:1,3',
            'clientOrderId' => 'nullable|numeric',
            'qty' => 'required|numeric',
            'price' => 'nullable|numeric',
            'stp' => 'nullable|in:1,2,3',
        ];
    }

    public function failedValidation($validator)
    {
        $errors = $validator->errors()->toArray();
        throw new \Illuminate\Validation\ValidationException($validator, response()->json($errors, 422));
    }

    public function messages()
    {
        return [
            'tradeSide.in' => 'The tradeSide field must have one of the following values: 1 or 2.',
            'tradeType.in' => 'The tradeType field must have one of the following values: 1 or 3.',
            'stp.in' => 'The stp field must have one of the following values: 1 (CM - Cancel Maker), 2 (CT - Cancel Taker), or 3 (CB - Cancel Both).',
        ];
    }
}
