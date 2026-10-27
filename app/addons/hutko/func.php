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

if (!defined('BOOTSTRAP')) {
    die('Access denied');
}

function fn_hutko_install(): void
{
    fn_hutko_uninstall();

    db_query('INSERT INTO ?:payment_processors ?e', [
        'processor'          => 'hutko Payment Gateway',
        'processor_script'   => 'hutko.php',
        'processor_template' => 'views/payments/components/cc_processors/hutko.tpl',
        'admin_template'     => 'hutko.tpl',
        'callback'           => 'Y',
        'type'               => 'P',
        'addon'              => 'hutko',
    ]);
}

function fn_hutko_uninstall(): void
{
    db_query('DELETE FROM ?:payment_processors WHERE processor_script = ?s', 'hutko.php');
}

function fn_hutko_redact_for_log(array $data): array
{
    $redactedKeys = [
        'secret_key',
        'signature',
        'response_signature_string',
        'rectoken',
        'receiver_rectoken',
        'card_number',
        'masked_card',
        'cvv2',
        'expiry_date',
        'sender_email',
        'sender_cell_phone',
        'sender_account',
        'rrn',
        'approval_code',
    ];

    $clean = [];
    foreach ($data as $key => $value) {
        if (in_array(strtolower((string) $key), $redactedKeys, true)) {
            $clean[$key] = '[REDACTED]';
        } elseif (is_array($value)) {
            $clean[$key] = fn_hutko_redact_for_log($value);
        } else {
            $clean[$key] = $value;
        }
    }

    return $clean;
}

function fn_hutko_log(array $processorParams, string $context, array $requestData, array $responseData): void
{
    if (($processorParams['logging'] ?? 'N') !== 'Y') {
        return;
    }

    if (function_exists('fn_log_event')) {
        fn_log_event('requests', 'http', [
            'url'      => $context,
            'data'     => json_encode(fn_hutko_redact_for_log($requestData), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'response' => json_encode(fn_hutko_redact_for_log($responseData), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }
}
