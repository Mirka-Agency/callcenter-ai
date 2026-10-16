<?php

namespace App\Application\Crm\Invoices;

class InvoiceTotals
{
    /**
     * @param  list<array{amount_rials: int}>  $lines
     * @return array{subtotal_rials: int, tax_rials: int, total_rials: int}
     */
    public static function compute(array $lines, float $taxPercent): array
    {
        $subtotal = 0;
        foreach ($lines as $line) {
            $subtotal += (int) ($line['amount_rials'] ?? 0);
        }

        $taxPercent = max(0, min(100, $taxPercent));
        $tax = (int) round($subtotal * ($taxPercent / 100));

        return [
            'subtotal_rials' => $subtotal,
            'tax_rials' => $tax,
            'total_rials' => $subtotal + $tax,
        ];
    }

    public static function lineAmount(float $quantity, int $unitPriceRials): int
    {
        if ($quantity < 0) {
            $quantity = 0;
        }

        return (int) round($quantity * $unitPriceRials);
    }
}
