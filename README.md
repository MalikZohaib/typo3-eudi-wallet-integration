# TYPO3 EUDI Wallet Extension

Native TYPO3 adapter for the framework-agnostic PHP EUDI Wallet verifier SDK.

## Target

- TYPO3 CMS 14.3 LTS (built for 14.3.6)
- PHP 8.2–8.5
- Composer mode
- Real-wallet OpenID4VP flow only
- No fake/test-wallet routes or services are included in this extension

The extension keeps protocol code in the `eudi/*` SDK packages. TYPO3 is responsible for configuration, persistence, rendering, frontend-user mapping and frontend session creation.

## Features

- Three configurable verification modes: `login`, `claims_only`, and `age_over_18`.
- Claims-only mode verifies a real wallet and returns/displays the verified claims without creating or logging in a TYPO3 frontend user.
- Age-over-18 mode requests only `age_over_18`, creates no frontend login and no age-result cookie, and exposes a public integration interface for other TYPO3 extensions.
- QR / scan screen rendered from Fluid and overrideable per site.
- Overrideable claims-only result template.
- `felogin` integration with "Login with EUDI Wallet" button for login-mode profiles only.
- Dedicated EUDI Wallet verification content plugin usable for login or claims-only verification.
- Multiple requested claims, optionally mapped into allowed `fe_users` fields when login mode is used.
- Stable wallet identity linking by issuer + subject + credential type.
- Optional user creation and user-field updates.
- Optional verified-claims audit storage.
- Relying-party private-key / certificate-chain file configuration.
- Automatic `x509_hash:` client ID derivation when `clientId` is left blank.
- Per-configuration credential issuer trust anchors.
- Database-backed SDK verification sessions with optimistic locking.
- Separate random browser completion token so a phone callback cannot directly log a different browser in merely by knowing the SDK session ID.

## HAIP 1.0 redirect support

The supplied SDK and TYPO3 adapter support the HAIP 1.0 redirect presentation path with signed Request Objects and `response_mode=direct_post.jwt`. Each verification transaction gets a fresh P-256 ECDH-ES response-encryption key; only the public key is sent in `client_metadata`, while the private JWK remains in the DB-backed verification session until that transaction completes. Wallet responses are accepted with `A128GCM` or `A256GCM`.

The same-device **Open wallet on this device** path additionally uses the OpenID4VP `redirect_uri` response mechanism with a fresh one-time response code and originating-browser binding. The QR path remains available for cross-device testing/use.

Remaining scope: credential `status` / revocation resolution is not implemented by the supplied SD-JWT handler and therefore remains fail-closed; ISO mdoc and `dc_api.jwt` are not integrated into this TYPO3 adapter.

## Install with a local SDK monorepo

Example project layout:

```text
project/
├── composer.json
├── packages/
│   ├── eudi-php-sdk/
│   │   └── packages/
│   │       ├── verifier-core/
│   │       ├── credential-sdjwt/
│   │       ├── trust/
│   │       ├── http/
│   │       └── qr-code/
│   └── eudi_wallet_integration/
└── public/
```

