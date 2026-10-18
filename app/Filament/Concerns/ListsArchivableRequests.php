<?php

namespace App\Filament\Concerns;

use Filament\Actions\Action;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Livewire\Attributes\Url;

trait ListsArchivableRequests
{
    #[Url]
    public bool $archived = false;

    public function getTitle(): string|Htmlable
    {
        if ($this->archived) {
            return __('filament.pages.archived_requests', [
                'label' => static::getResource()::getPluralModelLabel(),
            ]);
        }

        return parent::getTitle();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('archives')
                ->label(fn (): string => $this->archived
                    ? __('filament.actions.active_requests')
                    : __('filament.actions.archives'))
                ->icon(fn (): string => $this->archived ? 'heroicon-o-inbox' : 'heroicon-o-archive-box')
                ->badge(function (): ?string {
                    if ($this->archived) {
                        return null;
                    }

                    $count = static::getResource()::getModel()::query()->archived()->count();

                    return $count > 0 ? (string) $count : null;
                })
                ->action(function (): void {
                    $this->archived = ! $this->archived;
                    $this->resetTable();
                }),
        ];
    }

    protected function getTableQuery(): Builder|Relation|null
    {
        $query = parent::getTableQuery();

        if (! $query instanceof Builder) {
            return $query;
        }

        return $this->archived ? $query->archived() : $query->active();
    }
}
