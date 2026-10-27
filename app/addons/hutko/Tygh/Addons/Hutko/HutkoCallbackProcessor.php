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

namespace Tygh\Addons\Hutko;

class HutkoCallbackProcessor
{
    public static function evaluate(
        array $payload,
        array $processorParams,
        int $expectedOrderId,
        int $expectedMinorTotal,
        string $expectedCurrency,
        string $currentOrderStatus,
        array $existingPaymentInfo
    ): array {
        $configuredMerchantId = trim((string) ($processorParams['merchant_id'] ?? ''));
        $secretKey            = trim((string) ($processorParams['secret_key'] ?? ''));
        $paidStatus           = !empty($processorParams['paid_status']) ? (string) $processorParams['paid_status'] : 'P';
        $pendingStatus        = !empty($processorParams['pending_status']) ? (string) $processorParams['pending_status'] : 'O';
        $failedStatus         = !empty($processorParams['failed_status']) ? (string) $processorParams['failed_status'] : 'F';

        if ($secretKey === '' || !HutkoSignature::verify($payload, $secretKey)) {
            return [
                'http_code'    => 400,
                'action'       => 'reject',
                'new_status'   => null,
                'notify_user'  => false,
                'message'      => 'Invalid hutko signature',
                'payment_info' => [],
            ];
        }

        $callbackMerchantId = trim((string) ($payload['merchant_id'] ?? ''));
        if ($configuredMerchantId === '' || $callbackMerchantId !== $configuredMerchantId) {
            return [
                'http_code'    => 400,
                'action'       => 'reject',
                'new_status'   => null,
                'notify_user'  => false,
                'message'      => 'Merchant ID mismatch',
                'payment_info' => [],
            ];
        }

        $hutkoOrderId = trim((string) ($payload['order_id'] ?? ''));
        $extractedId  = HutkoSignature::extractOrderId($hutkoOrderId, '_');
        if ($extractedId <= 0 || $extractedId !== $expectedOrderId) {
            return [
                'http_code'    => 400,
                'action'       => 'reject',
                'new_status'   => null,
                'notify_user'  => false,
                'message'      => 'Order ID mismatch',
                'payment_info' => [],
            ];
        }

        $callbackAmount   = isset($payload['amount']) && is_numeric($payload['amount']) ? (int) $payload['amount'] : -1;
        $callbackCurrency = strtoupper(trim((string) ($payload['currency'] ?? '')));
        $expectedCurrency = strtoupper(trim($expectedCurrency));

        if ($callbackAmount !== $expectedMinorTotal || $callbackCurrency !== $expectedCurrency) {
            return [
                'http_code'    => 400,
                'action'       => 'reject',
                'new_status'   => null,
                'notify_user'  => false,
                'message'      => 'Amount or currency mismatch',
                'payment_info' => [],
            ];
        }

        $tranType = strtolower(trim((string) ($payload['tran_type'] ?? 'purchase')));
        if (!in_array($tranType, ['purchase', 'capture', 'reverse'], true)) {
            return [
                'http_code'    => 400,
                'action'       => 'reject',
                'new_status'   => null,
                'notify_user'  => false,
                'message'      => 'Unsupported transaction type',
                'payment_info' => [],
            ];
        }

        $hutkoOrderStatus = strtolower(trim((string) ($payload['order_status'] ?? '')));
        $reversalAmount   = isset($payload['reversal_amount']) && is_numeric($payload['reversal_amount'])
            ? (int) $payload['reversal_amount']
            : 0;

        $alreadyPaid = ($currentOrderStatus === $paidStatus)
            || (($existingPaymentInfo['hutko_order_status'] ?? '') === 'approved');

        if ($alreadyPaid && $hutkoOrderStatus !== 'reversed') {
            return [
                'http_code'    => 200,
                'action'       => 'noop',
                'new_status'   => null,
                'notify_user'  => false,
                'message'      => 'OK (idempotent no-op)',
                'payment_info' => [],
            ];
        }

        $activeAttemptId = (string) ($existingPaymentInfo['hutko_attempt_order_id'] ?? '');
        if (
            !in_array($hutkoOrderStatus, ['approved', 'reversed'], true)
            && $activeAttemptId !== ''
            && $activeAttemptId !== $hutkoOrderId
        ) {
            return [
                'http_code'    => 200,
                'action'       => 'noop',
                'new_status'   => null,
                'notify_user'  => false,
                'message'      => 'OK (superseded attempt ignored)',
                'payment_info' => [],
            ];
        }

        $updatedInfo = [];
        foreach ($existingPaymentInfo as $k => $v) {
            if (is_scalar($v)) {
                $updatedInfo[(string) $k] = (string) $v;
            }
        }

        $updatedInfo['transaction_id']       = (string) ($payload['payment_id'] ?? '');
        $updatedInfo['hutko_order_id']       = $hutkoOrderId;
        $updatedInfo['hutko_order_status']   = $hutkoOrderStatus;
        $updatedInfo['hutko_response_code']  = (string) ($payload['response_code'] ?? '');
        $updatedInfo['hutko_payment_system'] = (string) ($payload['payment_system'] ?? '');

        if (!empty($payload['response_description'])) {
            $updatedInfo['reason_text'] = (string) $payload['response_description'];
        }

        if ($hutkoOrderStatus === 'approved' && $tranType !== 'reverse') {
            $updatedInfo['order_status'] = $paidStatus;
            return [
                'http_code'    => 200,
                'action'       => 'update_status',
                'new_status'   => $paidStatus,
                'notify_user'  => true,
                'message'      => 'OK (approved)',
                'payment_info' => $updatedInfo,
            ];
        }

        if ($hutkoOrderStatus === 'reversed' || ($reversalAmount > 0 && $reversalAmount >= $expectedMinorTotal)) {
            $updatedInfo['hutko_reversal_amount'] = (string) $reversalAmount;
            return [
                'http_code'    => 200,
                'action'       => 'update_info_only',
                'new_status'   => null,
                'notify_user'  => false,
                'message'      => 'OK (reversed recorded)',
                'payment_info' => $updatedInfo,
            ];
        }

        if (in_array($hutkoOrderStatus, ['created', 'processing'], true)) {
            $updatedInfo['order_status'] = $pendingStatus;
            $newStatus = ($currentOrderStatus === 'N') ? $pendingStatus : null;
            return [
                'http_code'    => 200,
                'action'       => $newStatus !== null ? 'update_status' : 'update_info_only',
                'new_status'   => $newStatus,
                'notify_user'  => false,
                'message'      => 'OK (pending)',
                'payment_info' => $updatedInfo,
            ];
        }

        if (in_array($hutkoOrderStatus, ['declined', 'expired', 'cancelled'], true)) {
            $updatedInfo['order_status'] = $failedStatus;
            $newStatus = ($currentOrderStatus !== $failedStatus) ? $failedStatus : null;
            return [
                'http_code'    => 200,
                'action'       => $newStatus !== null ? 'update_status' : 'update_info_only',
                'new_status'   => $newStatus,
                'notify_user'  => false,
                'message'      => 'OK (failed/expired)',
                'payment_info' => $updatedInfo,
            ];
        }

        return [
            'http_code'    => 400,
            'action'       => 'reject',
            'new_status'   => null,
            'notify_user'  => false,
            'message'      => 'Unrecognized order_status',
            'payment_info' => [],
        ];
    }
}
