# Architecture

```text
TYPO3 / felogin / Fluid
          │
          │ thin adapter
          ▼
T3Hub\EudiWalletIntegration
          │
          ├── DB SessionStoreInterface adapter
          ├── TYPO3 verification profile + claim configuration
          ├── same-device browser/session binding
          ├── claims-only completion path
          ├── optional TYPO3 frontend user provisioning/login path
          └── Fluid QR + result views
          │
          ▼
eudi/verifier-core
          ├── HAIP/OpenID4VP request orchestration
          ├── per-request P-256 response encryption key
          ├── direct_post.jwt JWE decryption
          ├── DCQL response matching
          ├── eudi/credential-sdjwt
          └── eudi/trust
```

No `eudi/testing` dependency exists in the TYPO3 extension.

## HAIP redirect request path

```text
VerifierService::start()
  ├─ state + nonce
  ├─ EphemeralResponseKeyGenerator -> P-256 ECDH-ES key pair
  └─ SessionStore::create() -> private key stays server-side

Wallet GET/POST /wallet/request.jwt/{session}
  └─ AuthorizationRequestFactory
       └─ HaipAuthorizationRequestParameters
            ├─ response_type=vp_token
            ├─ response_mode=direct_post.jwt
            ├─ DCQL
            └─ client_metadata.jwks -> public ephemeral key
       └─ X509RequestObjectSigner -> signed JAR
```

The ephemeral key is generated once for the authorization transaction, not each time the Request Object is fetched.

## Encrypted response path

```text
POST /wallet/direct_post
  body: response=<compact JWE>
      │
      ├─ protected kid -> select DB transaction/configuration
      │
      ▼
VerifierService::handleDirectPost()
      ├─ findByResponseEncryptionKid(kid)
      ├─ DirectPostJwtResponseParser
      │    ├─ ECDH-ES / P-256
      │    ├─ A128GCM or A256GCM
      │    ├─ authenticated decryption
      │    └─ state verification
      ├─ match DCQL vp_token query IDs
      ├─ verify SD-JWT/KB-JWT
      └─ complete session + clear ephemeral private key
```

The TYPO3 middleware reads the untrusted protected `kid` only because it must select the profile-specific issuer trust configuration before creating the SDK verifier. Credential data and decrypted state are not trusted there; those checks remain in the SDK.

## Same-device binding

The scan screen has two invocation styles:

- QR code: cross-device; the browser keeps polling with its separate browser token.
- **Open wallet on this device**: goes through `/eudi-wallet/open`, marks the transaction as same-device, then invokes the wallet.

For same-device transactions the Response URI creates a fresh one-time response code and returns `/eudi-wallet/redirect?...&response_code=...` to the Wallet. The redirect endpoint requires:

1. the unconsumed response code, and
2. the browser-binding cookie created when the verification started.

Until that redirect succeeds, `/status` hides a cryptographically verified result and `/finish` refuses to consume it. This prevents a relayed presentation from completing in a different browser session.

## Verification modes

A configuration can use `login`, `claims_only`, or `age_over_18`. The EUDI SDK is unaware of this TYPO3-level distinction; after cryptographic verification TYPO3 either provisions/links a frontend user, renders claims, or returns a privacy-minimal age-verification reference to a consuming extension.

## Age-over-18 integration mode

`VerificationMode::AgeOver18` is a privacy-minimal non-login mode. `Configuration::requestedClaims()` hard-codes the DCQL claim set to `age_over_18`, so backend claim mappings cannot expand disclosure for this mode.

After a verified presentation, `WalletFrontendMiddleware` consumes the normal browser-completion token and redirects back to the validated caller `return_url` with an opaque `eudi_age_verification` session reference. It does not create an age-result cookie.

Other TYPO3 extensions integrate through `T3Hub\EudiWalletIntegration\Contract\AgeOver18VerificationInterface`. `resolveFromRequest()` checks the session mode, verification status, expiry, browser-consumption state and originating-browser binding before returning `AgeOver18VerificationResult`. The DTO exposes only the over-18 boolean, verification timestamp and opaque session ID.
