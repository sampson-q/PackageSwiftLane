# PackageSwiftLane API v1 Documentation

## Overview

Production-grade REST API for authentication, user management, and package/shipping operations. Uses database-backed OTP challenges, JWT tokens, and comprehensive audit logging.

**Base URL**: `https://yourdomain.com/api/v1`  
**Status**: Production-ready  
**Last Updated**: 2024

---

## Authentication

All protected endpoints require a Bearer token in the `Authorization` header:

```
Authorization: Bearer <jwt_token>
```

Tokens expire after 3600 seconds (default) and can be refreshed with `/auth/refresh`.

---

## Endpoints

### Authentication

#### 1. Register (Step 1: Submit Info)
```
POST /auth/register
```

**Request**:
```json
{
  "username": "john_doe",
  "email": "john@example.com",
  "pass": "MySecure@Pass123",
  "pass2": "MySecure@Pass123",
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

**Response** (200):
```json
{
  "status": "success",
  "code": 200,
  "message": "OTP sent to email",
  "data": {
    "requires_otp": true,
    "challenge_id": 42,
    "expires_in": 300
  }
}
```

**Validation Rules**:
- `username`: 3-20 chars, alphanumeric with `.`, `-`, `_`
- `email`: Valid email format
- `pass`: Min 10 chars, must contain 3 of: uppercase, lowercase, digit, special char
- `phone`: 10-15 digits
- All fields required

---

#### 2. Verify Registration OTP (Step 2)
```
POST /auth/verify-register-otp
```

**Request**:
```json
{
  "challenge_id": 42,
  "otp": "123456"
}
```

**Response** (201):
```json
{
  "status": "success",
  "code": 201,
  "message": "Account created successfully",
  "data": {
    "user_id": 1,
    "username": "john_doe",
    "email": "john@example.com",
    "name": "John Doe"
  }
}
```

**Error Responses**:
- `401`: OTP verification failed (invalid code, expired, or too many attempts)
- `409`: Email or username already registered (race condition prevented)

---

#### 3. Login (Step 1: Submit Credentials)
```
POST /auth/login
```

**Request**:
```json
{
  "username": "john_doe",
  "password": "MySecure@Pass123",
  "remember_me": true
}
```

**Response** (200 - if trusted device):
```json
{
  "status": "success",
  "code": 200,
  "message": "Login successful",
  "data": {
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "token_type": "Bearer",
    "expires_in": 3600,
    "user": {
      "id": 1,
      "username": "john_doe"
    }
  }
}
```

**Response** (200 - requires OTP):
```json
{
  "status": "success",
  "code": 200,
  "message": "OTP sent to email",
  "data": {
    "requires_otp": true,
    "challenge_id": 43,
    "expires_in": 300
  }
}
```

**Features**:
- Automatic OTP bypass if device is trusted (from previous `remember_me` login)
- Set `remember_me: true` to receive a cookie-based device token (60 day expiry)

---

#### 4. Verify Login OTP (Step 2)
```
POST /auth/verify-login-otp
```

**Request**:
```json
{
  "challenge_id": 43,
  "otp": "123456"
}
```

**Response** (200):
```json
{
  "status": "success",
  "code": 200,
  "message": "Login successful",
  "data": {
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "token_type": "Bearer",
    "expires_in": 3600,
    "user": {
      "id": 1,
      "username": "john_doe"
    }
  }
}
```

---

#### 5. Get Current User
```
GET /auth/me
Authorization: Bearer <token>
```

**Response** (200):
```json
{
  "status": "success",
  "code": 200,
  "message": "User fetched",
  "data": {
    "user": {
      "id": 1,
      "username": "john_doe",
      "email": "john@example.com",
      "name": "John Doe",
      "userlevel": 1,
      "lastlogin": "2024-01-15 10:30:00"
    }
  }
}
```

---

#### 6. Refresh Token
```
POST /auth/refresh
Authorization: Bearer <token>
```

**Response** (200):
```json
{
  "status": "success",
  "code": 200,
  "message": "Token refreshed",
  "data": {
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "token_type": "Bearer",
    "expires_in": 3600
  }
}
```

Use this when your token is expiring but session is still active. No re-authentication required.

---

#### 7. Logout
```
POST /auth/logout
Authorization: Bearer <token>
```

**Response** (200):
```json
{
  "status": "success",
  "code": 200,
  "message": "Logged out",
  "data": null
}
```

**Note**: Token-based system; client should delete token locally. Tokens cannot be blacklisted by the server (stateless JWT design). Use `/auth/refresh` denial or `/auth/devices/revoke-all` for session management.

---

### Password Management

#### 8. Request Password Reset
```
POST /auth/forgot-password
```

**Request**:
```json
{
  "email": "john@example.com"
}
```

**Response** (200):
```json
{
  "status": "success",
  "code": 200,
  "message": "If email exists, password reset link sent",
  "data": null
}
```

**Security**: Always returns success regardless of email existence (prevents user enumeration).

---

#### 9. Reset Password
```
POST /auth/reset-password
```

**Request**:
```json
{
  "token": "<reset_token_from_email>",
  "new_password": "NewSecure@Pass123",
  "confirm_password": "NewSecure@Pass123"
}
```

**Response** (200):
```json
{
  "status": "success",
  "code": 200,
  "message": "Password reset successfully. Please log in.",
  "data": null
}
```

**Features**:
- Token valid for 15 minutes
- Password must be 10+ chars with 3 of 4: uppercase, lowercase, digit, special char
- All trusted devices revoked after reset
- User must re-login

---

### Device Management

#### 10. Revoke All Trusted Devices
```
POST /auth/devices/revoke-all
Authorization: Bearer <token>
```

**Response** (200):
```json
{
  "status": "success",
  "code": 200,
  "message": "All trusted devices revoked",
  "data": null
}
```

**Use Cases**:
- After password reset (automatic)
- User suspects account compromise
- Security-conscious users managing sessions
- Logout from all devices

---

## HTTP Status Codes

| Code | Meaning | Common Causes |
|------|---------|--------------|
| 200 | Success | OK |
| 201 | Created | User created, resource created |
| 400 | Bad Request | Missing/invalid required fields |
| 401 | Unauthorized | Invalid credentials, expired token, OTP failed |
| 403 | Forbidden | Account inactive, permission denied |
| 404 | Not Found | Resource doesn't exist, user not found |
| 409 | Conflict | Email/username already exists (race condition prevented) |
| 422 | Validation Failed | Input validation errors with details |
| 500 | Server Error | Unexpected error |

---

## Error Responses

Standard error format:

```json
{
  "status": "error",
  "code": 400,
  "message": "validation_failed",
  "errors": {
    "email": "Invalid email address",
    "pass": "Password too short (minimum 10 characters)"
  }
}
```

---

## OTP & Challenge System

All authentication flows use **database-backed OTP challenges** with:

- **6-digit codes** (100000-999999)
- **5-minute expiry** by default
- **5 max attempts** before lockout
- **SHA256 hashing** with salt (never stored in plaintext)
- **Email delivery** via configured SMTP or PHP mail()
- **WhatsApp delivery** available for premium users
- **Automatic replacement** of pending challenges (prevents accumulation)

### Challenge Lifecycle

```
1. POST /auth/login
   ↓ Creates challenge
