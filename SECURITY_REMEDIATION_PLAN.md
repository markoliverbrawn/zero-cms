# Security Remediation Plan

## Scope
This plan addresses the highest-risk issues identified during the independent security review of the application, with emphasis on authentication hardening, secure file access, and abuse prevention.

## Priority 1 — Harden authentication and password-reset flows

### Objective
Reduce the risk of credential stuffing, brute-force attacks, and password-reset abuse.

### Actions
1. Implement centralized rate limiting for authentication endpoints.
   - Apply the existing security helper to login, frontend login, password reset request, and password reset completion routes.
   - Track attempts by a combination of IP address, username, and account identifier.
   - Enforce progressive delays and temporary lockouts after repeated failures.

2. Add server-side throttling and logging.
   - Persist counters in the existing cache/database layer rather than relying only on sleep-based delays.
   - Record lockout events and repeated failures in the audit log.

3. Review error handling for login and reset flows.
   - Ensure the application does not reveal whether an account exists through different response timings or messages.
   - Keep responses consistent and avoid leaking account state.

### Files to review
- src/Modules/Admin/Controllers/LoginController.php
- src/Modules/Admin/Controllers/FrontendLoginController.php
- src/Modules/Admin/Controllers/ForgotController.php
- src/Modules/Admin/Controllers/FrontendForgotController.php
- src/Modules/Admin/Controllers/ResetController.php
- src/Support/Security.php

### Acceptance criteria
- Repeated failed login attempts from the same IP or account trigger rate limiting.
- Password reset requests are throttled per IP and per account.
- Security logs capture lockout and abuse events.

---

## Priority 2 — Harden secure file download handling

### Objective
Prevent path traversal and unauthorized file access in the secure download flow.

### Actions
1. Normalize and validate file paths before access.
   - Resolve the target path against the intended storage root and reject any path that escapes it.
   - Reject symlinks, absolute paths, and unexpected traversal sequences.

2. Enforce strict access controls.
   - Ensure the controller checks authorization before serving a file.
   - Apply the same role checks used elsewhere in the admin area.

3. Improve logging and abuse detection.
   - Log suspicious access attempts and repeated invalid download requests.

### Files to review
- src/Modules/Admin/Controllers/SecureDownloadController.php
- src/Core/Storage/LocalStorageDriver.php

### Acceptance criteria
- A path containing traversal sequences cannot resolve outside the allowed storage root.
- Unauthorized users cannot access protected files.
- Suspicious access attempts are logged.

---

## Priority 3 — Strengthen API authentication protections

### Objective
Reduce the risk of API token abuse and credential leakage.

### Actions
1. Review token handling for rotation and revocation.
   - Ensure API keys or bearer tokens can be revoked and rotated cleanly.
   - Avoid long-lived tokens where possible.

2. Add abuse prevention controls.
   - Apply request rate limiting per API key and per IP.
   - Reject anonymous or malformed token usage consistently.

3. Reduce exposure in logs and error responses.
   - Never return the full token or token metadata in responses.

### Files to review
- src/Http/Controllers/ApiController.php

### Acceptance criteria
- API keys are rate-limited and revocable.
- Token leakage in responses is eliminated.
- Suspicious usage is logged.

---

## Priority 4 — Improve regression coverage

### Objective
Ensure the hardening changes remain effective over time.

### Actions
1. Add or update tests for authentication throttling.
2. Add tests covering path traversal rejection in secure downloads.
3. Add tests for API key abuse handling and unexpected token formats.

### Suggested test targets
- tests/SecurityTest.php
- tests/StorageTest.php
- tests/RouterTest.php

### Acceptance criteria
- Security tests cover the main abuse scenarios.
- A regression failure would occur if the protection is removed.

---

## Recommended implementation order
1. Authentication and reset-flow throttling
2. Secure download path validation
3. API token abuse controls
4. Regression tests and audit logging

## Suggested timeline
- Phase 1: 1–3 days
- Phase 2: 1 day
- Phase 3: 1–2 days
- Phase 4: 1 day

## Verification checklist
- Run security-related tests after implementation.
- Exercise login failure scenarios and confirm throttling triggers.
- Attempt traversal-based secure download requests and confirm they are blocked.
- Review audit logs for lockout and abuse events.
