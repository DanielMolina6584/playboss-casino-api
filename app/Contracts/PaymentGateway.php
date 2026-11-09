<?php

namespace App\Contracts;

interface PaymentGateway
{
    /** @return array{reference:string,status:string,checkout_url?:string|null} */
    public function crearIntento(array $payload, string $claveIdempotencia): array;

    /** @return array{verified:bool,event_id:string,reference:string,status:string,amount:string,currency:string} */
    public function verificarEvento(array $event): array;
}
