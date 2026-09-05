<?php

declare(strict_types=1);

namespace T3Hub\EudiWalletIntegration\Domain;

use DateTimeImmutable;

/**
 * Privacy-minimal result exposed to consuming TYPO3 extensions.
 *
 * No PID subject, date of birth, personal administrative number, or full
 * credential is exposed because an age gate only needs the boolean result.
 */
final readonly class AgeOver18VerificationResult
{
    public function __construct(
        public string $sessionId,
        public bool $over18,
        public DateTimeImmutable $verifiedAt,
    ) {
    }
}
