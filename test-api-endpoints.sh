#!/bin/bash

# Air Du Cap API Test Runner
# This script runs curl commands to test both UAT and Production APIs

echo "=== Air Du Cap API Testing ==="
echo "Testing both UAT and Production environments..."
echo ""

# Credentials
USERNAME="dev101@dev101.com"
PASSWORD="QgdYlFgTvAQTcCC"
UAT_URL="https://uat-book.airducap.com"
PROD_URL="https://book.airducap.com"

echo "1. Testing UAT Environment..."
echo "================================"

echo "UAT Airport Search (Cape):"
curl -s -u "$USERNAME:$PASSWORD" \
  "$UAT_URL/airports/api/list/?q=cape&field_name=from_location" \
  | jq '.' 2>/dev/null || echo "Response is not JSON or jq not installed"

echo ""
echo "UAT Flight Search (Cape Winelands to Victoria West):"
curl -s -u "$USERNAME:$PASSWORD" \
  "$UAT_URL/flights/api/search/?from_location=94&to_location=158&date_of_travel=09/09/2025&adults=1&currency=ZAR&ip_address=127.0.0.1" \
  | jq '.available_flights[0] | {plane, computed_price, speed, duration, distance}' 2>/dev/null || echo "Response is not JSON or jq not installed"

echo ""
echo "2. Testing Production Environment..."
echo "===================================="

echo "Production Airport Search (Cape):"
PROD_AIRPORT_RESPONSE=$(curl -s -w "HTTP_STATUS:%{http_code}" -u "$USERNAME:$PASSWORD" \
  "$PROD_URL/airports/api/list/?q=cape&field_name=from_location")

HTTP_STATUS=$(echo "$PROD_AIRPORT_RESPONSE" | grep -o "HTTP_STATUS:[0-9]*" | cut -d: -f2)
RESPONSE_BODY=$(echo "$PROD_AIRPORT_RESPONSE" | sed 's/HTTP_STATUS:[0-9]*$//')

echo "Status Code: $HTTP_STATUS"
if [ "$HTTP_STATUS" = "200" ]; then
    echo "$RESPONSE_BODY" | jq '.' 2>/dev/null || echo "$RESPONSE_BODY"
else
    echo "Error Response: $RESPONSE_BODY"
fi

echo ""
echo "Production Flight Search (Cape Winelands to Victoria West):"
PROD_FLIGHT_RESPONSE=$(curl -s -w "HTTP_STATUS:%{http_code}" -u "$USERNAME:$PASSWORD" \
  "$PROD_URL/flights/api/search/?from_location=94&to_location=158&date_of_travel=09/09/2025&adults=1&currency=ZAR&ip_address=127.0.0.1")

HTTP_STATUS=$(echo "$PROD_FLIGHT_RESPONSE" | grep -o "HTTP_STATUS:[0-9]*" | cut -d: -f2)
RESPONSE_BODY=$(echo "$PROD_FLIGHT_RESPONSE" | sed 's/HTTP_STATUS:[0-9]*$//')

echo "Status Code: $HTTP_STATUS"
if [ "$HTTP_STATUS" = "200" ]; then
    echo "$RESPONSE_BODY" | jq '.available_flights[0] | {plane, computed_price, speed, duration, distance}' 2>/dev/null || echo "$RESPONSE_BODY"
else
    echo "Error Response: $RESPONSE_BODY"
fi

echo ""
echo "3. Testing Alternative Production Endpoints..."
echo "============================================="

echo "Alternative Airport Endpoint (/api/airports/):"
ALT_RESPONSE=$(curl -s -w "HTTP_STATUS:%{http_code}" -u "$USERNAME:$PASSWORD" \
  "$PROD_URL/api/airports/?q=cape&field_name=from_location")

HTTP_STATUS=$(echo "$ALT_RESPONSE" | grep -o "HTTP_STATUS:[0-9]*" | cut -d: -f2)
RESPONSE_BODY=$(echo "$ALT_RESPONSE" | sed 's/HTTP_STATUS:[0-9]*$//')

echo "Status Code: $HTTP_STATUS"
if [ "$HTTP_STATUS" = "200" ]; then
    echo "$RESPONSE_BODY" | jq '.' 2>/dev/null || echo "$RESPONSE_BODY"
else
    echo "Error Response: $RESPONSE_BODY"
fi

echo ""
echo "Root API Check:"
ROOT_RESPONSE=$(curl -s -w "HTTP_STATUS:%{http_code}" -u "$USERNAME:$PASSWORD" \
  "$PROD_URL/api/")

HTTP_STATUS=$(echo "$ROOT_RESPONSE" | grep -o "HTTP_STATUS:[0-9]*" | cut -d: -f2)
RESPONSE_BODY=$(echo "$ROOT_RESPONSE" | sed 's/HTTP_STATUS:[0-9]*$//')

echo "Status Code: $HTTP_STATUS"
echo "Response: $RESPONSE_BODY"

echo ""
echo "=== Test Summary ==="
echo "UAT Environment: Should work (200 OK responses)"
echo "Production Environment: Likely returns 404 errors" 
echo "This indicates the Production API has different:"
echo "  - URL structure"
echo "  - Authentication requirements"  
echo "  - Or is hosted on a different domain"
echo ""
echo "Next steps:"
echo "1. Contact client for correct Production API details"
echo "2. Use UAT environment for testing your plugin"
echo "3. Ask client about Production vs UAT data differences"
