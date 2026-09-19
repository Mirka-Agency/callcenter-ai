<?php

namespace App\Support;

use App\Models\ConversationAnalysis;
use App\Services\Performance\Calculators\SentimentScoreCalculator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

enum CustomerListSort: string
{
    case LastContact = 'last_contact';
    case Satisfaction = 'satisfaction';
    case Dissatisfaction = 'dissatisfaction';
    case Newest = 'newest';
    case Oldest = 'oldest';

    public function label(): string
    {
        return match ($this) {
            self::LastContact => 'آخرین تماس',
            self::Satisfaction => 'راضی‌ترین مشتری',
            self::Dissatisfaction => 'ناراضی‌ترین مشتری',
            self::Newest => 'جدیدترین مشتری',
            self::Oldest => 'قدیمی‌ترین مشتری',
        };
    }

    public static function fromInput(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::LastContact;
    }

    /**
     * @param  Builder<Model>  $query
     * @param  'company'|'contact'  $entity
     * @return Builder<Model>
     */
    public static function apply(Builder $query, string $sort, string $entity, int $organizationId): Builder
    {
        $resolved = self::fromInput($sort);
        $entity = $entity === 'company' ? 'company' : 'contact';
        $table = $query->getModel()->getTable();

        return match ($resolved) {
            self::Newest => $query->orderByDesc($table.'.created_at')->orderBy($table.'.id'),
            self::Oldest => $query->orderBy($table.'.created_at')->orderBy($table.'.id'),
            self::Satisfaction => self::applySatisfaction($query, $entity, $organizationId, descending: true),
            self::Dissatisfaction => self::applySatisfaction($query, $entity, $organizationId, descending: false),
            self::LastContact => self::applyLastContact($query, $entity),
        };
    }

    /**
     * @param  Builder<Model>  $query
     * @param  'company'|'contact'  $entity
     * @return Builder<Model>
     */
    private static function applyLastContact(Builder $query, string $entity): Builder
    {
        $table = $query->getModel()->getTable();

        $query->orderByDesc($table.'.last_contact_at');

        if ($entity === 'company') {
            $query->orderBy($table.'.name');
        }

        return $query;
    }

    /**
     * @param  Builder<Model>  $query
     * @param  'company'|'contact'  $entity
     * @return Builder<Model>
     */
    private static function applySatisfaction(Builder $query, string $entity, int $organizationId, bool $descending): Builder
    {
        $table = $query->getModel()->getTable();
        $weightSql = SentimentScoreCalculator::weightExpression('conversation_analyses.sentiment');

        $subquery = ConversationAnalysis::query()
            ->selectRaw($entity === 'company'
                ? 'customers.customer_company_id as subject_id'
                : 'calls.customer_id as subject_id')
            ->selectRaw('AVG('.$weightSql.') as satisfaction_score')
            ->join('calls', function ($join) {
                $join->on('calls.id', '=', 'conversation_analyses.call_id')
                    ->whereColumn('calls.organization_id', 'conversation_analyses.organization_id');
            })
            ->when($entity === 'company', function (Builder $inner) {
                $inner->join('customers', function ($join) {
                    $join->on('customers.id', '=', 'calls.customer_id')
                        ->whereColumn('customers.organization_id', 'calls.organization_id')
                        ->whereNotNull('customers.customer_company_id');
                });
            })
            ->where('conversation_analyses.organization_id', $organizationId)
            ->whereNotNull('conversation_analyses.sentiment')
            ->when(
                $entity === 'company',
                fn (Builder $inner) => $inner->groupBy('customers.customer_company_id'),
                fn (Builder $inner) => $inner->groupBy('calls.customer_id'),
            );

        $query
            ->select($table.'.*')
            ->leftJoinSub($subquery, 'customer_list_satisfaction', 'customer_list_satisfaction.subject_id', '=', $table.'.id')
            ->orderByRaw('customer_list_satisfaction.satisfaction_score IS NULL');

        if ($descending) {
            $query->orderByDesc('customer_list_satisfaction.satisfaction_score');
        } else {
            $query->orderBy('customer_list_satisfaction.satisfaction_score');
        }

        return $query
            ->orderByDesc($table.'.last_contact_at')
            ->orderBy($table.'.id');
    }
}
