# Changelog

## 0.3.4

- Added verification mode `age_over_18`.
- Age mode always requests only the boolean `age_over_18` claim, regardless of configured claim-mapping rows.
- Added public `AgeOver18VerificationInterface` for other TYPO3 extensions.
- The age flow redirects back to the caller with an opaque `eudi_age_verification` session reference; the consuming extension resolves it through the interface.
- The interface validates verification status, configuration mode, expiry and originating-browser binding before exposing the boolean result.
- No age-result cookie is created by `eudi_wallet_integration`; cookie/storage policy remains the consuming extension's responsibility.

## 0.3.0

- Added HAIP 1.0 redirect-flow support using `response_mode=direct_post.jwt`.
- Added per-authorization-request P-256 / ECDH-ES response-encryption keys and JWE decryption for `A128GCM` and `A256GCM`.
- Wired encrypted-response processing through the SDK `VerifierService` instead of TYPO3 protocol code.
- Added response-encryption `kid` lookup to all session-store implementations.
- Added HAIP DCQL `trusted_authorities` / `aki` configuration.
- Request Object `x5c` no longer includes a trailing self-signed trust anchor; self-signed leaf certificates are rejected.
- Added HAIP same-device redirect/session binding with a fresh one-time response code and browser-binding cookie.
- Kept QR-code polling as the existing cross-device flow.
- Bumped extension version to 0.3.0.

## 0.2.0

- Added configuration mode `claims_only` alongside the existing `login` mode.
- Claims-only mode verifies a real EUDI Wallet presentation and renders the verified claims without creating, updating, linking or logging in a TYPO3 `fe_users` record.
- Added overrideable `Wallet/Result.fluid.html` result template.
- Login-only account matching claims are no longer silently added to DCQL requests in claims-only mode.
- `felogin` integration now accepts only configurations whose mode is `login`.
- Standalone content element automatically renders either **Login with EUDI Wallet** or **Verify with EUDI Wallet**.
- Optional verification/audit storage works in claims-only mode with `fe_user_uid = 0`.
- Added TCA mode selector and hides `fe_users`-specific configuration fields in claims-only mode.
- Updated QR screen wording so the same flow works for login and non-login verification.
