<?php

/**
 * A small client for the GSM X TOOL reseller API (Dhru Fusion standard v6.1).
 *
 *   $api = new GsmXToolApi('https://shop.example/api/index.php', 'you@example.com', 'YOUR-API-KEY');
 *   $api->accountInfo();
 *   $api->serviceList();
 *   $api->placeOrder('1203', '356789012345678');
 *   $api->orderStatus(98213);
 *
 * Every method returns the decoded reply. A refusal throws GsmXToolApiException
 * with the shop's own message, so a call either gives you what you asked for
 * or tells you why not.
 *
 * Needs PHP 7.4+ with the cURL and JSON extensions.
 */
class GsmXToolApi
{
    private string $url;
    private string $username;
    private string $key;
    private int $timeout;

    public function __construct(string $url, string $username, string $key, int $timeout = 60)
    {
        $this->url      = $url;
        $this->username = $username;
        $this->key      = $key;
        $this->timeout  = $timeout;
    }

    /** Balance and currency: ['credit' => '$12.00', 'creditraw' => 12.0, 'mail' => …, 'currency' => 'USD']. */
    public function accountInfo(): array
    {
        $reply = $this->call('accountinfo');

        return $reply['SUCCESS'][0]['AccountInfo'] ?? $reply['SUCCESS'][0]['AccoutInfo'] ?? [];
    }

    /**
     * Every service on sale, grouped: ['Group name' => ['GROUPNAME' => …, 'SERVICES' => [...]]].
     * At most once every 5 minutes per account — cache it.
     */
    public function serviceList(): array
    {
        return $this->call('imeiservicelist')['SUCCESS'][0] ?? [];
    }

    /**
     * Place one order and return its reference id.
     *
     * @param string     $serviceId    The service's SERVICEID from serviceList()
     * @param string|null $imei        The main box (IMEI services)
     * @param array      $customFields ['Field name' => 'value'] for Requires.Custom
     * @param int|null   $quantity     For services ordered by quantity
     */
    public function placeOrder(string $serviceId, ?string $imei = null, array $customFields = [], ?int $quantity = null): int
    {
        $parameters = ['ID' => $serviceId];

        if ($imei !== null) {
            $parameters['IMEI'] = $imei;
        }
        if ($quantity !== null) {
            $parameters['QNT'] = $quantity;
        }
        if ($customFields) {
            $parameters['CUSTOMFIELD'] = base64_encode(json_encode($customFields));
        }

        return (int) $this->call('placeimeiorder', $parameters)['SUCCESS'][0]['REFERENCEID'];
    }

    /**
     * Up to 100 orders at once. $orders is ['your-ref' => ['ID' => …, 'IMEI' => …], …].
     * Returns, per ref, ['status' => 'success'|'error', 'message' => …, 'referenceid' => …].
     */
    public function placeOrders(array $orders): array
    {
        return $this->call('placebulkorder', $orders)['SUCCESS'] ?? [];
    }

    /**
     * One order's state: ['STATUS' => 0|1|3|4, 'CODE' => result or reason, …].
     * 0 waiting, 1 in process, 3 rejected, 4 success.
     */
    public function orderStatus(int $referenceId): array
    {
        return $this->call('getimeiorder', ['ID' => $referenceId])['SUCCESS'][0] ?? [];
    }

    /**
     * Up to 100 orders at once. $referenceIds is ['your-ref' => 98213, …].
     * Returns, per ref, either ['SUCCESS' => [[STATUS, CODE…]]] or ['ERROR' => [['MESSAGE' => …]]].
     */
    public function orderStatuses(array $referenceIds): array
    {
        $parameters = [];
        foreach ($referenceIds as $ref => $id) {
            $parameters[$ref] = ['ID' => $id];
        }

        $reply = $this->call('getimeiorderbulk', $parameters);
        unset($reply['apiversion']);

        return $reply;
    }

    /** Send one action and return the decoded reply; a refusal throws. */
    public function call(string $action, array $parameters = []): array
    {
        $post = [
            'username'      => $this->username,
            'apiaccesskey'  => $this->key,
            'action'        => $action,
            'requestformat' => 'JSON',
        ];

        if ($parameters) {
            $post['parameters'] = base64_encode(json_encode($parameters));
        }

        $ch = curl_init($this->url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($post),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);

        $body  = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new GsmXToolApiException('Could not reach the API: ' . $error);
        }

        $reply = json_decode($body, true);

        if (! is_array($reply)) {
            throw new GsmXToolApiException('The API did not answer with JSON.');
        }

        if (isset($reply['ERROR'][0]['MESSAGE'])) {
            throw new GsmXToolApiException($reply['ERROR'][0]['MESSAGE']);
        }

        return $reply;
    }
}

class GsmXToolApiException extends RuntimeException
{
}
