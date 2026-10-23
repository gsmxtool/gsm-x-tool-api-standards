#!/usr/bin/env sh
# The GSM X TOOL reseller API from a shell.
# Fill in the three values from Dashboard → API Settings.

URL="https://shop.example/api/index.php"
USERNAME="you@example.com"
KEY="YOUR-API-ACCESS-KEY"

call() {
    # $1 = action, $2 = parameters as JSON (optional)
    if [ -n "$2" ]; then
        PARAMS=$(printf '%s' "$2" | base64 | tr -d '\n')
        curl -s -X POST "$URL" \
            --data-urlencode "username=$USERNAME" \
            --data-urlencode "apiaccesskey=$KEY" \
            --data-urlencode "action=$1" \
            --data-urlencode "requestformat=JSON" \
            --data-urlencode "parameters=$PARAMS"
    else
        curl -s -X POST "$URL" \
            --data-urlencode "username=$USERNAME" \
            --data-urlencode "apiaccesskey=$KEY" \
            --data-urlencode "action=$1" \
            --data-urlencode "requestformat=JSON"
    fi
    echo
}

# Balance
call accountinfo

# Catalogue (once every 5 minutes)
call imeiservicelist

# One order — ID is a SERVICEID from the list
call placeimeiorder '{"ID":"1203","IMEI":"356789012345678"}'

# With custom fields: CUSTOMFIELD is base64 of {"Field name":"value"}
CUSTOM=$(printf '%s' '{"Model":"S23"}' | base64 | tr -d '\n')
call placeimeiorder "{\"ID\":\"1203\",\"IMEI\":\"356789012345678\",\"CUSTOMFIELD\":\"$CUSTOM\"}"

# Several orders at once (your own keys, up to 100)
call placebulkorder '{"a1":{"ID":"1203","IMEI":"356789012345678"},"a2":{"ID":"1203","IMEI":"356789012345679"}}'

# An order's result — ID is the REFERENCEID you got back
call getimeiorder '{"ID":98213}'

# Several results at once
call getimeiorderbulk '{"x1":{"ID":98213},"x2":{"ID":98214}}'