Add path repositories to the TYPO3 root `composer.json`:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "packages/eudi-php-sdk/packages/*",
      "options": {"symlink": true}
    },
    {
      "type": "path",
      "url": "packages/eudi_wallet_integration",
      "options": {"symlink": true}
    }
  ]
}
```

Then:

```bash
ddev composer req t3hub/eudi-wallet-integration:@dev
ddev composer req typo3/cms-felogin:^14.3
ddev typo3 extension:setup
```

Include site set `t3hub/eudi-wallet-integration` in **Sites > Setup** or in the site configuration.

## Global extension configuration

Open **System > Settings > Extension Configuration > eudi_wallet_integration** and configure:

- `publicBaseUrl` — public HTTPS origin reachable from the phone/wallet. With a tunnel, use its HTTPS URL.
- `clientId` — optional. Leave empty to calculate `x509_hash:<base64url sha256 DER leaf cert>`.
- `walletAuthorizationEndpoint` — wallet invocation scheme/endpoint.
- `requestUriMethod` — `get` or `post`.
- `responseMode` — use `direct_post.jwt` for HAIP 1.0 (default). Legacy `direct_post` remains available for the generic OpenID4VP profile.
- `profile` — use `haip_1_0` for the HAIP redirect profile (default).
- `sessionTtlSeconds`.
- `sessionRetentionSeconds` — retention after expiration; run `ddev typo3 eudi-wallet:cleanup` from cron/scheduler.
- `requestPrivateKeyPath`.
- `requestCertificateChainPath` — PEM leaf first, intermediates after it. A trailing self-signed root/trust anchor is stripped from the Request Object `x5c`; the leaf itself must not be self-signed.
- `requestSigningAlgorithm` — ES256 or RS256.
- `feloginEnabled`.
- `feloginConfigurationUid`.

### Key/certificate file paths

Only filesystem paths are stored in TYPO3 configuration. Key contents are never stored in TYPO3 DB records. Relative paths are resolved from the TYPO3 Composer project root; absolute paths and `EXT:` paths are also supported.

Example outside the public docroot:

```text
var/eudi/
├── relying-party-key.pem
└── relying-party-chain.pem
```

The extension rejects key/certificate/trust files located below TYPO3's public web root. When the EUDI registry issues a PKCS#12 (`.p12`) relying-party credential, extract its private key and leaf/intermediate certificate chain to PEM files and point these two settings at them.

## Configure a wallet verification profile

Create a `tx_eudiwalletintegrationintegration_configuration` record on a sysfolder / storage page.

Choose a **Verification mode**:

- `Wallet login (fe_users)` — verifies claims, resolves/provisions a frontend user and creates a TYPO3 FE login session.
- `Verify claims only (no login)` — verifies and exposes the returned claims, but never creates/updates/links `fe_users` and never creates a frontend login session.
- `Age over 18 (integration API, no cookie)` — requests only the boolean `age_over_18` claim. It never creates/updates/links `fe_users` and never creates an age-result cookie.

Then set:

- VCT of the actual SD-JWT credential in the wallet.
- purpose.
- holder binding requirement.
- requested claim rows.
- credential issuer trust anchor rows.
- optionally `store_verification_result` if your privacy policy permits claim persistence.

### HAIP SD-JWT issuer trust

For `profile=haip_1_0`, configure `x509_trust_anchor_path` with the **trusted CA/root certificate PEM** used to anchor SD-JWT VC issuer-signature validation. The older `public_key_path` remains available only for legacy/direct public-key trust outside strict HAIP X.509 mode. The presented SD-JWT VC must contain a leaf-first `x5c` JOSE chain that excludes the trust-anchor certificate; the SDK validates that chain to the separately configured trust-anchor certificate and then verifies the issuer JWT with the leaf certificate key.

Set `authority_key_identifier` to the base64url-encoded RFC 5280 Authority Key Identifier `KeyIdentifier` that should be advertised in the DCQL `trusted_authorities` query. This field helps the Wallet select a credential from an accepted X.509 trust framework; the verifier still performs its own issuer trust-chain validation.

The fe_users storage PID, automatic account creation/update and account-matching fields are relevant only in login mode and are hidden for non-login profiles. Claim mappings are also hidden in age_over_18 mode because that mode always requests only age_over_18.

Each requested claim can optionally target an `fe_users` field. The target is ignored in claims-only mode. Sensitive fields such as `password`, `usergroup`, `uid`, `pid`, enable fields and internal session fields are intentionally blocked from claim mapping.

## felogin integration

Set the UID of an EUDI configuration record whose mode is **Wallet login (fe_users)** in global `feloginConfigurationUid` and include the site set. Claims-only configurations are intentionally ignored by the felogin event listener.

The extension listens to `ModifyLoginFormViewEvent` and adds variables to felogin. Its default felogin `Login.fluid.html` is based on TYPO3 14.3.6 and adds an EUDI button after the normal username/password form.

A site package can override it by setting:

```yaml
felogin.view.templateRootPath: 'EXT:site_package/Resources/Private/Felogin/Templates/'
```

and copying only the modified `Login/Login.fluid.html` there.

## Claims-only verification

For a verification that should **not** log a user in:

1. Create an EUDI Wallet configuration record.
2. Set **Verification mode** to `Verify claims only (no login)`.
3. Add the claims you want to request. `target_field` can stay empty.
4. Select that configuration in the standalone EUDI Wallet content element.

After the real wallet returns a valid presentation, `/eudi-wallet/finish` renders:

```text
EXT:eudi_wallet_integration/Resources/Private/Templates/Wallet/Result.fluid.html
```

The result view receives:

- `configuration`
- `mode`
- `claims` — the raw verified claims array
- `claimRows` — string-normalized claim rows for the bundled template
- `credentials`
- `credential` — first verified credential, when present
- `verifiedAt`
- `returnUrl`

No `FrontendUserProvisioningService` call and no TYPO3 FE session creation occurs in this mode. If `store_verification_result` is enabled, the audit row is stored with `fe_user_uid = 0`.

## Age-over-18 integration API

Use an EUDI configuration whose mode is `age_over_18`. In this mode the extension deliberately ignores claim-mapping rows and requests only:

```text
age_over_18
```

The EUDI extension does **not** create or persist an age-result cookie. A consuming extension can inject:

```php
use T3Hub\EudiWalletIntegration\Contract\AgeOver18VerificationInterface;

public function __construct(
    private readonly AgeOver18VerificationInterface $ageVerification,
) {}
```

Start verification from the consuming extension:

```php
$startUrl = $this->ageVerification->startUrl(
    $request,
    configurationUid: 12,
    returnUrl: (string)$request->getUri(),
);
```

After successful wallet completion the EUDI extension redirects back to `returnUrl` and adds:

```text
?eudi_age_verification=<opaque-session-id>
```

The consuming extension resolves the result on that request:

```php
$result = $this->ageVerification->resolveFromRequest($request);