2. sendOtpEmail()
   ↓ User receives OTP
3. POST /auth/verify-login-otp
   ↓ Validates code & attempt count
4. Challenge marked 'verified' or 'locked'/'expired'
```

---

## Trusted Device System

**Purpose**: Skip OTP verification on subsequent logins from the same browser/device.

**Mechanism**:
1. User logs in with `remember_me: true`
2. OTP verified successfully
3. 24-byte selector + 64-byte verifier created
4. Verifier hashed and stored in `cdb_auth_trusted_devices`
5. Selector + verifier sent as `trusted_device` cookie (httponly, secure, samesite=Lax)
6. Next login: verifier validated, device checked for expiry/revocation
7. Device auto-refreshes `last_used_at` timestamp

**Device Expiry**: 60 days from creation  
**Device Revocation**: Via `/auth/devices/revoke-all` or automatic post-password-reset

---

## JWT Token Structure

```json
{
  "uid": 1,
  "username": "john_doe",
  "userlevel": 1,
  "iat": 1234567890,
  "exp": 1234571490
}
```

**Claims**:
- `uid`: User ID (int)
- `username`: Username (string)
- `userlevel`: Permission level (int, 1=customer, 3=driver, etc)
- `iat`: Issued at (Unix timestamp)
- `exp`: Expiry (Unix timestamp)

**Algorithm**: HS256 (HMAC-SHA256)  
**Default TTL**: 3600 seconds (1 hour)

---

## Security Features

### Implemented ✅

- **Password Hashing**: bcrypt with PASSWORD_DEFAULT
- **OTP Storage**: SHA256 + salt (never plaintext)
- **Trusted Device**: Selector + hashed verifier pattern
- **HTTPS Enforcement**: Secure + httponly cookies
- **Rate Limiting**: 5 OTP attempts per challenge
- **Input Validation**: Email, phone, username, password strength
- **SQL Injection Prevention**: Parameterized queries throughout
- **CORS**: Whitelist-based origin validation
- **Security Headers**: X-Content-Type-Options, HSTS, CSP, X-Frame-Options
- **Audit Logging**: All auth events logged
- **IP Tracking**: Proxy-aware client IP detection
- **Race Condition Prevention**: Transaction-based duplicate checks
- **Token Refresh**: Long-running operations can renew tokens

### Sensitive Operations

All of the following are logged with IP, user ID, timestamp, and outcome:
- Login attempts (success/failure reasons)
- OTP verification (success/failure)
- Password resets
- Device revocation
- Token refreshes

---

## API Configuration

### config.php

```php
return [
    'jwt_secret' => 'your-256-bit-secret-key-here',
    'jwt_ttl' => 3600,
    'cors' => [
        'allow_origin' => ['https://yourdomain.com', 'https://app.yourdomain.com'],
        'allow_methods' => 'POST, GET, OPTIONS, PUT, DELETE',
        'allow_headers' => 'Content-Type, Authorization, X-Requested-With, Accept',
        'allow_credentials' => true,
    ],
    'reset_url_base' => 'https://yourdomain.com/reset-password',
    'dev_mode' => false,
];
```

**Important**:
- `jwt_secret`: Must be a long, random string (use `openssl rand -base64 32`)
- `dev_mode`: Never enable in production (exposes OTP codes)
- `reset_url_base`: URL where your frontend handles password reset

---

## Testing

### Postman Collection

```json
{
  "info": {
    "name": "PackageSwiftLane API v1",
    "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
  },
  "item": [
    {
      "name": "Register",
      "request": {
        "method": "POST",
        "url": "{{baseUrl}}/auth/register",
        "body": {
          "mode": "raw",
          "raw": "{\"username\": \"testuser\", ...}"
        }
      }
    }
  ]
}
```

### Local Development

```bash
# Enable dev_mode in config.php for OTP debugging
'dev_mode' => true,

