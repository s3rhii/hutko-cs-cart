<?php
/***************************************************************************
 * hutko Payment Gateway for CS-Cart
 *
 * DISCLAIMER:
 * This is an independent third-party payment integration for hutko and CS-Cart.
 * It is not affiliated with, endorsed by or officially supported by hutko.
 * It was developed using publicly available hutko API documentation.
 * For merchant-account or transaction-support questions, contact hutko directly.
 ***************************************************************************/

use Tygh\Addons\Hutko\HutkoCallbackProcessor;
use Tygh\Addons\Hutko\HutkoSignature;
use Tygh\Http;

if (!defined('BOOTSTRAP')) {
    die('Access denied');
}

if (!class_exists(HutkoSignature::class)) {
    require_once dirname(__DIR__) . '/addons/hutko/Tygh/Addons/Hutko/HutkoSignature.php';
}
if (!class_exists(HutkoCallbackProcessor::class)) {
    require_once dirname(__DIR__) . '/addons/hutko/Tygh/Addons/Hutko/HutkoCallbackProcessor.php';
}

const HUTKO_CHECKOUT_URL_ENDPOINT = 'https://pay.hutko.org/api/checkout/url';
const HUTKO_ORDER_DELIMITER       = '_';

if (defined('PAYMENT_NOTIFICATION')) {
    $mode = $_REQUEST['mode'] ?? '';

    if ($mode === 'callback') {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('HTTP/1.1 405 Method Not Allowed');
            exit('Method Not Allowed');
        }

        $rawBody = (string) file_get_contents('php://input');
        $payload = [];

        if ($rawBody !== '') {
            $decoded = json_decode($rawBody, true);
            if (is_array($decoded)) {
                $payload = (isset($decoded['response']) && is_array($decoded['response']))
                    ? $decoded['response']
                    : $decoded;
            }
        }

        if (empty($payload) && !empty($_POST) && is_array($_POST)) {
            $payload = $_POST;
        }

        if (empty($payload) || !is_array($payload)) {
            header('HTTP/1.1 400 Bad Request');
            exit('Malformed callback payload');
        }

        $hutkoOrderId = (string) ($payload['order_id'] ?? '');
        $orderId      = HutkoSignature::extractOrderId($hutkoOrderId, HUTKO_ORDER_DELIMITER);

        if ($orderId <= 0 || !fn_check_payment_script('hutko.php', $orderId, $processorData)) {
            header('HTTP/1.1 400 Bad Request');
            exit('Unknown or invalid order');
        }

        $processorParams = $processorData['processor_params'] ?? [];

        db_query('START TRANSACTION');
        $lockedStatus = db_get_field('SELECT status FROM ?:orders WHERE order_id = ?i FOR UPDATE', $orderId);

        if (empty($lockedStatus)) {
            db_query('ROLLBACK');
            header('HTTP/1.1 404 Not Found');
            exit('Order not found');
        }

        $orderInfo = fn_get_order_info($orderId, false, true, true, false);
        if (empty($orderInfo)) {
            db_query('ROLLBACK');
            header('HTTP/1.1 404 Not Found');
            exit('Order info unavailable');
        }

        $expectedCurrency = !empty($processorParams['currency'])
            ? strtoupper(trim((string) $processorParams['currency']))
            : strtoupper((string) ($orderInfo['secondary_currency'] ?? CART_PRIMARY_CURRENCY));

        $expectedTotal = $orderInfo['total'];
        if ($expectedCurrency !== CART_PRIMARY_CURRENCY && function_exists('fn_format_price_by_currency')) {
            $expectedTotal = fn_format_price_by_currency($orderInfo['total'], CART_PRIMARY_CURRENCY, $expectedCurrency);
        }

        try {
            $expectedMinorTotal = HutkoSignature::toMinorUnits($expectedTotal, $expectedCurrency);
        } catch (InvalidArgumentException $e) {
            db_query('ROLLBACK');
            header('HTTP/1.1 400 Bad Request');
            exit('Invalid stored order total');
        }

        $existingPaymentInfo = is_array($orderInfo['payment_info'] ?? null) ? $orderInfo['payment_info'] : [];

        $decision = HutkoCallbackProcessor::evaluate(
            $payload,
            $processorParams,
            $orderId,
            $expectedMinorTotal,
            $expectedCurrency,
            (string) $lockedStatus,
            $existingPaymentInfo
        );

        fn_hutko_log($processorParams, 'callback:hutko', $payload, [
            'http_code' => $decision['http_code'],
            'action'    => $decision['action'],
            'message'   => $decision['message'],
        ]);

        if ($decision['http_code'] !== 200) {
            db_query('ROLLBACK');
            header('HTTP/1.1 ' . $decision['http_code'] . ' Bad Request');
            exit($decision['message']);
        }

        if ($decision['action'] === 'update_status' && !empty($decision['new_status'])) {
            fn_update_order_payment_info($orderId, $decision['payment_info']);
            fn_change_order_status(
                $orderId,
                $decision['new_status'],
                '',
                fn_get_notification_rules(['notify_user' => $decision['notify_user']])
            );
        } elseif ($decision['action'] === 'update_info_only') {
            fn_update_order_payment_info($orderId, $decision['payment_info']);
        }

        db_query('COMMIT');
        header('HTTP/1.1 200 OK');
        exit($decision['message']);
    }

    if ($mode === 'return') {
        $rawOrderId = (string) ($_REQUEST['order_id'] ?? '');
        $orderId    = HutkoSignature::extractOrderId($rawOrderId, HUTKO_ORDER_DELIMITER);

        if ($orderId <= 0 || !fn_check_payment_script('hutko.php', $orderId, $processorData)) {
            fn_set_notification('E', __('error'), __('hutko.return_order_not_found'));
            fn_redirect(fn_url('checkout.cart'));
        }

        $processorParams = $processorData['processor_params'] ?? [];
        $paidStatus      = !empty($processorParams['paid_status']) ? (string) $processorParams['paid_status'] : 'P';
        $pendingStatus   = !empty($processorParams['pending_status']) ? (string) $processorParams['pending_status'] : 'O';

        $orderInfo = fn_get_order_info($orderId);
        if (empty($orderInfo)) {
            fn_redirect(fn_url('checkout.cart'));
        }

        $currentStatus = (string) $orderInfo['status'];

        if ($currentStatus === STATUS_INCOMPLETED_ORDER) {
            fn_change_order_status($orderId, $pendingStatus, '', fn_get_notification_rules(['notify_user' => false]));
            $currentStatus = $pendingStatus;
        }

        if ($currentStatus === $paidStatus) {
            fn_order_placement_routines('route', $orderId, false);
        } elseif ($currentStatus === $pendingStatus) {
            fn_set_notification('W', __('important'), __('hutko.payment_pending_confirmation'));
            fn_order_placement_routines('route', $orderId, false);
        } else {
            fn_set_notification('E', __('error'), __('text_transaction_declined'));
            fn_order_placement_routines('route', $orderId, false);
        }
        exit;
    }

    exit;
}

