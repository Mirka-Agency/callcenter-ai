# استقرار محلی (on-prem) — راهنمای کامل از سرور خام تا تحویل

هدف: همان محصول را روی LAN کارفرما با **یک سازمان** نصب کنید.

| نقش | آدرس | چه کسی |
|-----|------|--------|
| ادمین پلتفرم | `/admin` | **فقط شما** (کارفرما این را لازم ندارد) |
| کارفرما | `/app` | مدیر سازمان مشتری |
| کارشناس | `/workspace` | اپراتورها |

Asterisk/Simotel **لازم نیست** روی همان سرور اپ باشد؛ IP داخلی جدا کافی است، فقط باید در شبکه به هم برسند.

---

## ۰. جواب سریع به سوال‌های پرتکرار

### رمز ادمین از کجاست؟ «دیگه رمز نداره» یعنی چه؟

رمز از پیش‌ساختهٔ مخفی در محصول **وجود ندارد**. شما خودتان قبل از seed در فایل `.env` می‌نویسید:

```env
ONPREM_ADMIN_EMAIL=mehdi@yourcompany.com
ONPREM_ADMIN_PASSWORD=یک-رمز-قوی-که-خودت-انتخاب-میکنی
ONPREM_EMPLOYER_EMAIL=manager@customer.local
ONPREM_EMPLOYER_PASSWORD=رمز-کارفرما
```

بعد دستور `OnPremSeeder` همان ایمیل/رمز را در دیتابیس می‌سازد.

| ورود | آدرس | ایمیل | رمز |
|------|------|--------|-----|
| ادمین شما | `http://IP-سرور:8000/admin` | مقدار `ONPREM_ADMIN_EMAIL` | مقدار `ONPREM_ADMIN_PASSWORD` |
| کارفرما | `http://IP-سرور:8000/app` | مقدار `ONPREM_EMPLOYER_EMAIL` | مقدار `ONPREM_EMPLOYER_PASSWORD` |

اگر seeder را با مقادیر مثال (`change-me-admin-password`) زده باشید، همان‌ها رمز ورود هستند — **قبل از تحویل عوضشان کنید** (یا `.env` را درست کنید و دوباره seed بزنید / از ادمین رمز را reset کنید).

`PlatformFoundationSeeder` داخل همان فرآیند ممکن است کاربر قدیمی `admin@example.com` / `password` هم بسازد؛ برای on-prem فقط از اکانتی که با `ONPREM_ADMIN_*` ساختید استفاده کنید و در صورت تمایل کاربرهای مثال را بعداً از ادمین حذف کنید.

### باید zip کنم ببرم روی سرور؟

**یکی از این سه روش** — zip کاملاً معتبر است، مخصوصاً وقتی سرور مشتری به Git شما دسترسی ندارد:

| روش | کی مناسب است |
|-----|----------------|
| **A) zip + USB/scp** | سرور خام / بدون دسترسی Git — پیشنهادی برای بیشتر مشتریان |
| **B) git clone** | سرور به ریپو دسترسی دارد و اینترنت دارد |
| **C) ایمیج Docker آماده** | وقتی روی لپ‌تاپ build کرده‌اید و می‌خواهید روی سرور فقط `load` کنید |

جزئیات هر روش در بخش ۲ آمده است.

---

## ۱. پیش‌نیازها (قبل از رفتن پیش مشتری)

روی **لپ‌تاپ خودتان** یا هر جایی که کد را دارید:

- [ ] نسخهٔ پایدار پروژه (همان commitی که می‌خواهید نصب شود)
- [ ] IP پیشنهادی سرور اپ در LAN مشتری (مثلاً `10.0.0.50`)
- [ ] IP سرور Asterisk/Simotel (مثلاً `10.0.0.20`) — اگر جداست
- [ ] ایمیل/رمز ادمین (مال شما) و ایمیل/رمز کارفرما (مال مشتری)
- [ ] کلید API مدل LLM (برای مرحلهٔ بعد از نصب)
- [ ] فلش/دسترسی scp به سرور مشتری

