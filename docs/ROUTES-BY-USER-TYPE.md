# الروابط والمستخدمون حسب نوع الحساب

## التحقق من المنصات الثلاث وتسجيل الدخول

### المنصات الثلاث

| المنصة | الوصف | نوع الحساب | بادئة المسارات |
|--------|--------|------------|----------------|
| **المنصة الرئيسية (Admin)** | مدخل موحد — لوحة تحكم كاملة (شحنات، طلبات، متاجر، مستخدمين، تقارير، منظمات، إلخ) | أي نوع | بدون بادئة |
| **بوابة B2C** | بوابة الأفراد — شحنات، تتبع، محفظة، عناوين، دعم، إعدادات فقط | `individual` | `b2c.*` |
| **بوابة B2B** | بوابة الأعمال — نفس B2C + الطلبات، المتاجر، المستخدمين، الأدوار، الدعوات، التقارير | `organization` | `b2b.*` |

### الشاشات المتعلقة بكل منصة

- **المنصة الرئيسية:** `routes/web.php` — الشاشة الأولى بعد الدخول: `/` (لوحة التحكم)، Layout: `layouts.app`، View: `pages.dashboard.index` وغيرها تحت `pages/`.
- **B2C:** `routes/web_b2c.php` — الشاشة الأولى بعد الدخول: `/b2c/dashboard`، Views مستقلة تحت `b2c/` (مثل `b2c.dashboard`, `b2c.shipments.index`).
- **B2B:** `routes/web_b2b.php` — الشاشة الأولى بعد الدخول: `/b2b/dashboard`، Views مستقلة تحت `b2b/` (مثل `b2b.dashboard`, `b2b.shipments.index`).

### تسجيل الدخول لكل منصة

| المنصة | رابط صفحة الدخول | إجراء النموذج | الحقول المطلوبة | بعد النجاح |
|--------|------------------|---------------|-----------------|------------|
| **المنصة الرئيسية** | `/login` | `POST /login` (اسم المسار: `login`) | البريد، كلمة المرور، (تذكرني) | التوجيه إلى `/` (dashboard) |
| **B2C** | `/b2c/login` | `POST /b2c/login` (اسم المسار: `b2c.login.submit`) | البريد، كلمة المرور، (تذكرني) | التوجيه إلى `/b2c/dashboard` |
| **B2B** | `/b2b/login` | `POST /b2b/login` (اسم المسار: `b2b.login.submit`) | **معرّف المنظمة (Slug)**، البريد، كلمة المرور، (تذكرني) | التوجيه إلى `/b2b/dashboard` |

- **المنصة الرئيسية:** View: `pages.auth.login`، Controller: `AuthWebController`.
- **B2C:** View: `b2c.b2c-login` (المسار: `resources/views/b2c/b2c-login.blade.php`)، Controller: `B2CAuthWebController`.
- **B2B:** View: `b2b.b2b-login` (المسار: `resources/views/b2b/b2b-login.blade.php`)، Controller: `B2BAuthWebController` — يتطلب حقل `account_slug` (معرّف الحساب) لتحديد المنظمة.

### بيانات تسجيل الدخول التجريبية (بعد `php artisan db:seed`)

| المنصة | معرّف المنظمة (B2B فقط) | البريد | كلمة المرور |
|--------|--------------------------|--------|-------------|
| **منصة إدارة المنصة** (لوحة تحكم كاملة — **ليس B2C ولا B2B**) | — | `admin@platform.sa` أو `admin@gateway.sa` | `password` |
| **B2C** (أفراد) | — | `user@individual.sa` أو `b2c@individual.sa` | `password` |
| **B2B** (منظمات) | `demo-company` | `admin@company.sa` أو `b2b@company.sa` | `password` |

لـ B2B يمكن أيضاً استخدام: `fatima@company.sa`, `khalid@company.sa`, `noura@company.sa` مع نفس المعرّف `demo-company` وكلمة المرور `password`.

### مستخدم منصة إدارة المنصة (ليس B2C ولا B2B)