if ($result !== null && $result->over18) {
    // The consuming extension may create its own signed cookie or other state.
}
```

`resolveFromRequest()` validates that the reference belongs to a completed, unexpired `age_over_18` verification and to the same browser that initiated the wallet flow. The returned DTO exposes only the boolean result, verification time and opaque session id; it does not expose date of birth, PID identifiers or the full credential.

The existing short-lived `eudi_wallet_integration_binding` browser-binding cookie used by the verification protocol remains part of the wallet flow. No additional/persistent **age-result** cookie is created by this mode.

## Override the QR / scan screen

The default QR template is:

```text
EXT:eudi_wallet_integration/Resources/Private/Templates/Wallet/Scan.fluid.html
```

The default claims-only result template is:

```text
EXT:eudi_wallet_integration/Resources/Private/Templates/Wallet/Result.fluid.html
```

Override site settings:

```yaml
eudiWalletIntegration.view.templateRootPath: 'EXT:site_package/Resources/Private/EudiWalletIntegration/Templates/'
eudiWalletIntegration.view.partialRootPath: 'EXT:site_package/Resources/Private/EudiWalletIntegration/Partials/'
eudiWalletIntegration.view.layoutRootPath: 'EXT:site_package/Resources/Private/EudiWalletIntegration/Layouts/'
```

Then create:

```text
Resources/Private/EudiWalletIntegration/Templates/Wallet/Scan.fluid.html
```

The view receives:

- `configuration`
- `session`
- `qrCodeDataUri`
- `authorizationRequestUri`
- `openWalletUrl` — marks the transaction as HAIP same-device before invoking the wallet
- `statusUrl`
- `finishUrl`
- `requestedClaims`

## Protocol endpoints

The current SDK hard-codes these request-by-reference/response paths relative to `publicBaseUrl`, so the TYPO3 middleware serves exactly:

```text
GET|POST /wallet/request.jwt/{sessionId}
POST     /wallet/direct_post
```

Browser-only endpoints are:

```text
GET /eudi-wallet/start
GET /eudi-wallet/scan
GET /eudi-wallet/open
GET /eudi-wallet/redirect
GET /eudi-wallet/status
GET /eudi-wallet/finish
```

`/eudi-wallet/open` is used only for the explicit same-device wallet link. `/eudi-wallet/redirect` consumes the one-time response code returned through the Wallet and confirms that the redirect arrived in the same browser session that initiated the request.

The browser token is random, stored only as SHA-256 in the DB and is **not** put in the QR code.

## Cross-device verification/login flow

```text
TYPO3 browser
    ↓ start
DB-backed verification session + browser secret
    ↓
QR (contains only OpenID4VP wallet authorization URI)
    ↓
Real wallet
    ↓ request_uri
TYPO3 /wallet/request.jwt/{id}
    ↓
Wallet user consent
    ↓ direct_post.jwt (`response=<JWE>`)
TYPO3 verifier decrypts + validates credential
    ↓
Browser polling sees verified
    ↓ /finish + browser secret
If mode=login:
    map/link fe_users → create TYPO3 FE session cookie

If mode=claims_only:
    render verified claims → no fe_users / no login cookie
```

## Same-device HAIP flow

```text
TYPO3 scan page
    ↓ Open wallet on this device
/eudi-wallet/open marks same_device=1
    ↓
Wallet processes signed request_uri Request Object
    ↓
POST /wallet/direct_post with response=<JWE>
    ↓
Verifier authenticates/decrypts JWE and validates presentation
    ↓
HTTP 200 JSON { redirect_uri: ...response_code=<fresh secret> }
    ↓ Wallet follows redirect
/eudi-wallet/redirect
    ├─ verifies one-time response code
    └─ verifies initiating browser-binding cookie
    ↓
/eudi-wallet/finish may consume the verified result
```

If the Wallet never follows the redirect or it returns through a different browser session, same-device completion remains blocked.

## Security / privacy notes

- Keep the RP private key and issuer public/trust material outside `public/`.
- Use a public HTTPS `publicBaseUrl` for real wallets.
- Never log `vp_token`, SD-JWT disclosures, private keys or full verified claim sets.
- Enable `store_verification_result` only with a documented retention/legal basis.
- For login, the extension uses the verified JWT `sub` claim when present. If the credential omits `sub`, configure **Stable wallet identity claim** with an issuer-scoped stable identifier (for example `personal_administrative_number` for a PID when provided). Mutable profile attributes such as names or addresses should not be used as identity keys.
- `match_claim` is only a bootstrap/fallback account link; after first success the dedicated wallet identity table is used.
- Add infrastructure rate limiting for `/wallet/*` and `/eudi-wallet/*` in production.

## Files to study first

1. `Classes/Middleware/WalletFrontendMiddleware.php`
2. `Classes/Middleware/WalletProtocolMiddleware.php`
3. `Classes/Service/VerifierFactory.php`
4. `Classes/Infrastructure/DatabaseSessionStore.php`
5. `Classes/Service/FrontendUserProvisioningService.php`
6. `Resources/Private/Templates/Wallet/Scan.fluid.html`
7. `Resources/Private/Felogin/Templates/Login/Login.fluid.html`

## Database cleanup

Verification sessions are deliberately stored in TYPO3's database. Run periodically:

```bash
ddev typo3 eudi-wallet:cleanup
```

The retention window is controlled by `sessionRetentionSeconds` in Extension Configuration.
