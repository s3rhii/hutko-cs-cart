hutko Payment Gateway for CS-Cart

## English

This is an independent third-party payment integration for hutko and CS-Cart. It is not affiliated with, endorsed by, certified by or officially supported by hutko. It was developed using publicly available hutko API documentation. For merchant-account or transaction-support questions, contact hutko directly.

### Features

- Hosted checkout through hutko.
- Signed requests to the hutko checkout API.
- Server callback validation with Merchant ID, order ID, amount, currency and payment status checks.
- Separate customer return and server callback handling.
- Pending, approved, declined, expired and cancelled payment status handling.
- Duplicate and delayed callback protection.

### Requirements

- CS-Cart 4.10.1 or newer.
- PHP 7.4 or newer.
- HTTPS for the storefront and callback URL.
- A hutko merchant account.
- hutko `Merchant ID` and `Secret key`.

The listed CS-Cart and PHP versions are the target compatibility range for this release. Verify compatibility with your exact CS-Cart installation before production use.

### Installation

1. Upload `dist/hutko-cs-cart-v1.0.0.zip` in CS-Cart Admin: **Add-ons → Manage add-ons → Upload & install add-on**.

   Alternatively, copy the `app/`, `design/` and `var/` directories into the CS-Cart root directory while preserving their structure.

2. Open **Administration → Payment methods**.

3. Create a payment method and select **Community hutko Payment Gateway**.

4. Enter the hutko `Merchant ID` and `Secret key`.

5. Select the pending, paid and failed order statuses required by your store.

6. Save the payment method and enable it for the required storefront and customer groups.

### Payment flow

1. CS-Cart creates the order in a pending state.
2. The plugin sends the final order amount and currency to hutko in a signed HTTPS request.
3. The customer is redirected to the hosted hutko checkout page.
4. hutko sends a server callback to CS-Cart after the payment attempt.
5. The plugin validates the signature, Merchant ID, order ID, amount, currency and payment status.
6. The order status is updated only after a valid callback.

The customer return URL is used for navigation and displaying the result. It is not treated as independent proof of payment.

### Security

- Keep the `Secret key` on the server and do not expose it in frontend code.
- Do not mark an order as paid based only on the browser return URL.
- Do not change payment amounts or currencies in callback data.
- Do not share logs containing credentials, tokens or personal data.

### Current limitations

This release currently provides the hosted checkout flow. Hold/capture, refunds, recurring payments, rectoken payments, embedded checkout and fiscalization are not enabled unless separately implemented and verified for this CS-Cart integration.

The plugin is not an official hutko module. Its operation depends on the public hutko API, CS-Cart behavior and the merchant's configuration. Test the complete payment and callback flow before using it in production.

### Support

For plugin bugs or installation issues, contact the independent developer who published this plugin. For merchant-account, terminal activation or transaction-support questions, contact hutko directly.

## Українська версія

Це незалежна стороння платіжна інтеграція hutko з CS-Cart. Плагін не пов'язаний із hutko, не розробляється та не підтримується hutko, а також не є офіційно схваленим або сертифікованим продуктом hutko. Плагін створено на основі загальнодоступної документації API hutko. З питань мерчант-акаунта або конкретної транзакції звертайтеся безпосередньо до hutko.

### Можливості

- Оплата через hosted checkout hutko.
- Підписані запити до API платіжної сторінки hutko.
- Перевірка server callback за Merchant ID, номером замовлення, сумою, валютою та статусом платежу.
- Окреме опрацювання повернення покупця та server callback.
- Обробка статусів очікування, успішної, відхиленої, простроченої та скасованої оплати.
- Захист від повторних і запізнілих callback.

### Вимоги

- CS-Cart версії 4.10.1 або новішої.
- PHP версії 7.4 або новішої.
- HTTPS для сайту та адреси server callback.
- Активований мерчант-акаунт hutko.
- `Merchant ID` і `Secret key` hutko.

Зазначені версії CS-Cart і PHP є цільовим діапазоном сумісності цього релізу. Перед використанням у production перевірте роботу плагіна на вашій конкретній версії CS-Cart.

### Встановлення

1. Завантажте `dist/hutko-cs-cart-v1.0.0.zip` в адмінпанелі CS-Cart: **Додатки → Керування додатками → Завантажити та встановити додаток**.

   Також можна скопіювати каталоги `app/`, `design/` і `var/` до кореневого каталогу CS-Cart зі збереженням структури файлів.

2. Відкрийте **Адміністрування → Способи оплати**.

3. Створіть спосіб оплати та виберіть **Community hutko Payment Gateway**.

4. Введіть `Merchant ID` і `Secret key` hutko.

5. Виберіть статуси замовлення для очікування, успішної та невдалої оплати.

6. Збережіть спосіб оплати та активуйте його для потрібної вітрини й груп покупців.

### Схема оплати

1. CS-Cart створює замовлення зі статусом очікування оплати.
2. Плагін надсилає фінальну суму та валюту замовлення до hutko підписаним HTTPS-запитом.
3. Покупець переходить на hosted checkout hutko.
4. Після спроби оплати hutko надсилає server callback до CS-Cart.
5. Плагін перевіряє підпис, Merchant ID, номер замовлення, суму, валюту та статус платежу.
6. Статус замовлення змінюється лише після коректного callback.

Адреса повернення покупця використовується для навігації та відображення результату. Вона не є самостійним підтвердженням оплати.

### Безпека

- Зберігайте `Secret key` лише на сервері та не передавайте його у frontend-код.
- Не позначайте замовлення як оплачене лише після переходу покупця за return URL.
- Не приймайте суму або валюту з callback без перевірки їхньої відповідності замовленню.
- Не передавайте в логи облікові дані, токени або персональні дані.

### Поточні обмеження

Цей реліз реалізує hosted checkout. Холдування та capture, повернення коштів, рекурентні платежі, платежі через rectoken, embedded checkout і фіскалізація не активовані, якщо їх окремо не реалізовано та не перевірено саме для цієї інтеграції з CS-Cart.

Плагін не є офіційним модулем hutko. Його робота залежить від публічного API hutko, поведінки CS-Cart і налаштувань мерчанта. Перед використанням у production перевірте повний платіжний сценарій і доставлення callback.

### Підтримка

З питань помилок плагіна або його встановлення звертайтеся до незалежного розробника, який опублікував цей плагін. З питань мерчант-акаунта, активації терміналів або конкретних транзакцій звертайтеся безпосередньо до hutko.

### Публічні джерела

- hutko API documentation: https://docs.hutko.org/
- hutko integration repositories: https://github.com/hutko-service/
