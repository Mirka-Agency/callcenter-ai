# Tasks & Activities — Audit

تاریخ: ۲۰۲۶-۱۰-۱۴  
منبع حقیقت: کد واقعی مخزن.

## آنچه از قبل وجود دارد

| حوزه | وضعیت | مسیر کلیدی |
|------|--------|------------|
| قرارداد اتصال به Deal | وجود دارد | `docs/crm/deals/02-tasks-integration-contract.md` |
| `next_action_*` روی Deal | وجود دارد | فیلد سبک تا آمدن Tasks |
| Health عامل tasks_module | وزن ۰ | `DealHealthScorer` |
| `next_actions_json` تحلیل | وجود دارد | `ConversationAnalysis` |
| پیشنهاد معامله از تحلیل | وجود دارد | `crm_deal_ai_suggestions` |
| تاریخچه معامله | وجود دارد | `crm_deal_activities` |
| Task خارجی Didar/Dynamics | وجود دارد | `TaskData` + `CrmManager::createTask` |
| موعد خارجی بر اساس Lead Score | وجود دارد | `AbstractCrmAdapter` (۴س/۱ر/۳ر) |
| CRM Mode + middleware | وجود دارد | `OrganizationCrmMode`, `organization-crm` |
| Notification/Reminder داخلی | نیست | — |
| مدل/جدول `crm_tasks` | نیست | — |

## قابل استفاده مجدد

- الگوی Action + `DealAuthorization` → `TaskAuthorization`
- Livewire زیر `Livewire\Crm\` + `ResolvesCrmContext`
- صف `followup` برای پیشنهاد و یادآوری
- Navigation فقط در حالت Internal
- موعد پیش‌فرض Lead Score (همان آستانه‌های خارجی، فقط برای پیشنهاد داخلی)
- بدون کپی تماس/تحلیل داخل Activity؛ Reference کافی است

## باید ساخته شود

1. جداول: `crm_tasks`, `crm_task_events`, `crm_ai_task_suggestions`, `crm_task_reminders`
2. Actions وضعیت و تخصیص
3. Timeline ترکیبی خواندنی (Task events + Deal activities + لینک تماس)
4. صفحات `/crm/tasks` زیر سایدبار CRM
5. بخش وظایف در Customer و Deal Show
6. پیشنهاد AI با تأیید انسانی پیش‌فرض
7. یادآوری درون‌برنامه‌ای + دستور زمان‌بندی

## ریسک داده

- Migration فقط افزایشی است؛ داده Deal/`next_action_*` حفظ می‌شود.
- Mode تغییر کند، Taskهای تاریخی حذف نمی‌شوند؛ Job قبل از side effect Mode را چک می‌کند.
- Task خارجی Didar/Dynamics دست‌نخورده می‌ماند؛ هیچ push از Tasks داخلی نیست.
