# Air Du Cap API Testing Guide

## Overview
This guide helps you understand the exact differences between UAT and Production environments, and identify why Production returns 404 errors while UAT works correctly.

## Quick Start
1. Import the enhanced `AirDuCap-API-Postman-Collection.json` into Postman
2. Run the collection sections in order to diagnose the issues

## Testing Strategy

### 1. Environment Health Check
**Purpose**: Verify basic connectivity to both environments

**Tests to Run**:
- `UAT - Health Check`: Should return 200 OK with airport data
- `Production - Health Check`: Currently returns 404 - this is the main issue

**Expected Results**:
```json
// UAT Response (Working)
[
  {
    "id": 94,
    "name": "Cape Winelands Airport",
    "city": "Cape Winelands",
    "country": "South Africa"
  }
]

// Production Response (Broken)
404 Not Found
```

### 2. Exact Client Route Comparison
**Purpose**: Compare the exact same flight search between environments

**Key Route**: Cape Winelands (94) to Victoria West (158)
- Date: September 9, 2025
- Return: September 30, 2025
- Adults: 1

**What to Look For**:
- UAT: Different aircraft than client's website
- Production: 404 error or different aircraft fleet

### 3. Authentication Testing
**Purpose**: Check if authentication is the issue

**Tests**:
- Basic auth with headers
- Different content-type headers
- OPTIONS requests to check CORS

### 4. API Structure Discovery
**Purpose**: Find the correct Production API endpoints

**Alternative Endpoints to Test**:
- `/api/airports/` instead of `/airports/api/list/`
- `/api/flights/search/` instead of `/flights/api/search/`
- Different authentication methods

## Key Findings Expected

### 1. Production API Issues
The 404 errors suggest:
- **Different URL structure**: Production might use different endpoint paths
- **Authentication differences**: Different auth requirements
- **Domain/subdomain differences**: API might be on a different subdomain
- **Version differences**: API versioning might be different

### 2. Data Differences Between Environments
- **UAT Aircraft Fleet**: Limited test aircraft
- **Production Aircraft Fleet**: Full commercial fleet
- **Image URLs**: Different CDN or image storage
- **Pricing**: Different pricing engines

## Step-by-Step Testing Process

### Step 1: Run Environment Health Check
```bash
# In Postman, run these requests:
1. "UAT - Health Check" 
2. "Production - Health Check"
```

**Analysis**:
- If UAT works and Production fails → URL/endpoint issue
- If both fail → Authentication issue
- If both work → Your plugin has a different issue

### Step 2: Authentication Debug
```bash
# Test different auth methods:
1. "UAT - Check Authentication"
2. "Production - Check Authentication"  
3. "Production - Different Auth Headers"
```

### Step 3: Find Correct Production Endpoints
```bash
# Try alternative endpoints:
1. "Production - Try Alternative Airport Endpoint"
2. "Production - Try Alternative Flight Endpoint"
3. "Production - Root API Check"
4. "Production - OPTIONS Request"
```

### Step 4: Compare Full Data Sets
```bash
# Get complete airport lists:
1. "UAT - Full Airport List"
2. "Production - Full Airport List"
```

## Common Issues and Solutions

### Issue 1: Production Returns 404
**Likely Causes**:
- API moved to different subdomain (e.g., `api.airducap.com`)
- Different endpoint structure (e.g., `/v1/airports/` vs `/airports/api/`)
- API versioning in URLs (e.g., `/api/v2/`)

**Solution**: Test alternative URL patterns

### Issue 2: Authentication Fails
**Likely Causes**:
- Production requires JWT tokens instead of Basic Auth
- Different credential requirements
- IP whitelist restrictions

**Solution**: Contact client for Production API credentials

### Issue 3: Different Aircraft Data
**Expected**: UAT has test data, Production has real fleet
**Solution**: Use Production environment for real data

## Expected Console Output

When you run the collection, look for these patterns:

### UAT Working Response:
```
UAT Airports found: 15
UAT Sample airport: {
  "id": 94,
  "name": "Cape Winelands Airport",
  "city": "Cape Winelands"
}
UAT Available Flights: 2
Flight 1:
  Aircraft: Cessna 208B Grand Caravan
  Price: R 8500
  Speed: 170 knots
```

### Production Error Response:
```
❌ Production API returns 404 - Endpoint not found
Response: {"error": "Not Found", "message": "The requested resource was not found"}
```

## Next Steps Based on Results

### If Production API Structure is Different:
1. Update your plugin's API URLs
2. Modify authentication method if needed
3. Test with new endpoints

### If Production Requires Different Credentials:
1. Contact client for Production API access
2. Request proper authentication tokens
3. Update plugin configuration

### If Production Has Different Data:
1. This is expected (UAT vs Production data)
2. Use Production for real aircraft fleet
3. Update plugin to handle both environments

## Plugin Configuration Updates

Based on test results, you may need to update:

```php
// In airducap-integration.php
define('AIRDUCAP_API_BASE', 'https://api.airducap.com'); // If API moved
define('AIRDUCAP_AUTH_METHOD', 'jwt'); // If auth changed
```

## Contact Client Questions

Based on test results, ask the client:

1. **API Access**: "What are the correct Production API endpoints and credentials?"
2. **Environment Differences**: "Should the plugin use UAT or Production data?"
3. **Authentication**: "What authentication method does Production require?"
4. **URL Structure**: "Have the Production API URLs changed recently?"

## Success Criteria

✅ **Both environments return 200 OK**
✅ **Airport searches work in both environments**  
✅ **Flight searches return results**
✅ **Aircraft data matches client's website**
✅ **Images load correctly**
✅ **Booking URLs are functional**

Run this testing suite to identify exactly where the disconnect is between your plugin and the client's working website.
