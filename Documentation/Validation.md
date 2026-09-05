# Validation report

Generated for TYPO3 14.3 LTS / PHP 8.2–8.5.

Static checks for extension version 0.3.0:

- PHP syntax lint: all PHP files passed.
- Composer JSON files: valid JSON.
- SDK HAIP request flow is wired through `VerifierService` and `AuthorizationRequestFactory`; the per-request P-256 response-encryption key is created once in `VerifierService::start()` and persisted with the verification session.
- `response_mode=direct_post.jwt` is handled by `VerifierService::handleDirectPost()` using authenticated JWE decryption before `state` or credential data are trusted.
- JWE response processing accepts HAIP algorithms `ECDH-ES` with P-256 and `A128GCM` / `A256GCM` only.
- The JWE protected `kid` is used only to select the server-side transaction/private key; decrypted `state` is then checked against that transaction.
- DCQL `vp_token` response objects are matched against the credential query IDs that were requested.
- Request Object signing excludes a trailing self-signed trust anchor from `x5c` and rejects a self-signed leaf signing certificate.
- HAIP `trusted_authorities` with `type=aki` can be configured per credential issuer trust anchor.
- In HAIP mode, SD-JWT VC issuer signatures require a leaf-first `x5c` JOSE chain; the presented trust anchor is rejected, intermediate signatures are checked, the final presented certificate must chain to the configured trust-anchor public key, and the issuer JWT is verified with the leaf certificate key.
- The TYPO3 DB session store persists the response-encryption `kid` and private JWK only while the verifier transaction is pending. Terminal sessions clear the response-encryption key material.
- Same-device invocation is explicit through `/eudi-wallet/open`; successful wallet POST processing returns a fresh one-time `redirect_uri`, and `/eudi-wallet/redirect` requires both the response code and the originating browser-binding cookie before the frontend may consume the result.
- QR-code invocation remains a cross-device flow and does not mark the transaction as same-device.
- No dependency or class reference to `eudi/testing`, `TestWalletService`, `DemoIssuer` or the demo HMAC request signer exists in the TYPO3 extension.

## Runtime verification still required

The build environment used for this archive does not contain a complete TYPO3 installation or your production relying-party/issuer certificates. After installing in TYPO3, run at minimum:

```bash
ddev composer update eudi/verifier-core web-token/jwt-library t3hub/eudi-wallet-integration -W
ddev typo3 extension:setup
ddev typo3 cache:flush
```

For an existing installation, run TYPO3 **Analyze Database Structure** / `extension:setup` so the new session and trust-anchor columns are created.

Then validate:

1. Cross-device QR: wallet fetches signed Request Object, returns `direct_post.jwt`, browser polling completes.
2. Same-device link: wallet returns `direct_post.jwt`, receives `redirect_uri`, follows it in the initiating browser session, and only then can `/finish` consume the verification.
3. An invalid `kid`, JWE algorithm, encryption method, `state`, response code, or browser binding is rejected.
4. Both `A128GCM` and `A256GCM` wallet responses decrypt successfully.

## Remaining scope

- SD-JWT VC Token Status List (`status.status_list`) resolution is implemented for JWT Status List Tokens (`application/statuslist+jwt`), including signature, time, subject, decompression, index and VALID/INVALID/SUSPENDED checks. CWT Status List Tokens are not yet supported.
- ISO mdoc verification is not integrated into this TYPO3 adapter.
- `dc_api.jwt` / W3C Digital Credentials API is not implemented; this archive implements the HAIP redirect flow using `direct_post.jwt`.
- A real wallet/conformance suite still requires an ecosystem-valid, non-self-signed relying-party certificate and appropriate issuer trust material.