مستخدم **منصة إدارة المنصة** هو للحساب الذي يدخل من **المنصة الرئيسية فقط** (`/login`) ويرى كل شاشات الإدارة (لوحة التحكم، الشحنات، الطلبات، المتاجر، المحفظة، المستخدمين، الدعم، التتبع، الأدوار، الدعوات، الإشعارات، العناوين، الإعدادات، التقارير، المالية، التدقيق، التسعير، الإدارة، KYC، DG، المنظمات، الحاويات، الجمارك، السائقين، المطالبات، السفن، الجداول، الفروع، الشركات، أكواد HS، المخاطر، وتسجيل الخروج). **لا يستخدم بوابة B2C (`/b2c/login`) ولا بوابة B2B (`/b2b/login`).**

| البريد | كلمة المرور | صفحة الدخول |
|--------|-------------|--------------|
| **`admin@platform.sa`** | `password` | **`/login`** فقط |
| **`admin@gateway.sa`** | `password` | **`/login`** فقط |

بعد الدخول يُوجّه إلى `/` وتظهر قائمة Admin الكاملة. يُنشأ هذان المستخدمان من السيدر `DevPlatformAdminSeeder` عند تشغيل `php artisan db:seed`.

### مستخدم Admin — يظهر له كل الشاشات (الجدول أدناه)

نفس المستخدمين أعلاه: عند تسجيل الدخول من **`/login`** يظهر لهم **كل** الشاشات المذكورة. (راجع الجدول في قسم «المنصة الرئيسية» للروابط.)

---

## ملخص سريع

| نوع المستخدم | بوابة الدخول | رابط تسجيل الدخول | البادئة |
|-------------|-------------|-------------------|---------|
| **أفراد (B2C)** | بوابة الأفراد | `/b2c/login` | `b2c.*` |
| **منظمات (B2B)** | بوابة الأعمال | `/b2b/login` | `b2b.*` |
| **المنصة الرئيسية** | المدخل الموحد (Blade) | `/login` | بدون بادئة |

---

## المستخدمون لكل نوع

نوع المستخدم يُحدد من **نوع الحساب (Account)** وليس من المستخدم (User). كل مستخدم ينتمي لحساب واحد، والحساب إما `individual` أو `organization`.

### مستخدمون تجريبيون (بعد تشغيل `php artisan db:seed`)

| النوع | البريد | كلمة المرور | صفحة الدخول |
|-------|--------|-------------|--------------|
| **B2C** (أفراد) | `user@individual.sa` أو `b2c@individual.sa` | `password` | `/b2c/login` |
| **B2B** (منظمات) | `admin@company.sa` أو `b2b@company.sa` | `password` | `/b2b/login` (معرّف: `demo-company`) |
| **Admin** (كل الشاشات) | `admin@platform.sa` أو `admin@gateway.sa` | `password` | `/login` |

| النوع | الحقل في النظام | من يدخل من |
|-------|-----------------|-------------|
| **أفراد (B2C)** | `accounts.type = 'individual'` | `/b2c/login` فقط |
| **منظمات (B2B)** | `accounts.type = 'organization'` | `/b2b/login` فقط |
| **المنصة الرئيسية** | أي نوع حساب | `/login` |

### المستخدمون المخصصون لـ B2C (أفراد — Individual)

- **التعريف:** مستخدمون مرتبطون بحساب `account.type === 'individual'`. يدخلون **فقط** من `/b2c/login`.
- **الصلاحيات:** شحنات، تتبع، محفظة، عناوين، دعم، إعدادات (بدون طلبات/متاجر/مستخدمين/أدوار/دعوات).
- **مستخدمون مخصصون لـ B2C (بعد تشغيل البذور):**

| البريد | كلمة المرور | صفحة الدخول |
|--------|-------------|--------------|
| `user@individual.sa` | `password` | `/b2c/login` |
| `b2c@individual.sa` | `password` | `/b2c/login` |

لا يُطلب معرّف منظمة؛ الحقول: البريد + كلمة المرور فقط.

### المستخدمون المخصصون لـ B2B (منظمات — Organization)

- **التعريف:** مستخدمون مرتبطون بحساب `account.type === 'organization'`. يدخلون **فقط** من `/b2b/login` مع **معرّف المنظمة**.
- **الصلاحيات:** شحنات، طلبات، متاجر، مستخدمين، أدوار، دعوات، محفظة، تقارير، إعدادات.
- **مستخدمون مخصصون لـ B2B (بعد تشغيل البذور):**

