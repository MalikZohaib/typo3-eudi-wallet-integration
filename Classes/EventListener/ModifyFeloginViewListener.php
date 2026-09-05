<?php

declare(strict_types=1);

namespace T3Hub\EudiWalletIntegration\EventListener;

use T3Hub\EudiWalletIntegration\Configuration\ExtensionSettings;
use T3Hub\EudiWalletIntegration\Http\FrontendEndpointUrlBuilder;
use T3Hub\EudiWalletIntegration\Repository\ConfigurationRepository;
use TYPO3\CMS\FrontendLogin\Event\ModifyLoginFormViewEvent;

final readonly class ModifyFeloginViewListener
{
    public function __construct(
        private ExtensionSettings $settings,
        private FrontendEndpointUrlBuilder $endpointUrlBuilder,
        private ConfigurationRepository $configurationRepository,
    ) {
    }

    public function __invoke(ModifyLoginFormViewEvent $event): void
    {
        if (!$this->settings->bool('feloginEnabled', true)) {
            $event->getView()->assign('eudiWalletIntegrationEnabled', false);
            return;
        }

        $configurationUid = $this->settings->int('feloginConfigurationUid', 0);
        if ($configurationUid <= 0) {
            $event->getView()->assign('eudiWalletIntegrationEnabled', false);
            return;
        }

        try {
            $configuration = $this->configurationRepository->get($configurationUid);
        } catch (\Throwable) {
            $event->getView()->assign('eudiWalletIntegrationEnabled', false);
            return;
        }

        // felogin is intentionally only a consumer of configurations in login mode.
        // Claims-only profiles belong on the standalone EUDI Wallet content element.
        if (!$configuration->isLoginMode()) {
            $event->getView()->assign('eudiWalletIntegrationEnabled', false);
            return;
        }

        $returnUrl = (string)$event->getRequest()->getUri();
        $event->getView()->assignMultiple([
            'eudiWalletIntegrationEnabled' => true,
            'eudiWalletIntegrationStartUrl' => $this->endpointUrlBuilder->url($event->getRequest(), '/eudi-wallet/start', [
                'configuration' => $configurationUid,
                'return_url' => $returnUrl,
            ]),
        ]);
    }
}
