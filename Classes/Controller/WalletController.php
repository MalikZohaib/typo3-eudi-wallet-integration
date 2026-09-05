<?php

declare(strict_types=1);

namespace T3Hub\EudiWalletIntegration\Controller;

use Psr\Http\Message\ResponseInterface;
use T3Hub\EudiWalletIntegration\Http\FrontendEndpointUrlBuilder;
use T3Hub\EudiWalletIntegration\Repository\ConfigurationRepository;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

final class WalletController extends ActionController
{
    public function __construct(
        private readonly FrontendEndpointUrlBuilder $endpointUrlBuilder,
        private readonly ConfigurationRepository $configurationRepository,
    ) {
    }

    public function indexAction(): ResponseInterface
    {
        $configurationUid = (int)($this->request->getAttribute('currentContentObject')?->data['tx_eudiwalletintegration_configuration'] ?? 0);
        $currentUrl = (string)$this->request->getUri();
        $configuration = null;
        if ($configurationUid > 0) {
            try {
                $configuration = $this->configurationRepository->get($configurationUid);
            } catch (\Throwable) {
                // Keep the content element renderable and show its normal configuration error state.
            }
        }

        $this->view->assignMultiple([
            'configurationUid' => $configurationUid,
            'configuration' => $configuration,
            'mode' => $configuration?->mode->value,
            'loginMode' => $configuration?->isLoginMode() ?? false,
            'ageOver18Mode' => $configuration?->isAgeOver18Mode() ?? false,
            'startUrl' => $this->endpointUrlBuilder->url($this->request, '/eudi-wallet/start', [
                'configuration' => $configurationUid,
                'return_url' => $currentUrl,
            ]),
        ]);
        return $this->htmlResponse();
    }
}
