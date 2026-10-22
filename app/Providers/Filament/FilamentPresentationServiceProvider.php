<?php

namespace App\Providers\Filament;

use App\Support\PersianNumber;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Support\ServiceProvider;

class FilamentPresentationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Table::configureUsing(function (Table $table): void {
            $table
                ->defaultNumberLocale('fa')
                ->defaultCurrency('IRR');
        });

        Schema::configureUsing(function (Schema $schema): void {
            $schema
                ->defaultNumberLocale('fa')
                ->defaultCurrency('IRR');
        });

        DatePicker::configureUsing(function (DatePicker $picker): void {
            $picker->jalali()->firstDayOfWeek(6);
        });

        DateTimePicker::configureUsing(function (DateTimePicker $picker): void {
            $picker->jalali()->firstDayOfWeek(6);
        });

        TextInput::macro('persianNumeric', function (
            ?int $decimalPlaces = null,
            ?int $maxDecimalPlaces = null,
        ): TextInput {
            /** @var TextInput $this */
            // Use text inputs — Filament's numeric() forces type=number which cannot display Persian digits.
            $this->type('text');
            $this->inputMode('decimal');

            $this->formatStateUsing(function ($state) use ($decimalPlaces, $maxDecimalPlaces) {
                if (blank($state)) {
                    return null;
                }

                if (is_string($state) && ! is_numeric($state)) {
                    $parsed = PersianNumber::parse($state);

                    if (is_numeric($parsed)) {
                        $state = $parsed;
                    } else {
                        return $state;
                    }
                }

                if (! is_numeric($state)) {
                    return $state;
                }

                return PersianNumber::format($state, $decimalPlaces, $maxDecimalPlaces);
            });

            $this->dehydrateStateUsing(fn ($state) => PersianNumber::parse($state));

            $this->rule(static function (TextInput $component): \Closure {
                return function (string $attribute, mixed $value, \Closure $fail) use ($component): void {
                    if (blank($value)) {
                        return;
                    }

                    $parsed = PersianNumber::parse($value);

                    if (! is_numeric($parsed)) {
                        $fail(__('validation.numeric'));

                        return;
                    }

                    $numeric = (float) $parsed;

                    if (filled($min = $component->getMinValue()) && $numeric < (float) $min) {
                        $fail(__('validation.min.numeric', ['min' => $min]));
                    }

                    if (filled($max = $component->getMaxValue()) && $numeric > (float) $max) {
                        $fail(__('validation.max.numeric', ['max' => $max]));
                    }
                };
            });

            return $this;
        });
    }
}
