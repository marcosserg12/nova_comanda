<?php

namespace App\Actions\Orders;

final class OrderInput
{
    /** @return array<string, list<string>> */
    public static function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:60'],
            'takeaway' => ['boolean'],
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.item_id' => ['nullable', 'integer'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'customer_name.required' => 'Informe o nome do cliente.',
            'customer_name.max' => 'O nome pode ter no máximo 60 letras.',
            'items.required' => 'Adicione pelo menos um item.',
            'items.min' => 'Adicione pelo menos um item.',
            'items.*.quantity.max' => 'Quantidade máxima por item: 20.',
        ];
    }
}
