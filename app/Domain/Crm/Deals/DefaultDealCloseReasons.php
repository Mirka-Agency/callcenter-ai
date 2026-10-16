<?php

namespace App\Domain\Crm\Deals;

final class DefaultDealCloseReasons
{
    /**
     * @return list<array{kind: string, code: string, label: string, position: int, requires_note: bool}>
     */
    public static function catalog(): array
    {
        return [
            ['kind' => DealCloseReasonKind::Lost->value, 'code' => 'price', 'label' => 'قیمت', 'position' => 1, 'requires_note' => false],
            ['kind' => DealCloseReasonKind::Lost->value, 'code' => 'no_budget', 'label' => 'نبود بودجه', 'position' => 2, 'requires_note' => false],
            ['kind' => DealCloseReasonKind::Lost->value, 'code' => 'competitor', 'label' => 'انتخاب رقیب', 'position' => 3, 'requires_note' => false],
            ['kind' => DealCloseReasonKind::Lost->value, 'code' => 'no_need', 'label' => 'عدم نیاز', 'position' => 4, 'requires_note' => false],
            ['kind' => DealCloseReasonKind::Lost->value, 'code' => 'bad_timing', 'label' => 'زمان نامناسب', 'position' => 5, 'requires_note' => false],
            ['kind' => DealCloseReasonKind::Lost->value, 'code' => 'product', 'label' => 'مشکل محصول', 'position' => 6, 'requires_note' => false],
            ['kind' => DealCloseReasonKind::Lost->value, 'code' => 'no_response', 'label' => 'عدم پاسخ‌گویی مشتری', 'position' => 7, 'requires_note' => false],
            ['kind' => DealCloseReasonKind::Lost->value, 'code' => 'other', 'label' => 'سایر موارد', 'position' => 8, 'requires_note' => true],
            ['kind' => DealCloseReasonKind::Won->value, 'code' => 'value_fit', 'label' => 'تناسب ارزش و نیاز', 'position' => 1, 'requires_note' => false],
            ['kind' => DealCloseReasonKind::Won->value, 'code' => 'relationship', 'label' => 'اعتماد و ارتباط', 'position' => 2, 'requires_note' => false],
            ['kind' => DealCloseReasonKind::Won->value, 'code' => 'timing', 'label' => 'زمان مناسب خرید', 'position' => 3, 'requires_note' => false],
            ['kind' => DealCloseReasonKind::Won->value, 'code' => 'other', 'label' => 'سایر موارد', 'position' => 4, 'requires_note' => true],
        ];
    }
}
