<?php

return [
    'accepted' => 'O campo :attribute deve ser aceito.',
    'array' => 'O campo :attribute deve ser uma lista.',
    'between' => [
        'array' => 'O campo :attribute deve ter entre :min e :max itens.',
        'file' => 'O campo :attribute deve ter entre :min e :max kilobytes.',
        'numeric' => 'O campo :attribute deve estar entre :min e :max.',
        'string' => 'O campo :attribute deve ter entre :min e :max caracteres.',
    ],
    'boolean' => 'O campo :attribute deve ser verdadeiro ou falso.',
    'confirmed' => 'A confirmação do campo :attribute não confere.',
    'date' => 'O campo :attribute não é uma data válida.',
    'email' => 'O campo :attribute deve ser um e-mail válido.',
    'exists' => 'O valor escolhido para :attribute não é válido.',
    'in' => 'O valor escolhido para :attribute não é válido.',
    'integer' => 'O campo :attribute deve ser um número inteiro.',
    'max' => [
        'array' => 'O campo :attribute deve ter no máximo :max itens.',
        'file' => 'O campo :attribute deve ter no máximo :max kilobytes.',
        'numeric' => 'O campo :attribute não pode ser maior que :max.',
        'string' => 'O campo :attribute deve ter no máximo :max caracteres.',
    ],
    'min' => [
        'array' => 'O campo :attribute deve ter pelo menos :min itens.',
        'file' => 'O campo :attribute deve ter pelo menos :min kilobytes.',
        'numeric' => 'O campo :attribute deve ser pelo menos :min.',
        'string' => 'O campo :attribute deve ter pelo menos :min caracteres.',
    ],
    'numeric' => 'O campo :attribute deve ser um número.',
    'regex' => 'O formato do campo :attribute é inválido.',
    'required' => 'O campo :attribute é obrigatório.',
    'string' => 'O campo :attribute deve ser um texto.',
    'unique' => 'Já existe um registro com este :attribute.',

    'custom' => [],

    'attributes' => [
        'name' => 'nome',
        'username' => 'usuário',
        'password' => 'senha',
        'price' => 'preço',
        'readyMinutes' => 'minutos de pronto',
        'restaurantName' => 'nome do restaurante',
        'stationLeftWidth' => 'largura do monitor do balcão',
        'stationRightWidth' => 'largura do monitor da cozinha',
        'customerName' => 'nome do cliente',
        'cancelReason' => 'motivo',
    ],
];
