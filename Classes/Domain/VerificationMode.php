<?php

declare(strict_types=1);

namespace T3Hub\EudiWalletIntegration\Domain;

enum VerificationMode: string
{
    case Login = 'login';
    case ClaimsOnly = 'claims_only';
    case AgeOver18 = 'age_over_18';

    public function requiresFrontendUser(): bool
    {
        return $this === self::Login;
    }
}
