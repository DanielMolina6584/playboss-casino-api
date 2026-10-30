<?php

namespace App\Http\Requests\Concerns;

/**
 * Cuerpo (`idempotency_key`) > header `Idempotency-Key` > clave determinista
 * (usuario + payload normalizado + ventana de 30 s) que amortigua dobles envíos.
 */
trait ResuelveClaveIdempotencia
{
    protected function claveIdempotencia(array $payload): string
    {
        $explicita = $this->input('idempotency_key') ?: $this->header('Idempotency-Key');
        if ($explicita) {
            return (string) $explicita;
        }

        $normalizar = function (mixed $v) use (&$normalizar) {
            if (is_array($v)) {
                array_is_list($v) || ksort($v);

                return array_map($normalizar, $v);
            }

            return is_scalar($v) ? (string) $v : $v;
        };
        $ventana = intdiv(time(), 30);
        $huella = hash('sha256', json_encode([(string) $this->user()?->getAuthIdentifier(), $this->path(), $normalizar($payload), $ventana]));

        return 'auto-' . substr($huella, 0, 40);
    }
}
