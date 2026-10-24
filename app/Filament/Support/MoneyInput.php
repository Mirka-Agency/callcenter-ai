<?php

namespace App\Filament\Support;

use App\Support\Money;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;

/**
 * Inputs and columns for amounts stored in rials and shown in the display unit (rial/toman).
 */
class MoneyInput
{
    public static function make(string $name): TextInput
    {
        return TextInput::make($name)
            ->numeric()
            ->minValue(0)
            ->suffix(fn (): string => Money::unitLabel())
            ->formatStateUsing(fn (mixed $state): int|float|null => $state === null ? null : Money::fromRials((int) $state))
            ->dehydrateStateUsing(fn (mixed $state): int => Money::toRials($state));
    }

    public static function column(string $name): TextColumn
    {
        return TextColumn::make($name)
            ->formatStateUsing(fn (mixed $state): string => Money::format($state === null ? null : (int) $state))
            ->alignEnd();
    }
}
