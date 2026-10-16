# Deals & Pipelines — Hardening Audit

تاریخ بررسی: ۲۰۲۶-۱۰-۱۴  
منبع حقیقت: کد فعلی مخزن، نه طراحی قبلی.

## وضعیت جداسازی دو حالت CRM

| لایه | وضعیت |
|------|--------|
| `OrganizationCrmMode` + ستون `organizations.crm_mode` | وجود دارد؛ پیش‌فرض `internal` |
| Filament Super Admin | انتخاب حالت فقط از فرم سازمان |
| Middleware `organization-crm` | مسیرهای داخلی/خارجی را جدا می‌کند |
| Navigation | بر اساس حالت فیلتر می‌شود |
| `SuggestDealFromAnalysis` | فقط `usesInternalCrm()` |
| `CrmIntelligenceSyncService` / `SyncCrmDataJob` | فقط `usesExternalCrm()` |
| `DealAuthorization` / اکشن‌های معامله | **حالت CRM را دوباره چک نمی‌کنند** |
| `CrmManager` | **حالت CRM را چک نمی‌کند** |
| `crm_mode = null` | امروز به Internal می‌افتد (**fail-open**) |

**ریسک:** اگر middleware دور زده شود یا Job قدیمی بماند، عملیات نامناسب ممکن است. مدیر سازمان UI تغییر Mode ندارد، ولی `crm_mode` هنوز fillable است.

## وضعیت ۱۰ قابلیت

| # | قابلیت | وضعیت | خلاصه |
|---|--------|--------|--------|
| ۱ | تشخیص معامله مرتبط / جلوگیری از تکرار | Partial | تطبیق فقط با شخص + unique روی call/analysis؛ بدون رتبه‌بندی موضوعی |
| ۲ | Aging / Stale | Partial | فقط ۱۴ روز ثابت روی `last_activity_at`؛ بدون آستانه مرحله |
| ۳ | تاریخچه قابل اتکا | Exists | `crm_deal_activities` + `crm_deal_stage_history`؛ UI بدون diff فیلد |
| ۴ | Deal Health Score | Missing | — |
| ۵ | موانع فروش | Partial | `concerns_json` در تحلیل هست؛ روی معامله سطح اول نیست |
| ۶ | هم‌زمانی / Optimistic lock | Partial | `expected_updated_at` در Update؛ بدون `version`؛ Close/Reopen بدون توکن |
| ۷ | دلایل Won/Lost | Partial | `lost_reason` متنی؛ بدون کاتالوگ و بدون `won_reason` |
| ۸ | استقلال دو حالت CRM | Partial | HTTP خوب؛ لایه Action/Manager ناقص |
| ۹ | امنیت و مجوزها | Partial | Tenant + مالکیت هست؛ Mode در auth نیست |
| ۱۰ | آمادگی Tasks | Partial | فقط مستند و TaskData خارجی |

## جداول / Migration لازم (افزایشی)

- `crm_pipeline_stages.stale_after_days`
- `crm_deals.version`, `stage_entered_at`, `won_reason`, `lost_reason_code`
- `crm_deals.health_*`, `next_action_*`
- `crm_deal_close_reasons` (کاتالوگ سازمان)
- `crm_deal_barriers` (مانع ↔ معامله ↔ تحلیل/تماس)

بدون حذف داده و بدون Hybrid sync.

## Refactorهای هدفمند (نه بازنویسی)

1. گارد Mode در `DealAuthorization` و `CrmManager`
2. تطبیق موضوعی محدود در پیشنهاد تحلیل
3. سرویس Aging و Health Rule-based
4. تکمیل Close reasons + concurrency با `version`
5. استخراج موانع از `concerns_json` موجود
6. قرارداد Tasks بدون ماژول موازی

## ریسک داده موجود

- معامله‌های دمو و واقعی با `last_activity_at` می‌مانند؛ `stage_entered_at` از تاریخچه یا `created_at` پر می‌شود.
- تغییر Mode داده را پاک نمی‌کند.
- آستانه stale پیش‌فرض برای مرحله‌ها خالی می‌ماند تا مدیر خودش تنظیم کند (بدون هشدار حدسی گسترده).
