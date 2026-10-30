<?php

namespace App\Contracts;

/** Pasarelas que permiten consultar el estado real de un pago por referencia. */
interface ConsultableGateway
{
    /** @return array{verified:bool,event_id:string,reference:string,status:string,amount:string,currency:string}|null */
    public function consultarPorReferencia(string $reference): ?array;
}
