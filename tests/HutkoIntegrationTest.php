<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/addons/hutko/Tygh/Addons/Hutko/HutkoSignature.php';
require_once __DIR__ . '/../app/addons/hutko/Tygh/Addons/Hutko/HutkoCallbackProcessor.php';

use Tygh\Addons\Hutko\HutkoCallbackProcessor;
use Tygh\Addons\Hutko\HutkoSignature;

function assertTest($expected, $actual, string $label): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, sprintf("[FAIL] %s\n  Expected: %s\n  Actual:   %s\n", $label, var_export($expected, true), var_export($actual, true)));
        exit(1);
    }
    fwrite(STDOUT, sprintf("[PASS] %s\n", $label));
}

$processorParams = [
    'merchant_id'    => '1700001',
    'secret_key'     => 'test_secret_key_for_unit_tests',
    'pending_status' => 'O',
    'paid_status'    => 'P',
    'failed_status'  => 'F',
];

// 1. Signature generation & numeric zero preservation
$baseCallback = [
    'merchant_id'               => 1700001,
    'order_id'                  => '501_1700000100',
    'amount'                    => 25000,
    'currency'                  => 'UAH',
    'order_status'              => 'approved',
    'tran_type'                 => 'purchase',
    'payment_id'                => '99887766',
    'fee'                       => 0,
    'reversal_amount'           => '0',
    'empty_str'                 => '',
    'null_val'                  => null,
    'response_signature_string' => 'ignored_string',
];
$baseCallback['signature'] = HutkoSignature::generate($baseCallback, $processorParams['secret_key']);
assertTest(true, HutkoSignature::verify($baseCallback, $processorParams['secret_key']), '1. Valid signature with numeric 0 and string "0" preserved');

// 2. Approved payment callback transitions Pending (O) -> Paid (P)
$resApproved = HutkoCallbackProcessor::evaluate(
    $baseCallback,
    $processorParams,
    501,
    25000,
    'UAH',
    'O',
    ['hutko_attempt_order_id' => '501_1700000100']
);
assertTest(200, $resApproved['http_code'], '2a. Approved callback returns HTTP 200');
assertTest('update_status', $resApproved['action'], '2b. Approved callback triggers status update');
assertTest('P', $resApproved['new_status'], '2c. Approved callback sets status to Paid (P)');

// 3. Duplicate approved callback is idempotent no-op
$resDuplicate = HutkoCallbackProcessor::evaluate(
    $baseCallback,
    $processorParams,
    501,
    25000,
    'UAH',
    'P',
    $resApproved['payment_info']
);
assertTest(200, $resDuplicate['http_code'], '3a. Duplicate callback returns HTTP 200');
assertTest('noop', $resDuplicate['action'], '3b. Duplicate callback performs idempotent no-op');

// 4. Declined payment callback transitions Pending (O) -> Failed (F)
$declinedCallback = $baseCallback;
$declinedCallback['order_status'] = 'declined';
$declinedCallback['signature']    = HutkoSignature::generate($declinedCallback, $processorParams['secret_key']);
$resDeclined = HutkoCallbackProcessor::evaluate(
    $declinedCallback,
    $processorParams,
    501,
    25000,
    'UAH',
    'O',
    ['hutko_attempt_order_id' => '501_1700000100']
);
assertTest('F', $resDeclined['new_status'], '4. Declined callback sets status to Failed (F)');

// 5. Expired payment callback transitions Pending (O) -> Failed (F)
$expiredCallback = $baseCallback;
$expiredCallback['order_status'] = 'expired';
$expiredCallback['signature']    = HutkoSignature::generate($expiredCallback, $processorParams['secret_key']);
$resExpired = HutkoCallbackProcessor::evaluate(
    $expiredCallback,
    $processorParams,
    501,
    25000,
    'UAH',
    'O',
    ['hutko_attempt_order_id' => '501_1700000100']
);
assertTest('F', $resExpired['new_status'], '5. Expired callback sets status to Failed (F)');

// 6. Delayed failure from older superseded attempt is ignored
$olderFailed = $baseCallback;
$olderFailed['order_id']     = '501_1699999000';
$olderFailed['order_status'] = 'expired';
$olderFailed['signature']    = HutkoSignature::generate($olderFailed, $processorParams['secret_key']);
$resSuperseded = HutkoCallbackProcessor::evaluate(
    $olderFailed,
    $processorParams,
    501,
    25000,
    'UAH',
    'O',
    ['hutko_attempt_order_id' => '501_1700000100']
);
assertTest('noop', $resSuperseded['action'], '6. Delayed callback from older attempt is safely ignored');

// 7. Invalid signature rejected with HTTP 400
$badSig = $baseCallback;
$badSig['signature'] = sha1('wrong');
$resBadSig = HutkoCallbackProcessor::evaluate($badSig, $processorParams, 501, 25000, 'UAH', 'O', []);
assertTest(400, $resBadSig['http_code'], '7. Invalid signature rejected with HTTP 400');

// 8. Wrong Merchant ID rejected with HTTP 400
$wrongMerchant = $baseCallback;
$wrongMerchant['merchant_id'] = 9999999;
$wrongMerchant['signature']   = HutkoSignature::generate($wrongMerchant, $processorParams['secret_key']);
$resWrongMerchant = HutkoCallbackProcessor::evaluate($wrongMerchant, $processorParams, 501, 25000, 'UAH', 'O', []);
assertTest(400, $resWrongMerchant['http_code'], '8. Wrong Merchant ID rejected with HTTP 400');

// 9. Wrong Order ID rejected with HTTP 400
$wrongOrder = $baseCallback;
$wrongOrder['order_id']  = '999_1700000100';
$wrongOrder['signature'] = HutkoSignature::generate($wrongOrder, $processorParams['secret_key']);
$resWrongOrder = HutkoCallbackProcessor::evaluate($wrongOrder, $processorParams, 501, 25000, 'UAH', 'O', []);
assertTest(400, $resWrongOrder['http_code'], '9. Wrong Order ID rejected with HTTP 400');

// 10. Amount mismatch rejected with HTTP 400
$wrongAmount = $baseCallback;
$wrongAmount['amount']    = 100;
$wrongAmount['signature'] = HutkoSignature::generate($wrongAmount, $processorParams['secret_key']);
$resWrongAmount = HutkoCallbackProcessor::evaluate($wrongAmount, $processorParams, 501, 25000, 'UAH', 'O', []);
assertTest(400, $resWrongAmount['http_code'], '10. Amount mismatch rejected with HTTP 400');

// 11. Currency mismatch rejected with HTTP 400
$wrongCurr = $baseCallback;
$wrongCurr['currency']  = 'USD';
$wrongCurr['signature'] = HutkoSignature::generate($wrongCurr, $processorParams['secret_key']);
$resWrongCurr = HutkoCallbackProcessor::evaluate($wrongCurr, $processorParams, 501, 25000, 'UAH', 'O', []);
assertTest(400, $resWrongCurr['http_code'], '11. Currency mismatch rejected with HTTP 400');

fwrite(STDOUT, "\nAll 11 behavioral and security test suites passed!\n");