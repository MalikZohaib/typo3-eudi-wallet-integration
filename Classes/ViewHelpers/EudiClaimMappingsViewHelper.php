<?php

declare(strict_types=1);

namespace T3Hub\EudiWalletIntegration\ViewHelpers;

use TYPO3\CMS\Form\Domain\Model\FormElements\AbstractFormElement;
use TYPO3\CMS\Form\Domain\Model\FormElements\Page;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

final class EudiClaimMappingsViewHelper extends AbstractViewHelper
{
    public function initializeArguments(): void
    {
        $this->registerArgument(
            'element',
            AbstractFormElement::class,
            'EUDI Wallet form element',
            true
        );
    }

    public function render(): string
    {
        /** @var AbstractFormElement $eudiElement */
        $eudiElement = $this->arguments['element'];

        $mappings = [];

        /*
         * The EUDI Wallet element is located inside a Page.
         */
        $page = $eudiElement->getParentRenderable();

        if (!$page instanceof Page) {
            return '{}';
        }

        /*
         * Find the root Page.
         *
         * This allows us to start from the first page
         * instead of only looking at the page containing
         * the EUDI Wallet element.
         */
        while (
            $page->getParentRenderable() instanceof Page
        ) {
            $page =
                $page->getParentRenderable();
        }

        /*
         * Recursively collect EUDI claims from
         * all pages and their elements.
         */
        $this->collectFromPage(
            $page,
            $eudiElement,
            $mappings
        );

        return json_encode(
            $mappings,
            JSON_THROW_ON_ERROR |
            JSON_UNESCAPED_SLASHES |
            JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * Recursively process a Form Page.
     *
     * @param Page $page
     * @param AbstractFormElement $eudiElement
     * @param array<string, string> $mappings
     */
    private function collectFromPage(
        Page $page,
        AbstractFormElement $eudiElement,
        array &$mappings
    ): void {
        /*
         * Page::getElements() is the API available
         * in the TYPO3 version used by this project.
         */
        foreach ($page->getElements() as $element) {
            if (
                !$element instanceof AbstractFormElement
            ) {
                continue;
            }

            /*
             * Don't process the EUDI Wallet element itself.
             */
            if ($element === $eudiElement) {
                continue;
            }

            /*
             * Check whether this element has
             * an EUDI claim configured.
             */
            $properties =
                $element->getProperties();

            $eudiClaim =
                $properties['eudiClaim'] ?? null;

            $identifier =
                $element->getIdentifier();

            if (
                $identifier !== null &&
                trim($identifier) !== '' &&
                $eudiClaim !== null &&
                trim((string)$eudiClaim) !== ''
            ) {
                $mappings[$identifier] =
                    trim((string)$eudiClaim);
            }

            /*
             * If this element is itself a Page,
             * recursively process its elements.
             *
             * This handles nested pages/containers
             * where supported by the Form Framework.
             */
            if ($element instanceof Page) {
                $this->collectFromPage(
                    $element,
                    $eudiElement,
                    $mappings
                );
            }
        }
    }
}