<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Paginación y filtros comunes de catálogos y listados. */
class ListadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('estado'))) {
            $this->merge(['estado' => strtoupper($this->input('estado'))]);
        }
    }

    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'q' => ['sometimes', 'string', 'max:80'],
            'liga_id' => ['sometimes', 'integer', 'min:1'],
            'temporada_id' => ['sometimes', 'integer', 'min:1'],
            'jornada_id' => ['sometimes', 'integer', 'min:1'],
            'equipo_id' => ['sometimes', 'integer', 'min:1'],
            'ciudad_id' => ['sometimes', 'integer', 'min:1'],
            'servicio_codigo' => ['sometimes', 'string', 'max:10'],
            'pais_codigo' => ['sometimes', 'string', 'size:2'],
            'estado' => ['sometimes', 'string', 'in:PROG,ENJ,FIN,SUSP,PEND,GAN,PER,ANU,CASH,PENDIENTE,PAGADO,RECHAZADO,ACT,BLQ,SUS'],
            'usuario_id' => ['sometimes', 'integer', 'min:1'],
            'rol' => ['sometimes', 'string', 'in:CLIENTE,ADMIN'],
            'desde' => ['sometimes', 'date'],
            'hasta' => ['sometimes', 'date', 'after_or_equal:desde'],
            'orden' => ['sometimes', 'in:asc,desc'],
            'tipo' => ['sometimes', 'string', 'max:30'],
        ];
    }

    public function porPagina(): int
    {
        return (int) $this->integer('per_page', 20);
    }
}
