<?php

/*
 * Balance, catalogue, one order and its result — with the PHP client.
 *
 *   php example.php
 *
 * Fill in the three values below from Dashboard → API Settings first.
 */

require __DIR__ . '/../../clients/php/GsmXToolApi.php';

$api = new GsmXToolApi(
    'https://shop.example/api/index.php', // API URL
    'you@example.com',                    // your account's email
    'YOUR-API-ACCESS-KEY'                 // API access key
);

try {
    // 1. Balance
    $account = $api->accountInfo();
    echo "Balance: {$account['credit']} ({$account['currency']})\n";

    // 2. Catalogue — cache this on your side; it may be asked for once every 5 minutes.
    $list = $api->serviceList();
    foreach ($list['LIST'] as $group) {
        echo "\n{$group['GROUPNAME']} ({$group['GROUPTYPE']})\n";
        foreach ($group['SERVICES'] as $service) {
            printf("  #%-6s %-60s %8.2f %s\n", $service['SERVICEID'], $service['SERVICENAME'], $service['CREDIT'], $list['CURRENCY']);
        }
    }

    // 3. An order — use a SERVICEID from the list above.
    $reference = $api->placeOrder('1203', '356789012345678');
    echo "\nOrder placed: #{$reference}\n";

    // 4. Its result. In real use, ask again every few minutes until STATUS is 3 or 4.
    $status = $api->orderStatus($reference);
    $names  = [0 => 'Waiting', 1 => 'In process', 3 => 'Rejected', 4 => 'Success'];
    echo 'Status: ' . ($names[$status['STATUS']] ?? $status['STATUS']) . "\n";
    if ($status['CODE'] !== '') {
        echo "Result: {$status['CODE']}\n";
    }
} catch (GsmXToolApiException $e) {
    echo 'API said: ' . $e->getMessage() . "\n";
}
