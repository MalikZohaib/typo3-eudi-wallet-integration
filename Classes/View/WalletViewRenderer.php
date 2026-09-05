<?php

declare(strict_types=1);

namespace T3Hub\EudiWalletIntegration\View;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;

final readonly class WalletViewRenderer
{
    public function __construct(private ViewFactoryInterface $viewFactory)
    {
    }

    /** @param array<string, mixed> $variables */
    public function render(string $template, ServerRequestInterface $request, array $variables = []): string
    {
        [$templates, $partials, $layouts] = $this->paths($request);
        $view = $this->viewFactory->create(new ViewFactoryData(
            templateRootPaths: $templates,
            partialRootPaths: $partials,
            layoutRootPaths: $layouts,
            request: $request,
        ));
        $view->assignMultiple($variables);
        return $view->render($template);
    }

    /** @return array{0:list<string>,1:list<string>,2:list<string>} */
    private function paths(ServerRequestInterface $request): array
    {
        $template = 'EXT:eudi_wallet_integration/Resources/Private/Templates/';
        $partial = 'EXT:eudi_wallet_integration/Resources/Private/Partials/';
        $layout = 'EXT:eudi_wallet_integration/Resources/Private/Layouts/';
        $site = $request->getAttribute('site');
        if ($site instanceof Site) {
            $settings = $site->getSettings();
            $template = (string)$settings->get('eudiWalletIntegration.view.templateRootPath', $template);
            $partial = (string)$settings->get('eudiWalletIntegration.view.partialRootPath', $partial);
            $layout = (string)$settings->get('eudiWalletIntegration.view.layoutRootPath', $layout);
        }
        return [
            ['EXT:eudi_wallet_integration/Resources/Private/Templates/', $template],
            ['EXT:eudi_wallet_integration/Resources/Private/Partials/', $partial],
            ['EXT:eudi_wallet_integration/Resources/Private/Layouts/', $layout],
        ];
    }
}
