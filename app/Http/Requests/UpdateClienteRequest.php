<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClienteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $clienteId = $this->route('cliente');

        return [
            'nome' => ['sometimes', 'string', 'max:255'],
            'cpf' => ['sometimes', 'digits:11', Rule::unique('clientes', 'cpf')->ignore($clienteId)],
            'email' => ['sometimes', 'string', 'email', Rule::unique('clientes', 'email')->ignore($clienteId)],
            'telefone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'renda_mensal' => ['sometimes', 'numeric', 'min:0'],
        ];
    }
}