| البريد | كلمة المرور | معرّف المنظمة | صفحة الدخول |
|--------|-------------|----------------|--------------|
| `admin@company.sa` | `password` | `demo-company` | `/b2b/login` |
| `b2b@company.sa` | `password` | `demo-company` | `/b2b/login` |

من نفس الحساب (DemoAccountSeeder) يمكن أيضاً: `fatima@company.sa`, `khalid@company.sa`, `noura@company.sa` مع معرّف `demo-company`.

### مستخدم B2B المحدد للتجربة في المتصفح

لاختبار بوابة B2B في المتصفح استخدم المستخدم التالي (بعد تشغيل `php artisan db:seed`):

| الحقل | القيمة |
|--------|--------|
| **رابط صفحة الدخول** | `http://127.0.0.1:8000/b2b/login` (أو `http://localhost:8000/b2b/login` عند تشغيل `php artisan serve`) |
| **معرّف المنظمة (Slug)** | `demo-company` |
| **البريد الإلكتروني** | `b2b@company.sa` |
| **كلمة المرور** | `password` |

بعد تسجيل الدخول يتم التوجيه إلى `/b2b/dashboard`.

### مستخدمون Admin (المنصة الرئيسية — `/login`)

- **التعريف:** مستخدم مخصّص للمدخل الموحد ولوحة التحكم الكاملة (شحنات، طلبات، متاجر، مستخدمين، تقارير، منظمات، إلخ).
- **مستخدم تجريبي:**

| البريد | كلمة المرور | الاستخدام |
|--------|-------------|-----------|
| `admin@platform.sa` | `password` | تسجيل الدخول من `/login` |

- يدخل منها أيضاً أي مستخدم آخر (فرد أو منظمة) إذا تم توجيهه لـ `/login`؛ نوع الحساب يحدد الصلاحيات.

### إنشاء مستخدم جديد لكل نوع

- **B2C (فرد):** إنشاء `Account` بـ `type = 'individual'` ثم إنشاء `User` مرتبط بهذا الحساب.
- **B2B (منظمة):** إنشاء `Account` بـ `type = 'organization'` (وربما `OrganizationProfile` و `Organization`) ثم إنشاء `User` مرتبط بالحساب.

---

## 1. مستخدمون أفراد — B2C (Individual)

**الحساب:** `account.type === 'individual'`  
**تسجيل الدخول:** `/b2c/login`  
**اسم المسار:** `b2c.login`

### الروابط بعد تسجيل الدخول (كلها تحت `/b2c/`)

| الوظيفة | الرابط | اسم المسار |
|--------|--------|------------|
| لوحة التحكم | `/b2c/dashboard` | `b2c.dashboard` |
| الشحنات | `/b2c/shipments` | `b2c.shipments.index` |
| إنشاء شحنة | `/b2c/shipments/create` | `b2c.shipments.create` |
| عرض شحنة | `/b2c/shipments/{id}` | `b2c.shipments.show` |
| التتبع | `/b2c/tracking` | `b2c.tracking.index` |
| تتبع رقم | `/b2c/tracking/{trackingNumber}` | `b2c.tracking.show` |
| المحفظة | `/b2c/wallet` | `b2c.wallet.index` |
| العناوين | `/b2c/addresses` | `b2c.addresses.index` |
| الدعم | `/b2c/support` | `b2c.support.index` |
| الإعدادات | `/b2c/settings` | `b2c.settings.index` |
| تسجيل الخروج | POST `/b2c/logout` | `b2c.logout` |

**ملاحظة:** B2C لا يشمل: الطلبات، المتاجر، الدعوات، المستخدمين، الأدوار، التقارير.

---

## 2. مستخدمون منظمات — B2B (Organization)

**الحساب:** `account.type === 'organization'`  
**تسجيل الدخول:** `/b2b/login`  
**اسم المسار:** `b2b.login`

### الروابط بعد تسجيل الدخول (كلها تحت `/b2b/`)