$processorParams = $processor_data['processor_params'] ?? [];
$merchantId      = trim((string) ($processorParams['merchant_id'] ?? ''));
$secretKey       = trim((string) ($processorParams['secret_key'] ?? ''));
$pendingStatus   = !empty($processorParams['pending_status']) ? (string) $processorParams['pending_status'] : 'O';

if ($merchantId === '' || $secretKey === '' || !ctype_digit($merchantId)) {
    $pp_response = [
        'order_status' => 'F',
        'reason_text'  => __('hutko.error_missing_credentials'),
    ];
    return;
}

$currency = !empty($processorParams['currency'])
    ? strtoupper(trim((string) $processorParams['currency']))
    : strtoupper((string) ($order_info['secondary_currency'] ?? CART_PRIMARY_CURRENCY));

$payableTotal = $order_info['total'];
if ($currency !== CART_PRIMARY_CURRENCY && function_exists('fn_format_price_by_currency')) {
    $payableTotal = fn_format_price_by_currency($order_info['total'], CART_PRIMARY_CURRENCY, $currency);
}

try {
    $minorAmount = HutkoSignature::toMinorUnits($payableTotal, $currency);
} catch (InvalidArgumentException $e) {
    $pp_response = [
        'order_status' => 'F',
        'reason_text'  => $e->getMessage(),
    ];
    return;
}

$attemptOrderId = $order_id . HUTKO_ORDER_DELIMITER . time();

$returnUrl   = fn_url("payment_notification.return?payment=hutko&order_id={$order_id}", AREA, 'current');
$callbackUrl = fn_url('payment_notification.callback?payment=hutko', AREA, 'current');

$langCode       = strtolower((string) CART_LANGUAGE);
$supportedLangs = ['uk', 'en', 'ru', 'pl', 'de', 'fr', 'es', 'it', 'cs', 'lv', 'lt', 'et', 'ro', 'sk'];
if (!in_array($langCode, $supportedLangs, true)) {
    $langCode = 'uk';
}

$requestPayload = [
    'order_id'            => $attemptOrderId,
    'merchant_id'         => (int) $merchantId,
    'order_desc'          => sprintf('%s #%s', __('order'), $order_id),
    'amount'              => $minorAmount,
    'currency'            => $currency,
    'response_url'        => $returnUrl,
    'server_callback_url' => $callbackUrl,
    'lang'                => $langCode,
];

if (!empty($order_info['email'])) {
    $requestPayload['sender_email'] = trim((string) $order_info['email']);
}

$requestPayload['signature'] = HutkoSignature::generate($requestPayload, $secretKey);

fn_update_order_payment_info($order_id, [
    'hutko_attempt_order_id' => $attemptOrderId,
    'hutko_order_status'     => 'created',
]);

if (($order_info['status'] ?? STATUS_INCOMPLETED_ORDER) === STATUS_INCOMPLETED_ORDER) {
    fn_change_order_status($order_id, $pendingStatus, '', fn_get_notification_rules(['notify_user' => false]));
}

$rawResponse = Http::post(
    HUTKO_CHECKOUT_URL_ENDPOINT,
    json_encode(['request' => $requestPayload], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    [
        'headers' => [
            'Content-Type: application/json; charset=utf-8',
            'Accept: application/json',
        ],
        'timeout' => 15,
    ]
);

$httpStatus      = Http::getStatus();
$decodedResponse = json_decode((string) $rawResponse, true);
$responseData    = is_array($decodedResponse['response'] ?? null) ? $decodedResponse['response'] : [];

fn_hutko_log($processorParams, HUTKO_CHECKOUT_URL_ENDPOINT, $requestPayload, $responseData);

if ($httpStatus !== 200 || empty($responseData) || ($responseData['response_status'] ?? '') !== 'success' || empty($responseData['checkout_url'])) {
    $errorMsg = (string) ($responseData['error_message'] ?? __('hutko.error_invalid_gateway_response'));
    $pp_response = [
        'order_status' => 'F',
        'reason_text'  => $errorMsg,
    ];
    return;
}

$checkoutUrl = (string) $responseData['checkout_url'];
$parsedUrl   = parse_url($checkoutUrl);
if (empty($parsedUrl['scheme']) || strtolower($parsedUrl['scheme']) !== 'https') {
    $pp_response = [
        'order_status' => 'F',
        'reason_text'  => __('hutko.error_invalid_checkout_url'),
    ];
    return;
}

fn_redirect($checkoutUrl, true);
exit;