روی **سرور مشتری** (خام):

- [ ] Linux (Ubuntu 22.04/24.04 پیشنهاد می‌شود)
- [ ] حداقل ~2 CPU، 4GB RAM، 40GB دیسک (بیشتر اگر ضبط صدا زیاد است)
- [ ] IP ثابت در LAN
- [ ] خروجی اینترنت برای: نصب Docker + pull ایمیج MySQL + API هوش مصنوعی
- [ ] دسترسی SSH با sudo

---

## ۲. انتقال کد به سرور

### روش A — zip (پیشنهادی برای سرور خام بدون Git)

روی لپ‌تاپ، از ریشهٔ پروژه:

```bash
chmod +x scripts/package-onprem.sh
./scripts/package-onprem.sh
# خروجی مثلاً: dist/avayar-onprem-YYYYMMDD.zip
```

بعد انتقال:

```bash
# مثال با scp:
scp dist/avayar-onprem-*.zip user@10.0.0.50:/tmp/

# روی سرور:
sudo mkdir -p /opt/callcenter
sudo unzip /tmp/avayar-onprem-*.zip -d /opt/callcenter
cd /opt/callcenter
```

یا کپی با فلش به همان مسیر.

اسکریپت `vendor` و `node_modules` را عمداً داخل zip نمی‌گذارد؛ روی سرور با Docker **build** می‌شوند (نیاز به اینترنت در زمان build).

### روش B — git clone

```bash
sudo mkdir -p /opt/callcenter
sudo git clone <URL-ریپو> /opt/callcenter
cd /opt/callcenter
sudo git checkout <tag-یا-branch-پایدار>
```

### روش C — ایمیج از پیش ساخته

روی لپ‌تاپ (با اینترنت):

```bash
docker compose -f docker-compose.onprem.yml build
docker save callcenter-app:latest | gzip > avayar-app.tar.gz
# نام ایمیج را با docker images چک کنید
```

روی سرور:

```bash
gunzip -c avayar-app.tar.gz | docker load
# کد/compose و .env را هم باید روی سرور داشته باشید (حداقل zip سبک)
```

برای اکثر نصب‌ها روش A ساده‌تر است.

---

## ۳. آماده‌سازی سرور خام (یک‌بار)

دستورها برای Ubuntu/Debian. با کاربر دارای sudo:

```bash
sudo apt-get update
sudo apt-get install -y ca-certificates curl unzip git

# نصب Docker (رسمی)
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker "$USER"
# یک‌بار logout/login کنید تا گروه docker اعمال شود

docker --version
docker compose version
```

فایروال (اگر ufw روشن است) — پورت وب اپ را باز کنید (پیش‌فرض compose: `8000`):

```bash
sudo ufw allow OpenSSH
sudo ufw allow 8000/tcp
# اگر realtime می‌خواهید:
# sudo ufw allow 8090/tcp
sudo ufw enable
```

IP سرور را یادداشت کنید:

```bash
hostname -I
```

---

## ۴. تنظیم `.env` (رمزها همین‌جا تعریف می‌شوند)

```bash
cd /opt/callcenter
cp .env.onprem.example .env
nano .env   # یا vim
```

### ۴.۱ موارد اجباری

| متغیر | چه بگذارید |
|--------|------------|
| `APP_URL` | `http://10.0.0.50:8000` — **IP واقعی LAN همین سرور اپ** (نه `localhost` اگر Asterisk جای دیگری است) |
| `APP_KEY` | فعلاً خالی بگذارید؛ بعد از بالا آمدن کانتینر generate می‌کنیم |
| `DB_PASSWORD` | یک رمز قوی برای MySQL |
| `ONPREM_ADMIN_EMAIL` | ایمیل ورود شما به `/admin` |
| `ONPREM_ADMIN_PASSWORD` | **رمز ادمین شما** (خودتان انتخاب کنید) |
| `ONPREM_EMPLOYER_EMAIL` | ایمیل کارفرما برای `/app` |
| `ONPREM_EMPLOYER_PASSWORD` | رمز کارفرما |
| `ONPREM_ORG_TITLE` | نام سازمان مشتری |
| `RECORDINGS_DISK` | `local` (همین پیش‌فرض مثال) |

