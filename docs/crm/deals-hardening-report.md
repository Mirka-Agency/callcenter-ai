# گزارش نهایی Hardening ماژول Deals & Pipelines

## ۱. وضعیت اولیه و مشکلات

ماژول از قبل وجود داشت: پایپ‌لاین، معامله، پیشنهاد تحلیل، تاریخچه، و جداسازی نسبی CRM Mode در HTTP.  
شکاف‌ها: تطبیق موضوعی ضعیف، Aging فقط ۱۴ روز ثابت، بدون Health/Barriers سطح معامله، قفل هم‌زمانی ناقص، دلایل Won/Lost فقط متن آزاد، گارد Mode در Action/CrmManager ناقص، و قرارداد Tasks فقط در مستند معماری.

جزئیات در `docs/crm/deals-hardening-audit.md`.

## ۲. ده قابلیت

| # | قابلیت | نتیجه |
|---|--------|--------|
| ۱ | تشخیص معامله مرتبط / جلوگیری از تکرار | تکمیل‌شده (رتبه‌بندی کلیدواژه + unique قبلی) |
| ۲ | Aging / Stale | تکمیل‌شده (آستانه مرحله + inactivity + UI) |
| ۳ | تاریخچه | تقویت‌شده (ValueChanged + نمایش previous/next) |
| ۴ | Deal Health Score | تکمیل‌شده (rule-based + Insufficient Data) |
| ۵ | موانع فروش | تکمیل‌شده از `concerns_json` موجود |
| ۶ | هم‌زمانی | تکمیل‌شده با `version` |
| ۷ | دلایل Won/Lost | تکمیل‌شده با کاتالوگ سازمان |
| ۸ | استقلال دو حالت CRM | تقویت‌شده (auth + CrmManager + nav fail-closed) |
| ۹ | امنیت مجوزها | تقویت‌شده (Mode در DealAuthorization) |
| ۱۰ | آمادگی Tasks | قرارداد + next_action بدون ماژول موازی |

## ۳. تغییرات معماری

- Actionهای جدید: `EnsureDealCloseReasons`, `SyncDealBarriersFromAnalysis`, `ResolveDealBarrier`
- سرویس‌ها: `DealRelatedMatcher`, `DealAging`, `DealHealthScorer`, `DealConcurrency`
- Domain: `DealHealthLevel`, `DealBarrierStatus`, `DealCloseReasonKind`, `DefaultDealCloseReasons`, `DealActivityType::ValueChanged`
- بدون Hybrid و بدون sync دوطرفه

## ۴. Migration

`database/migrations/2026_10_14_140000_harden_crm_deal_operations.php` (افزایشی):

- `crm_pipeline_stages.stale_after_days`
- فیلدهای deal: `version`, `stage_entered_at`, `won_reason`, `lost_reason_code`, `health_*`, `next_action_*`
- جداول `crm_deal_close_reasons`, `crm_deal_barriers`
- backfill `stage_entered_at`

## ۵. Models / Actions / Events

- Models: `CrmDeal`, `CrmPipelineStage`, `CrmDealCloseReason`, `CrmDealBarrier`, `Organization` (fail-closed mode)
- Actions به‌روز: Create/Update/Stage/Owner/CloseWon/CloseLost/Reopen/LinkCall/Suggest/UpdatePipelineStage
- Events قبلی حفظ شدند؛ انتشار همچنان بعد از commit

## ۶. API

سطح عمومی همچنان Livewire است (REST جدید اضافه نشد). قرارداد ورودی‌ها با `expected_version` و کد دلیل بسته‌شدن گسترش یافت.

## ۷. Frontend

- جزئیات معامله: سلامت، توقف، موانع، اقدام بعدی، دلایل بسته‌شدن، diff تاریخچه
- کانبان: نشان متوقف
- فهرست: فیلتر `stale`
- نمای کلی: شمارش stale با آستانه مرحله
- پایپ‌لاین: تنظیم `stale_after_days`

## ۸. Sidebar

آیتم منوی اصلی جدید ساخته نشد. در حالت Mode نامعتبر فقط اشخاص دیده می‌شود؛ داخلی و خارجی مثل قبل جدا هستند.

## ۹. اجرای محدودیت دو حالت CRM

- منبع حقیقت: `organizations.crm_mode` از Filament Super Admin
- HTTP: middleware `organization-crm`
- Action: `DealAuthorization` + `assertInternalCrm`
- خارجی: `CrmManager::execute` فقط وقتی `usesExternalCrm()`
- Jobهای پیشنهاد/همگام‌سازی قبل از side effect Mode را دوباره می‌خوانند
- Mode نامعتبر: fail-closed (نه انتخاب خودکار عملیاتی)

## ۱۰–۱۲. تست‌ها

اجرا شده:

- `CrmDealsHardeningTest` (۷)
- `CrmDealsAndPipelinesTest` (۹)
- `OrganizationCrmModeTest`

نتیجه آخرین اجرا: **۲۰ passed / ۱۳۲ assertions**.

ناموفق در این دور: ندارد.  
اجرانشده در این دور: کل suite پروژه (عمداً محدود به CRM).

## ۱۳. ریسک‌های باقی‌مانده

- رتبه‌بندی معامله مرتبط مبتنی بر توکن است، نه embedding معنایی
- فیلتر stale در SQL کاندید است و آستانه مرحله را در PHP نهایی می‌کند (برای صفحه‌های خیلی بزرگ ممکن است نیاز به بهینه‌سازی داشته باشد)
- `crm_mode` هنوز برای Filament fillable است؛ UI کارفرما فیلد ندارد ولی تست منفی mass-assignment عمیق روی Livewire جداگانه گسترش‌پذیر است
- مؤلفه Tasks در Health هنوز غیرفعال است (عمدی)

## ۱۴. آمادگی Tasks & Activities

مستند: `docs/crm/deals/02-tasks-integration-contract.md`  
فیلدهای `next_action_*` آماده نمایش‌اند تا ماژول سوم بدون کار موازی وصل شود.