# Then responses include:
{
  "data": {
    "challenge_id": 42,
    "debug_otp": "123456"
  }
}
```

---

## Migration from Old API

If upgrading from file-based OTP system:

1. **OtpService replaces Otp class**
   - Uses database tables instead of files
   - No more pending_logins/, pending_signups/ directories
   - Schema created automatically by OtpService::ensureTables()

2. **Updated Endpoints**
   - Old: `challenge_id` + `otp` (same)
   - New: Uses `createChallenge()` instead of `cdp_generateOtp()`
   - Metadata stored in JSON (flexible for future features)

3. **Trusted Devices**
   - Replaces cookie-based device trust
   - Now database-backed with expiry/revocation
   - Selector+verifier pattern more secure than raw tokens

4. **Password Reset** (new feature)
   - Old API didn't have password reset OTP flow
   - New: `/auth/forgot-password` + `/auth/reset-password` with email links

---

## Production Deployment Checklist

- [ ] Update `config.php` with production domain and JWT secret
- [ ] Set `dev_mode: false`
- [ ] Verify CORS `allow_origin` includes only your domains
- [ ] Ensure `assets/uploads/` directory exists and is writable (if file uploads added)
- [ ] Configure email/SMTP settings in Core class
- [ ] Set up background cleanup for expired OTP challenges (cron job)
- [ ] Enable HTTPS (required for secure cookies)
- [ ] Set up error logging (check logs regularly)
- [ ] Review audit logs after major updates
- [ ] Implement rate limiting at reverse proxy level (optional but recommended)
- [ ] Test all endpoints with real data
- [ ] Backup database before deployment

---

## Support

For issues, errors, or questions:
1. Check logs in `/var/log/php/` (or php.ini error_log location)
2. Review API_ISSUES_ANALYSIS.md for known edge cases
3. Check AuthController.php inline comments for implementation details
