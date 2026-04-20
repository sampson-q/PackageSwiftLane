# PackageSwiftLane API v1

## Overview

RESTful API for shipping and logistics management. Provides authentication, user profiles, package tracking, consolidations, notifications, and administrative lookups for mobile and web clients.

**Version**: 1.0  
**Base URL**: `https://api.yourdomain.com/api/v1`  
**Environment**: Production-grade with OTP-based authentication and JWT tokens

## Quick Start

### 1. Register
```bash
POST /auth/register
Content-Type: application/json

{
  "username": "john_doe",
  "email": "john@example.com",
  "pass": "SecurePass@123",
  "pass2": "SecurePass@123",
  "fname": "John",
  "lname": "Doe",
  "phone": "+1-234-567-8900",
  "document_number": "12345678",
  "document_type": "passport",
  "country": "USA",
  "state": "NY",
  "city": "New York",
  "address": "123 Main St",
  "postal": "10001",
  "terms": 1
}
```

### 2. Verify OTP
```bash
POST /auth/verify-register-otp
{
  "challenge_id": 123,
  "otp": "654321"
}
```

### 3. Login
```bash
POST /auth/login
{
  "username": "john_doe",
  "password": "SecurePass@123",
  "remember_me": true
}
```

### 4. Verify Login OTP
```bash
POST /auth/verify-login-otp
{
  "challenge_id": 124,
  "otp": "654321"
}
```

Response includes JWT token for authenticated requests.

## Authentication

### Token Usage
Include JWT in all authenticated requests:
```
Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
```

### Token Refresh
Token expires after 1 hour. Refresh before expiry:
```bash
POST /auth/refresh
Authorization: Bearer <current_token>
```

### Session Management
- **Trusted Devices**: Set `remember_me: true` on login to skip OTP on next login (60 day duration)
- **Revoke All**: `POST /auth/devices/revoke-all` to logout from all devices
- **Automatic Revocation**: All devices revoked after password reset

## Response Format

### Success Response
```json
{
  "status": "success",
  "code": 200,
  "message": "Operation completed",
  "data": { },
  "meta": { }
}
```

### Error Response
```json
{
  "status": "error",
  "code": 400,
  "message": "validation_failed",
  "errors": {
    "email": "Invalid email address",
    "pass": "Password too short"
  }
}
```

## HTTP Status Codes

| Code | Meaning |
|------|---------|
| 200 | Success |
| 201 | Created |
| 400 | Bad Request (validation error) |
| 401 | Unauthorized (invalid token, failed OTP) |
| 403 | Forbidden (inactive account) |
| 404 | Not Found |
| 409 | Conflict (duplicate email/username) |
| 422 | Validation Failed |
| 500 | Server Error |

## Endpoints by Feature

### Authentication (No Auth Required)
- `POST /auth/register` — Start registration
- `POST /auth/verify-register-otp` — Complete registration
- `POST /auth/login` — Start login
- `POST /auth/verify-login-otp` — Complete login
- `POST /auth/forgot-password` — Request password reset
- `POST /auth/reset-password` — Complete password reset

### Authentication (Auth Required)
- `GET /auth/me` — Current user profile
- `POST /auth/logout` — Logout
- `POST /auth/refresh` — Refresh token
- `POST /auth/devices/revoke-all` — Revoke all trusted devices

### Profile (Auth Required)
- `GET /profile` — Get profile
- `PUT /profile` — Update profile

### Notifications (Auth Required)
- `GET /notifications` — List notifications (paginated)
- `POST /notifications/{id}/read` — Mark notification as read

### Recipients (Auth Required)
- `GET /recipients` — List recipients (paginated)
- `POST /recipients` — Create recipient
- `GET /recipients/{id}` — Get recipient details

### Pre-Alerts (Auth Required)
- `GET /prealerts` — List pre-alerts (paginated)
- `POST /prealerts` — Create pre-alert
- `GET /prealerts/{id}` — Get pre-alert details

### Shipments (Auth Required)
- `GET /shipments` — List shipments (paginated)
- `GET /shipments/{id}` — Get shipment details
- `GET /shipments/{id}/tracking` — Get shipment tracking history

### Pickups (Auth Required)
- `GET /pickups` — List pickups (paginated)
- `GET /pickups/{id}` — Get pickup details
- `GET /pickups/{id}/tracking` — Get pickup tracking history

### Packages (Auth Required)
- `GET /packages` — List packages (paginated)
- `GET /packages/{id}` — Get package details
- `GET /packages/{id}/tracking` — Get package tracking history

### Consolidations (Auth Required)
- `GET /consolidations` — List consolidations (paginated)
- `GET /consolidations/{id}` — Get consolidation details
- `GET /consolidations/{id}/details` — Get consolidation items
- `GET /consolidations-packages` — List consolidated packages (paginated)
- `GET /consolidations-packages/{id}` — Get consolidated package details

### Lookups (No Auth Required)
- `GET /lookup/countries` — List countries
- `GET /lookup/states?country_id=1` — List states by country
- `GET /lookup/cities?state_id=1` — List cities by state
- `GET /lookup/shipping-modes` — List shipping modes
- `GET /lookup/delivery-times` — List delivery times
- `GET /lookup/packaging` — List packaging options
- `GET /lookup/payment-methods` — List payment methods
- `GET /lookup/courier-companies` — List courier companies
- `GET /lookup/incoterms` — List incoterms
- `GET /lookup/offices` — List offices
- `GET /lookup/branches` — List branches
- `GET /lookup/statuses` — List statuses