| الوظيفة | الرابط | اسم المسار |
|--------|--------|------------|
| لوحة التحكم | `/b2b/dashboard` | `b2b.dashboard` |
| الشحنات | `/b2b/shipments` | `b2b.shipments.index` |
| إنشاء شحنة | `/b2b/shipments/create` | `b2b.shipments.create` |
| عرض شحنة | `/b2b/shipments/{id}` | `b2b.shipments.show` |
| الطلبات | `/b2b/orders` | `b2b.orders.index` |
| عرض طلب | `/b2b/orders/{id}` | `b2b.orders.show` |
| المتاجر | `/b2b/stores` | `b2b.stores.index` |
| المستخدمين | `/b2b/users` | `b2b.users.index` |
| الأدوار | `/b2b/roles` | `b2b.roles.index` |
| الدعوات | `/b2b/invitations` | `b2b.invitations.index` |
| المحفظة | `/b2b/wallet` | `b2b.wallet.index` |
| التقارير | `/b2b/reports` | `b2b.reports.index` |
| الإعدادات | `/b2b/settings` | `b2b.settings.index` |
| تسجيل الخروج | POST `/b2b/logout` | `b2b.logout` |

---

## 3. المنصة الرئيسية (Blade — بدون بادئة)

**تسجيل الدخول:** `/login`  
**اسم المسار:** `login`  
تُستخدم للمستخدمين الذين يدخلون من المدخل الموحد (قد يكون حساب فردي أو منظمة حسب التنفيذ).

### الروابط بعد تسجيل الدخول (بدون بادئة)

| الوظيفة | الرابط | اسم المسار |
|--------|--------|------------|
| لوحة التحكم | `/` | `dashboard` |
| الشحنات | `/shipments` | `shipments.index` |
| إنشاء شحنة | `/shipments/create` | `shipments.create` |
| عرض شحنة | `/shipments/{shipment}` | `shipments.show` |
| تصدير شحنات | `/shipments/export` | `shipments.export` |
| إلغاء شحنة | PATCH `/shipments/{shipment}/cancel` | `shipments.cancel` |
| إرجاع شحنة | POST `/shipments/{shipment}/return` | `shipments.return` |
| تسمية شحنة | `/shipments/{shipment}/label` | `shipments.label` |
| الطلبات | `/orders` | `orders.index` |
| المتاجر | `/stores` | `stores.index` |
| المحفظة | `/wallet` | `wallet.index` |
| تعبئة المحفظة | POST `/wallet/topup` | `wallet.topup` |
| المستخدمين | `/users` | `users.index` |
| تعديل مستخدم | `/users/{user}/edit` | `users.edit` |
| الدعم | `/support` | `support.index` |
| تذكرة دعم | `/support/{ticket}` | `support.show` |
| التتبع | `/tracking` | `tracking.index` |
| الأدوار | `/roles` | `roles.index` |
| الدعوات | `/invitations` | `invitations.index` |
| الإشعارات | `/notifications` | `notifications.index` |
| العناوين | `/addresses` | `addresses.index` |
| الإعدادات | `/settings` | `settings.index` |
| التقارير | `/reports` | `reports.index` |
| تصدير تقرير | `/reports/export/{type}` | `reports.export` |
| المالية | `/financial` | `financial.index` |
| التدقيق | `/audit` | `audit.index` |
| التسعير | `/pricing` | `pricing.index` |
| الإدارة | `/admin` | `admin.index` |
| KYC | `/kyc` | `kyc.index` |
| DG | `/dg` | `dg.index` |
| المنظمات | `/organizations` | `organizations.index` |
| إنشاء منظمة | POST `/organizations` | `organizations.store` |
| الحاويات | `/containers` | `containers.index` |
| الجمارك | `/customs` | `customs.index` |
| السائقين | `/drivers` | `drivers.index` |
| المطالبات | `/claims` | `claims.index` |
| السفن | `/vessels` | `vessels.index` |
| الجداول | `/schedules` | `schedules.index` |
| الفروع | `/branches` | `branches.index` |
| الشركات | `/companies` | `companies.index` |
| أكواد HS | `/hscodes` | `hscodes.index` |
| المخاطر | `/risk` | `risk.index` |
| تسجيل الخروج | POST `/logout` | `logout` |

---

## استخدام أسماء المسارات في Blade

- **B2C:** استخدم دائماً `route('b2c.xxx')` مثل `route('b2c.dashboard')`, `route('b2c.shipments.index')`.
- **B2B:** استخدم دائماً `route('b2b.xxx')` مثل `route('b2b.dashboard')`, `route('b2b.orders.index')`.
- **المنصة الرئيسية:** استخدم أسماء بدون بادئة مثل `route('dashboard')`, `route('shipments.index')`.

بهذا تكون الروابط محددة حسب نوع المستخدم والبوابة.
