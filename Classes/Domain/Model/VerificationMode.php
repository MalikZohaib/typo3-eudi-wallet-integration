<?php

declare(strict_types=1);

namespace T3Hub\EudiWalletIntegration\Domain\Model;

enum VerificationMode: string
{
    case ClaimsOnly = 'claims_only';
    case AgeOver18 = 'age_over_18';
}
