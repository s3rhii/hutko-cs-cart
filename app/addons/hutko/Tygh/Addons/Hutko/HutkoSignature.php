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

use InvalidArgumentException;

class HutkoSignature
{
    public const EXCLUDED_FIELDS = [
        'signature',
        'response_signature_string',
    ];

    public const ZERO_DECIMAL_CURRENCIES = [
        'BIF', 'CLP', 'DJF', 'GNF', 'ISK', 'JPY', 'KMF', 'KRW',
        'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
    ];

    public const THREE_DECIMAL_CURRENCIES = [
        'BHD', 'IQD', 'JOD', 'KWD', 'LYD', 'OMR', 'TND',
    ];

    public static function toMinorUnits($amount, string $currency): int
    {
        if (!is_numeric($amount) || (float) $amount <= 0) {
            throw new InvalidArgumentException('Payable order total must be a positive number.');
        }

        $currency = strtoupper(trim($currency));
        if ($currency === '' || strlen($currency) !== 3) {
            throw new InvalidArgumentException('Currency must be a valid 3-letter ISO-4217 code.');
        }

        $exponent = 2;
        if (in_array($currency, self::ZERO_DECIMAL_CURRENCIES, true)) {
            $exponent = 0;
        } elseif (in_array($currency, self::THREE_DECIMAL_CURRENCIES, true)) {
            $exponent = 3;
        }

        $multiplier = 10 ** $exponent;
        return (int) round(((float) $amount) * $multiplier, 0, PHP_ROUND_HALF_UP);
    }

    public static function generate(array $data, string $secretKey): string
    {
        $secretKey = trim($secretKey);
        if ($secretKey === '') {
            throw new InvalidArgumentException('Secret key must not be empty.');
        }

        foreach (self::EXCLUDED_FIELDS as $field) {
            unset($data[$field]);
        }

        $filtered = [];
        foreach ($data as $key => $value) {
            if (is_array($value) || is_object($value)) {
                throw new InvalidArgumentException(
                    sprintf('Malformed nested value in signed field "%s".', (string) $key)
                );
            }

            if ($value === null || $value === '') {
                continue;
            }

            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }

            $filtered[(string) $key] = (string) $value;
        }

        ksort($filtered, SORT_STRING);

        $parts = array_merge([$secretKey], array_values($filtered));
        return sha1(implode('|', $parts));
    }

    public static function verify(array $payload, string $secretKey): bool
    {
        if (empty($payload['signature']) || !is_string($payload['signature'])) {
            return false;
        }

        try {
            $expected = self::generate($payload, $secretKey);
        } catch (InvalidArgumentException $e) {
            return false;
        }

        return hash_equals($expected, strtolower(trim($payload['signature'])));
    }

    public static function extractOrderId(string $hutkoOrderId, string $delimiter = '_'): int
    {
        $hutkoOrderId = trim($hutkoOrderId);
        if ($hutkoOrderId === '') {
            return 0;
        }

        $parts = explode($delimiter, $hutkoOrderId);
        $rawOrderId = $parts[0] ?? '';

        if ($rawOrderId === '' || !ctype_digit((string) $rawOrderId)) {
            return 0;
        }

        return (int) $rawOrderId;
    }
}
