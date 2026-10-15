# گزارش نهایی — Tasks & Activities (Module 03)

## ۱. معماری

ماژول داخلی زیر `app/Application/Crm/Tasks` و `app/Domain/Crm/Tasks`، موازی با Deals، فقط در حالت `OrganizationCrmMode::Internal`.  
Task واقعی ≠ پیشنهاد AI ≠ Activity تاریخچه. تاریخچه وظیفه در `crm_task_events` (append-only). Timeline معامله همچنان `crm_deal_activities` است و داده تماس کپی نمی‌شود.

## ۲. دیتابیس

Migration: `2026_10_14_160000_create_crm_task_tables.php`

- `crm_tasks`
- `crm_task_events`
- `crm_ai_task_suggestions` (unique analysis+fingerprint)
- `crm_task_reminders` (idempotency_key)
- `notifications` در صورت نبود

## ۳–۶. Models / Actions / Events / Jobs

Models: `CrmTask`, `CrmTaskEvent`, `CrmAiTaskSuggestion`, `CrmTaskReminder`  
Actions: Create/Update/Assign/Start/Complete/Cancel/Reopen + Suggest/Accept/Dismiss  
Listener: `SuggestTasksFromAnalysisListener` روی `CustomerSyncedFromAnalysis` (صف `followup`)  
Command: `crm:tasks-send-reminders` هر ۱۵ دقیقه  
Notifications: تخصیص و یادآوری (database)

## ۷–۹. Activities / AI / Reminders

- Activities وظیفه: رویدادهای `crm_task_events`
- AI: استخراج از `CustomerNextActionAggregator`؛ تأیید انسانی پیش‌فرض؛ تماس شخصی و Mode خارجی skip
- موعد پیشنهادی: همان آستانه‌های Lead Score (۴س/۱ر/۳ر) فقط برای پیشنهاد؛ موعد انسانی بازنویسی نمی‌شود مگر با ویرایش کارشناس
- Reminder: idempotent؛ با تغییر version/due یا تکمیل متوقف می‌شود

## ۱۰–۱۱. Frontend / Sidebar

- `/app/crm/tasks` و `/workspace/crm/tasks`
- جزئیات وظیفه، بخش وظایف در Deal و Customer
- سایدبار CRM: «وظایف و پیگیری‌ها» فقط Internal
- صفحه Activities مستقل اضافه نشد

## ۱۲–۱۳. CRM Mode و اتصال Customer/Deal

- `TaskAuthorization::assertInternalCrm`
- Routes زیر `organization-crm:internal`
- FK و اعتبارسنجی tenant برای customer/company/deal/assignee
- مسئول Task با تغییر مالک Deal عوض نمی‌شود

## ۱۴. تست‌ها

`tests/Feature/CrmTasksAndActivitiesTest.php` — سناریوهای ساخت، ارتباط، لغو، AI، Mode، reminder، concurrency، صفحه.

## ۱۵–۱۶. باقی‌مانده و آینده

- استخراج زمان صریح گفتاری مشتری (شنبه آینده) هنوز heuristic ساده ندارد
- Inbox اعلان‌ها در UI جداگانه نیست (دیتابیس notifications آماده است)
- Workflow automation و قوانین پیچیده برای فاز بعد
