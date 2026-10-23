# GSM X TOOL — Reseller API

The API every GSM X TOOL shop exposes to its customers, so a reseller's own
site can sync the catalogue, check its balance, place orders and collect
results automatically.

It follows the **Dhru Fusion API standard (v6.1)**, so any client built for
Dhru Fusion — including Dhru Fusion's own *GSM X TOOL* API module — connects
without changes.

- [Endpoint](#endpoint)
- [Authentication](#authentication)
- [Request format](#request-format)
- [Responses](#responses)
- [Actions](#actions)
  - [accountinfo](#accountinfo)
  - [imeiservicelist](#imeiservicelist)
  - [placeimeiorder](#placeimeiorder)
  - [placeimeiorderbulk](#placeimeiorderbulk)
  - [placebulkorder](#placebulkorder)
  - [getimeiorder](#getimeiorder)
  - [getimeiorderbulk](#getimeiorderbulk)
- [Order status codes](#order-status-codes)
- [Error messages](#error-messages)
- [Limits](#limits)
- [Client library and examples](#client-library-and-examples)

---

## Endpoint

```
POST https://<shop-domain>/api/index.php
```

`<shop-domain>` is the domain of the GSM X TOOL shop you buy from. The exact
URL is shown in your account under **Dashboard → API Settings → API URL**.

`GET` is accepted too, but send `POST` — your access key should not travel in
a URL.

## Authentication

Every request carries three fields:

| Field          | Value                                                                  |
| -------------- | ---------------------------------------------------------------------- |
| `username`     | Your account's email address                                           |
| `apiaccesskey` | Your API access key (**Dashboard → API Settings → API Access Key**)    |
| `action`       | The action to run (see [Actions](#actions))                            |

### IP lock

A key is tied to the **first IP address that uses it**. Requests from any
other address are refused with `Unauthorized IP address`.

Moving your site to a new server? Press **Reset IP Address** under
Dashboard → API Settings; the next request locks the key to the new address.
**Regenerate Key** issues a new key and stops the old one at once.

## Request format

Send an ordinary form post (`application/x-www-form-urlencoded` or
`multipart/form-data`):

| Field           | Required | Notes                                                                          |
| --------------- | -------- | ------------------------------------------------------------------------------ |
| `username`      | yes      |                                                                                |
| `apiaccesskey`  | yes      |                                                                                |
| `action`        | yes      | Case does not matter                                                           |
| `requestformat` | no       | `JSON` (replies are always JSON)                                               |
| `parameters`    | per action | **base64 of a JSON object**. The older Dhru `<PARAMETERS>` XML block is also read |

Parameter names are case-insensitive (`ID`, `id` and `Id` are the same).

```php
$parameters = base64_encode(json_encode(['ID' => '123']));
```

## Responses

Every reply is **HTTP 200 with a JSON body** — a refused request included.
Read the body, never the status code.

Success:

```json
{
  "SUCCESS": [ { "MESSAGE": "..." } ],
  "apiversion": "6.1"
}
```

Failure:

```json
{
  "ERROR": [ { "MESSAGE": "Insufficient balance" } ],
  "apiversion": "6.1"
}
```

---

## Actions

### accountinfo

Your balance and currency.

**Parameters:** none

```json
{
  "SUCCESS": [
    {
      "message": "Your Account Info",
      "MESSAGE": "Your Account Info",
      "AccoutInfo":  { "credit": "$125.50", "creditraw": 125.5, "mail": "you@example.com", "currency": "USD" },
      "AccountInfo": { "credit": "$125.50", "creditraw": 125.5, "mail": "you@example.com", "currency": "USD" }
    }
  ],
  "apiversion": "6.1"
}
```

| Field       | Meaning                                                   |
| ----------- | --------------------------------------------------------- |
| `credit`    | Balance, formatted with the currency symbol               |
| `creditraw` | Balance as a number — use this one in code                |
| `currency`  | The currency every amount on your account is counted in   |

`AccoutInfo` (sic) is the Dhru standard's own spelling; `AccountInfo` carries
the same object.

### imeiservicelist

Every service on sale to you, grouped, with your price and the fields each
order needs.

**Parameters:** none — at most once every **5 minutes** per account (see
[Limits](#limits)).

```json
{
  "SUCCESS": [
    {
      "MESSAGE": "IMEI Service List",
      "CURRENCY": "USD",
      "ACCOUNTINFO": { "credit": "$125.50", "creditraw": 125.5, "mail": "you@example.com", "currency": "USD" },
      "LIST": {
        "Samsung FRP": {
          "GROUPNAME": "Samsung FRP",
          "GROUPTYPE": "IMEI",
          "SERVICES": {
            "1203": {
              "SERVICEID": "1203",
              "SERVICETYPE": "IMEI",
              "SERVER": "0",
              "SERVICENAME": "Samsung FRP Remove — All Models",
              "CREDIT": 4.5,
              "TIME": "1-30 Minutes",
              "QNT": 0,
              "MINQNT": null,
              "MAXQNT": null,
              "QNTOPTIONS": "",
              "INFO": "",
              "CUSTOM": {
                "allow": "1", "bulk": "0", "customname": "IMEI", "custominfo": "15 digits",
                "customlen": "1", "maxlength": "300", "regex": "", "isalpha": "1"
              },
              "Requires.Custom": [
                {
                  "type": "serviceimei", "fieldname": "Model", "fieldtype": "dropdown",
                  "description": "Pick the phone model", "fieldoptions": "S21,S22,S23",
                  "regexpr": "", "adminonly": "", "required": "on"
                }
              ]
            }
          }
        }
      }
    }
  ],
  "apiversion": "6.1"
}
```

| Field             | Meaning                                                                                   |
| ----------------- | ----------------------------------------------------------------------------------------- |
| `SERVICEID`       | **The id to order with** (send it as `ID` in `placeimeiorder`)                             |
| `SERVICETYPE`     | `IMEI`, `SERVER` or `REMOTE`                                                               |
| `SERVER`          | Same as the type, as Dhru numbers it: `0` IMEI, `1` SERVER, `2` REMOTE                    |
| `CREDIT`          | Your price for one, in `CURRENCY` (your account's currency)                               |
| `TIME`            | Delivery time, as the shop writes it                                                       |
| `QNT`             | `1` when the service is ordered by quantity (server credits); otherwise one per order      |
| `MINQNT`/`MAXQNT` | Quantity limits, when `QNT` is `1`                                                        |
| `QNTOPTIONS`      | Comma-separated fixed amounts, when the service is sold only in those                     |
| `CUSTOM`          | On IMEI services: the name of the main box — send its value in `IMEI`                     |
| `Requires.Custom` | The other fields an order needs — send them in `CUSTOMFIELD` by `fieldname`               |

`fieldtype` is one of Dhru's names (`text`, `dropdown`, `radio`, …); for
`dropdown` and `radio`, `fieldoptions` lists the choices separated by commas.

> `SERVICES` is keyed by `SERVICEID`, as the Dhru standard keys it. In the
> rare case two services in one group share a `SERVICEID`, the second is
> keyed `row-<n>` instead, so neither hides the other — order with the
> `SERVICEID` field and both cases are covered.

### placeimeiorder

Place one order. The price is taken from your balance at once.

**Parameters**

| Name          | Required | Notes                                                                                 |
| ------------- | -------- | ------------------------------------------------------------------------------------- |
| `ID`          | yes      | The service's `SERVICEID`                                                             |
| `IMEI`        | IMEI services | The value for the service's main box (`CUSTOM.customname`)                      |
| `QNT`         | when `QNT` is `1` | How many. Must respect `MINQNT`/`MAXQNT` or be one of `QNTOPTIONS`         |
| `CUSTOMFIELD` | when the service has `Requires.Custom` | base64 of a JSON object: `{ "<fieldname>": "<value>" }` |

Field names in `CUSTOMFIELD` are matched without regard to case, and a space
and an underscore count as the same (`Serial Number` = `serial_number`).

```json
{ "ID": "1203", "IMEI": "356789012345678",
  "CUSTOMFIELD": "eyJNb2RlbCI6IlMyMyJ9" }
```

Reply:

```json
{
  "SUCCESS": [ { "MESSAGE": "Order received", "REFERENCEID": 98213 } ],
  "apiversion": "6.1"
}
```

Keep `REFERENCEID` — it is how you ask for the result.

### placeimeiorderbulk

Up to **100 orders in one request**, answered in Dhru's shape. Each order is
placed on its own: one that fails does not stop the rest.

**Parameters:** base64 of a JSON object whose keys are **your own references**
and whose values are `placeimeiorder` parameter objects.

```json
{
  "a1": { "ID": "1203", "IMEI": "356789012345678" },
  "a2": { "ID": "1203", "IMEI": "356789012345679" }
}
```

Reply — one entry per reference:

```json
{
  "a1": { "SUCCESS": [ { "MESSAGE": "Order received", "REFERENCEID": 98214 } ] },
  "a2": { "ERROR":   [ { "MESSAGE": "Insufficient balance" } ] },
  "apiversion": "6.1"
}
```

### placebulkorder

The same as `placeimeiorderbulk`, with a flatter reply:

```json
{
  "SUCCESS": {
    "a1": { "status": "success", "message": "Order received", "referenceid": 98214 },
    "a2": { "status": "error",   "message": "Insufficient balance" }
  },
  "apiversion": "6.1"
}
```

### getimeiorder

One order's status and result.

**Parameters:** `{ "ID": <REFERENCEID> }`

```json
{
  "ID": "98213",
  "SUCCESS": [
    { "IMEI": "", "STATUS": 4, "CODE": "Unlock code: 12345678", "COMMENTS": "" }
  ],
  "apiversion": "6.1"
}
```

`STATUS` is a number — see [Order status codes](#order-status-codes). `CODE`
holds the result (the code, the reply or the reason for a rejection).

Only your own orders can be read.

### getimeiorderbulk

Up to 100 orders at once.

**Parameters:** base64 of `{ "<your ref>": { "ID": <REFERENCEID> }, … }`

```json
{
  "x1": { "SUCCESS": [ { "IMEI": "", "STATUS": 4, "CODE": "Done", "COMMENTS": "" } ] },
  "x2": { "ERROR":   [ { "MESSAGE": "Order ID not found!" } ] },
  "apiversion": "6.1"
}
```

---

## Order status codes

| `STATUS` | Meaning                      | What to do                               |
| -------- | ---------------------------- | ---------------------------------------- |
| `0`      | Waiting — not started yet    | Ask again later                          |
| `1`      | In process                   | Ask again later                          |
| `3`      | Rejected                     | Final. The reason is in `CODE`           |
| `4`      | Success                      | Final. The result is in `CODE`           |

Poll an order every few minutes until it reaches `3` or `4`.

## Error messages

Every refusal arrives as `ERROR[0].MESSAGE`.

| Message                                                        | Cause                                                        |
| -------------------------------------------------------------- | ------------------------------------------------------------ |
| `Authentication Failed`                                        | `username`, `apiaccesskey` or `action` missing                |
| `Invalid API access key or username`                           | The key does not exist, or belongs to another email           |
| `API access is disabled for this account`                      | The shop has switched your API access off                     |
| `This account is not active`                                   | Your account is suspended                                     |
| `Unauthorized IP address`                                      | Request from an IP other than the locked one ([IP lock](#ip-lock)) |
| `Invalid Action`                                               | Unknown `action`                                              |
| `The service list can be fetched once every 5 minutes. Try again in N minutes.` | `imeiservicelist` asked for again within 5 minutes |
| `Missing or invalid parameters`                                | `parameters` missing or not readable                          |
| `Service ID is required`                                       | No `ID` in the order                                          |
| `Invalid service ID`                                           | No service on sale with that `SERVICEID`                      |
| `This service is not available right now`                      | The service is switched off for ordering                      |
| `Missing required field: <name>`                               | A required `IMEI` or custom field was not sent                |
| `Duplicate IMEI found for this service!`                       | An open order for the same IMEI exists (when the shop checks) |
| `Insufficient balance`                                         | Not enough balance for the order                              |
| `Overdue limit exceeded`                                       | Not enough balance, and over your credit (overdue) limit      |
| `Order could not be placed. Please try again.`                 | Temporary failure; nothing was charged                        |
| `Order ID is required`                                         | `getimeiorder` without a numeric `ID`                         |
| `Order ID not found!`                                          | No order with that reference on your account                  |
| `Parameters required for bulk orders.`                         | Bulk action without `parameters`                              |
| `Parameters must be a base64 encoded JSON array.`              | Bulk `parameters` not readable                                |
| `Too many orders in one request. The limit is 100.`            | More than 100 entries in one bulk request                     |

Quantity problems (below the minimum, above the maximum, not one of the fixed
amounts) are reported in words in the same place.

## Limits

| What                         | Limit                                    |
| ---------------------------- | ---------------------------------------- |
| `imeiservicelist`            | Once every 5 minutes per account         |
| Orders per bulk request      | 100                                      |
| Order lookups per bulk request | 100                                    |

Cache the service list on your side and refresh it on a schedule (every
15–60 minutes is plenty).

## Client library and examples

- [`clients/php/GsmXToolApi.php`](clients/php/GsmXToolApi.php) — a small PHP
  client, no dependencies beyond cURL.
- [`examples/php/example.php`](examples/php/example.php) — balance, service
  list, an order and its result, using the client.
- [`examples/curl.sh`](examples/curl.sh) — the same from a shell.

**Using Dhru Fusion?** You do not need any of this: install the *GSM X TOOL*
API module (download link under Dashboard → API Settings) and enter the API
URL, your email and your key.