نمونهٔ حداقلی برای بخش کاربران:

```env
APP_URL=http://10.0.0.50:8000

DB_PASSWORD=Db!StrongPass-ChangeMe

ONPREM_ADMIN_NAME="Mehdi"
ONPREM_ADMIN_EMAIL=mehdi@yourcompany.com
ONPREM_ADMIN_PASSWORD=Admin!StrongPass-ChangeMe

ONPREM_EMPLOYER_NAME="مدیر مرکز تماس"
ONPREM_EMPLOYER_EMAIL=manager@customer.local
ONPREM_EMPLOYER_PASSWORD=Employer!Pass-ChangeMe

ONPREM_ORG_TITLE="شرکت نمونه"
ONPREM_WALLET_BALANCE=100000000
ONPREM_EMPLOYER_CAN_MANAGE_INTEGRATIONS=true
```

`ONPREM_EMPLOYER_CAN_MANAGE_INTEGRATIONS=true` یعنی کارفرما در `/app` بتواند اتصال VoIP/webhook را ببیند؛ اگر می‌خواهید فقط شما از `/admin` تنظیم کنید، `false` بگذارید.

---

## ۵. بالا آوردن سرویس‌ها و ساخت کلید

```bash
cd /opt/callcenter
docker compose -f docker-compose.onprem.yml up -d --build
```

اولین build چند دقیقه طول می‌کشد (اینترنت لازم است).

صبر کنید تا healthy شود:

```bash
docker compose -f docker-compose.onprem.yml ps
docker compose -f docker-compose.onprem.yml logs -f app
# با Ctrl+C از لاگ خارج شوید وقتی migrate تمام شد
```

ساخت `APP_KEY` (اگر خالی بود):

```bash
docker compose -f docker-compose.onprem.yml exec app php artisan key:generate --force
```

چک سلامت:

```bash
curl -sS -o /dev/null -w "%{http_code}\n" http://127.0.0.1:8000/up
# انتظار: 200
```

از یک سیستم در LAN:

مرورگر → `http://10.0.0.50:8000` (IP خودتان)

اختیاری — realtime (Reverb):

```bash
docker compose -f docker-compose.onprem.yml --profile realtime up -d
```

---

## ۶. بوت‌استرپ یک سازمان (ساخت ادمین + کارفرما)

**فقط یک‌بار** بعد از اینکه `.env` رمزها را درست نوشته‌اید:

```bash
docker compose -f docker-compose.onprem.yml exec app php artisan db:seed --class=OnPremSeeder --force
```

این کار انجام می‌دهد:

1. ارائه‌دهنده‌های CRM / VoIP / LLM و تنظیمات پلتفرم
2. کاربر SuperAdmin با `ONPREM_ADMIN_*`
3. کاربر Employer + یک Organization با `ONPREM_EMPLOYER_*` / `ONPREM_ORG_TITLE`
4. شارژ اولیهٔ کیف‌پول AI
5. **بدون** دادهٔ دمو چندسازمانه

ورود آزمایشی:

1. `http://IP:8000/admin` → ایمیل و رمز `ONPREM_ADMIN_*`
2. `http://IP:8000/app` → ایمیل و رمز `ONPREM_EMPLOYER_*`

اگر رمز را اشتباه گذاشته‌اید:

- یا در `.env` درست کنید و دوباره همان seeder را بزنید (`updateOrCreate` رمز را عوض می‌کند)،
- یا از پنل ادمین (اگر هنوز با رمز قبلی وارد می‌شوید) کاربر را ویرایش کنید.

---

## ۷. پیکربندی بعد از نصب (با هم در `/admin`)

