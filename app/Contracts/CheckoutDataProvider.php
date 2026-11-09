<?php

namespace App\Contracts;

/** Pasarelas que entregan al front los datos para abrir su widget/checkout. */
interface CheckoutDataProvider
{
    /** @return array<string,mixed> */
    public function datosCheckout(string $reference, int $amountInCents, string $currency): array;
}
