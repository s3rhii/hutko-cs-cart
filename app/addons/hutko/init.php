<?php
/***************************************************************************
 * Community hutko Payment Gateway for CS-Cart
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

require_once __DIR__ . '/Tygh/Addons/Hutko/HutkoSignature.php';
require_once __DIR__ . '/Tygh/Addons/Hutko/HutkoCallbackProcessor.php';