با اکانت ادمین خودتان:

1. **LLM:** کلید API ارائه‌دهنده و مدل پیش‌فرض پلتفرم را تنظیم کنید  
2. **کیف‌پول:** در صورت نیاز شارژ بیشتر برای سازمان  
3. **VoIP:** اتصال سازمان را بسازید (بخش ۸)  
4. **کارشناسان:** کاربران employee بسازید و extension را نگاشت کنید  
5. رمز ورود را به کارفرما بدهید (`/app`) — پنل `/admin` را به آن‌ها ندهید  

---

## ۸. شبکه و VoIP (Asterisk جدا از اپ)

```text
[کارشناسان مرورگر] ──► [سرور اپ :8000]
[Asterisk/Simotel IP جدا] ──POST webhook──► [اپ /webhooks/voip/{token}]
[اپ] ──فقط اگر Simotel──► [api_url داخلی]
[اپ] ──تحلیل AI──► [اینترنت]
```

### ۸.۱ Asterisk خام (سفارشی)

- نوع اتصال در ادمین/کارفرما: **سفارشی / Asterisk**
- فقط **PBX → اپ** لازم است
- URL وب‌هوک را از UI کپی کنید؛ باید با `APP_URL` یکی باشد (IP LAN اپ)

چک از **ماشین Asterisk**:

```bash
curl -sS -o /dev/null -w "%{http_code}\n" \
  -X POST "http://10.0.0.50:8000/webhooks/voip/<TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{"event":"cdr","unique_id":"test-1","caller":"09120000000","callee":"1001","status":"answered","duration":10}'
```

انتظار معمول: `202`. اگر وصل نشد → فایروال یا IP/پورت اشتباه.

راهنمای dialplan داخل UI اتصال سفارشی هم هست.

### ۸.۲ Simotel روی IP داخلی

- `api_url` مثل `http://10.0.0.20/api/v4`
- ترافیک **دوطرفه** لازم است

از سرور اپ:

```bash
curl -sS -o /dev/null -w "%{http_code}\n" "http://10.0.0.20/api/v4"
```

### ۸.۳ ضبط صدا

اگر `recording_url` در webhook می‌آید، سرور اپ باید آن آدرس را روی LAN باز کند؛ وگرنه تماس ثبت می‌شود ولی تحلیل صوتی کامل نمی‌شود.

### ۸.۴ فایروال

| مبدأ | مقصد | پورت |
|------|------|------|
| کارشناسان | اپ | 8000 (یا 80/443 اگر reverse proxy گذاشتید) |
| Asterisk/Simotel | اپ | همان پورت وب |
| اپ | Simotel | پورت API |
| اپ | اینترنت | 443 برای LLM |

---

## ۹. چک‌لیست تحویل به کارفرما

- [ ] `/up` از LAN جواب ۲۰۰ می‌دهد
- [ ] ورود ادمین شما به `/admin` OK است
- [ ] ورود کارفرما به `/app` OK است
- [ ] حداقل یک کارشناس وارد `/workspace` می‌شود
- [ ] تست webhook از Asterisk موفق است
- [ ] یک تماس آزمایشی در سیستم دیده می‌شود
- [ ] (در صورت AI) یک تحلیل آزمایشی با LLM OK است
- [ ] کارفرما **رمز `/admin` را ندارد**؛ فقط `/app`
- [ ] بکاپ اول گرفته شده (بخش ۱۰)
- [ ] رمزهای مثال در `.env` باقی نمانده‌اند

---

## ۱۰. بهره‌برداری: بکاپ، آپدیت، عیب‌یابی

### بکاپ

```bash
cd /opt/callcenter
# دیتابیس
docker compose -f docker-compose.onprem.yml exec -T mysql \
  mysqldump -u callcenter -p"$DB_PASSWORD" callcenter > backup-$(date +%F).sql

# volume ذخیره (ضبط‌ها داخل Docker volume است)
docker run --rm -v callcenter_onprem_storage:/data -v "$(pwd)":/backup alpine \
  tar czf /backup/storage-$(date +%F).tar.gz -C /data .
# نام volume را با `docker volume ls` تأیید کنید
```

