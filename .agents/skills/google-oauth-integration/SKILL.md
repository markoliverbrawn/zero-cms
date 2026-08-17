---
name: google-oauth-integration
description: Explains Zero CMS's zero-dependency Google OAuth 2.0 single-sign-on flow (raw cURL, no SDK) and its multi-tenant scoping boundary checks. Use when modifying GoogleAuthController.php, debugging OAuth callback/login failures, or reviewing tenant-isolation checks on SSO login.
---

# Zero-Dependency Google OAuth 2.0 Integration & Scoping

To provide modern Single-Sign-On authentication without relying on bloated libraries, Zero CMS implements raw OAuth 2.0 flows using native PHP `cURL`, never a vendor SDK.

## 1. Anti-CSRF State Tokens

Before redirecting users to the Google Authorization page, the engine registers a random hex state token inside `$_SESSION['_csrf_token']` using `Security::csrfToken()`. Google redirects the callback, passing this token inside the `state` parameter. The callback controller verifies this immediately using `Security::csrfVerify($state)`, mitigating CSRF hijacking.

## 2. Standard Exchange Handshake (`src/Modules/Admin/Controllers/GoogleAuthController.php`)

Uses a native PHP `cURL` POST session to exchange Google's temporary authorization code for a secure Access Token:

```php
$ch = curl_init('https://oauth2.googleapis.com/token');
// POST payload with client_id, client_secret, redirect_uri, and code
```

Employs a secure GET session with authorization bearer headers to retrieve the user's validated profile email from `https://www.googleapis.com/oauth2/v3/userinfo`.

## 3. Strict Multi-Tenant Scoping Boundaries

After resolving the Google user email, the database is queried. To prevent data leaks and crossing multi-tenant boundaries, the resolved user must be a global platform `super_admin` OR their assigned `site_id` must match `App::getCurrentSiteId()`. Failing this check redirects users back with a specific isolation-mismatch security warning.