### Public Tracking (No Auth Required)
- `GET /tracking/{tracking_number}` — Get shipment tracking by tracking number

### System (No Auth Required)
- `GET /health` — Health check
- `GET /customers` — List customers (admin only)

## Pagination

List endpoints support pagination:

**Query Parameters**:
- `page` (default: 1) — Page number
- `per_page` (default: 25, max: 100) — Results per page

**Response Metadata**:
```json
{
  "meta": {
    "page": 1,
    "per_page": 25,
    "total": 500
  }
}
```

**Example**:
```bash
GET /shipments?page=2&per_page=50
```

## Filtering & Searching

Supported on list endpoints:

**Query Parameters**:
- `search` (string) — Search by tracking number or name
- `status_courier` (int) — Filter by courier status
- `sort` (string) — Sort field
- `order` (string) — `asc` or `desc`

**Example**:
```bash
GET /shipments?search=TRACK123&status_courier=2&sort=order_date&order=desc
```

## Data Models

### User
```
id: integer
username: string (3-20 chars, alphanumeric+._-)
email: string
fname: string
lname: string
phone: string
document_number: string
document_type: string
userlevel: integer (1=customer, 3=driver)
active: boolean
lastlogin: datetime
```

### Shipment
```
order_id: integer
order_prefix: string
order_no: string
order_date: datetime
sender_id: integer
receiver_id: integer
status_courier: integer
status_invoice: integer
total_order: decimal
order_pay_mode: integer
order_courier: integer
order_service_options: integer
driver_id: integer
is_pickup: boolean
```

### Notification
```
id: integer
user_id: integer
order_id: integer
notification_description: string
shipping_type: string
notification_date: datetime
is_read: boolean
```

## Security

### Password Requirements
- Minimum 10 characters
- Must contain 3 of 4:
  - Uppercase letter
  - Lowercase letter
  - Digit
  - Special character (!@#$%^&*...)

### OTP Authentication
- 6-digit codes
- 5-minute expiry
- 5 maximum attempts per challenge
- SHA256 hashing with salt

### Rate Limiting
- OTP: 5 attempts per challenge before lockout
- General: Implement at reverse proxy level (recommended)

### CORS
Whitelist-based origin validation. Configure in `config.php`:
```php
'cors' => [
    'allow_origin' => ['https://yourdomain.com', 'https://app.yourdomain.com'],
]
```

### HTTPS
All endpoints require HTTPS in production.

## Error Handling

### Validation Errors (422)
```json
{
  "status": "error",
  "code": 422,
  "message": "validation_failed",
  "errors": {
    "email": "Invalid email address",
    "pass": "Password too short (minimum 10 characters)"
  }
}
```

### Authentication Errors (401)
```json
{
  "status": "error",
  "code": 401,
  "message": "Invalid credentials"
}
```

### Not Found (404)
```json
{
  "status": "error",
  "code": 404,
  "message": "Resource not found"
}
```

### Server Errors (500)
```json
{
  "status": "error",
  "code": 500,
  "message": "Internal server error"
}
```

## Development

### Configuration
Copy and edit `api/v1/config.example.php`:
```bash
cp api/v1/config.php api/v1/config.example.php
```

Required settings:
- `jwt_secret` — Strong random string (use: `openssl rand -base64 32`)
- `cors.allow_origin` — Your application domains
- `reset_url_base` — Frontend password reset URL
- `dev_mode` — Set to `false` in production

### Email Templates
OTP emails use templates:
- Template #28: Signup OTP
- Template #30: Login OTP
- Template #27: Password Reset

Ensure these exist in `cdb_email_templates` table.

### Database
OtpService creates required tables automatically:
- `cdb_auth_otp_challenges` — OTP storage
- `cdb_auth_trusted_devices` — Device memory
- `cdb_password_reset_sessions` — Reset tokens

### Logging
All authentication events logged to PHP error_log:
```
[API-INFO] {"timestamp":"2024-01-15 10:30:00","method":"POST","action":"auth_success_login"}
[API-SECURITY] {"timestamp":"2024-01-15 10:31:00","event":"login_otp_verification_failed"}
```

## Testing

### Health Check
```bash
curl https://api.yourdomain.com/api/v1/health
```

### Full Authentication Flow
```bash
# 1. Register
curl -X POST https://api.yourdomain.com/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d @signup.json

# 2. Verify OTP (check email for code)
curl -X POST https://api.yourdomain.com/api/v1/auth/verify-register-otp \
  -H "Content-Type: application/json" \
  -d '{"challenge_id": 1, "otp": "123456"}'

# 3. Login
curl -X POST https://api.yourdomain.com/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username": "john_doe", "password": "SecurePass@123"}'

# 4. Verify Login OTP
curl -X POST https://api.yourdomain.com/api/v1/auth/verify-login-otp \
  -H "Content-Type: application/json" \
  -d '{"challenge_id": 2, "otp": "654321"}'

# 5. Authenticated Request
curl -H "Authorization: Bearer <token>" \
  https://api.yourdomain.com/api/v1/auth/me
```

## Support

For API issues, check:
1. Response status code and message
2. `errors` object for field-specific validation errors
3. Server logs at PHP error_log path
4. Request format (ensure JSON Content-Type for POST/PUT)