### آپدیت نسخه (شما انجام می‌دهید)

```bash
cd /opt/callcenter
# اگر zip جدید آوردید: فایل‌ها را جایگزین کنید (بدون پاک کردن .env و بدون حذف volumeها)
# اگر git: git pull

docker compose -f docker-compose.onprem.yml up -d --build
```

`.env` و volumeهای MySQL/storage را پاک نکنید.

### لاگ و وضعیت

```bash
docker compose -f docker-compose.onprem.yml ps
docker compose -f docker-compose.onprem.yml logs -f app
```

### خطای «Table already exists» موقع migrate

معمولاً یعنی migrate یک‌بار نیمه‌کاره مانده (جدول ساخته شده، ولی در جدول `migrations` ثبت نشده) و با restart دوباره همان migration اجرا شده.

اگر نصب هنوز تازه است و دادهٔ مهمی ندارید (ساده‌ترین راه):

```bash
cd /opt/callcenter
docker compose -f docker-compose.onprem.yml down -v   # حجم MySQL و storage پاک می‌شود
docker compose -f docker-compose.onprem.yml up -d --build
# بعد دوباره key:generate و OnPremSeeder
```

اگر نمی‌خواهید volume را پاک کنید:

```bash
# رمز DB را از .env بردارید
docker compose -f docker-compose.onprem.yml exec mysql \
  mysql -u callcenter -p"$DB_PASSWORD" callcenter -e \
  "DROP TABLE IF EXISTS employee_integration_meta, integration_meta_definitions;"

docker compose -f docker-compose.onprem.yml restart app
docker compose -f docker-compose.onprem.yml logs -f app
```

روی نسخه‌های جدیدتر، migrationها نسبت به restart نیمه‌کاره مقاوم شده‌اند (`IdempotentSchema`); کد را آپدیت کنید و `up -d --build` بزنید. اگر هنوز روی نسخهٔ قدیمی هستید، یکی از دو راه بالا را بزنید.

### مدل عملیاتی با مشتری

- نصب و تنظیمات اولیه را **با هم** انجام می‌دهید؛ ادمین پلتفرم نزد شما می‌ماند
- آپدیت و بکاپ مسئولیت شماست (SSH/VPN یا حضور)
- بدون اینترنت خروجی برای API مدل، تحلیل AI کار نمی‌کند — قبل از قرارداد بگویید
- داخل LAN معمولاً HTTP کافی است؛ اگر از بیرون/VPN دارند TLS بگذارید و `APP_URL` را `https://...` کنید

---

## ۱۱. خلاصهٔ مسیر شما از صفر تا انتها

```text
1. روی لپ‌تاپ: ./scripts/package-onprem.sh  →  فایل zip
2. zip را به سرور خام ببرید (scp / فلش) و در /opt/callcenter باز کنید
3. Docker را روی سرور نصب کنید
4. cp .env.onprem.example .env  →  APP_URL + رمز ادمین/کارفرما/DB
5. docker compose -f docker-compose.onprem.yml up -d --build
6. php artisan key:generate --force  (داخل کانتینر app)
7. php artisan db:seed --class=OnPremSeeder --force
8. ورود /admin با ONPREM_ADMIN_*  →  LLM + VoIP + کارشناسان
9. تست webhook از Asterisk
10. تحویل /app به کارفرما + بکاپ اول
```

---

## ۱۲. چیزهایی که عمداً انجام نمی‌دهیم

- محصول جدا یا حذف پنل ادمین
- اجبار به نصب Asterisk روی همان ماشین اپ
- دیتابیس جدا per-tenant

فایل‌های مرتبط: `.env.onprem.example`، `docker-compose.onprem.yml`، `database/seeders/OnPremSeeder.php`، `scripts/package-onprem.sh`
