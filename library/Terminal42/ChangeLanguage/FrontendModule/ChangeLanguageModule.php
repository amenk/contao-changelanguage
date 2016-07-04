<?php

/**
 * changelanguage Extension for Contao Open Source CMS
 *
 * @copyright  Copyright (c) 2008-2016, terminal42 gmbh
 * @author     terminal42 gmbh <info@terminal42.ch>
 * @license    http://opensource.org/licenses/lgpl-3.0.html LGPL
 * @link       http://github.com/terminal42/contao-changelanguage
 */

namespace Terminal42\ChangeLanguage\FrontendModule;

use Contao\FrontendTemplate;
use Contao\PageModel;
use Contao\System;
use Haste\Frontend\AbstractFrontendModule;
use Haste\Generator\RowClass;
use Terminal42\ChangeLanguage\Event\ChangelanguageNavigationEvent;
use Terminal42\ChangeLanguage\Helper\AlternateLinks;
use Terminal42\ChangeLanguage\Helper\LanguageText;
use Terminal42\ChangeLanguage\Helper\UrlParameterBag;
use Terminal42\ChangeLanguage\Navigation\NavigationFactory;
use Terminal42\ChangeLanguage\Navigation\NavigationItem;
use Terminal42\ChangeLanguage\Navigation\PageFinder;

/**
 * @property bool  $hideActiveLanguage
 * @property bool  $hideNoFallback
 * @property bool  $keepUrlParams
 * @property bool  $customLanguage
 * @property array $customLanguageText
 */
class ChangeLanguageModule extends AbstractFrontendModule
{
    /**
     * Template
     * @var string
     */
    protected $strTemplate = 'mod_changelanguage';

    /**
     * @inheritdoc
     */
    public function generate()
    {
        if ('BE' === TL_MODE) {
            return $this->generateWildcard();
        }

        $buffer = parent::generate();

        return '' === $this->Template->items ? '' : $buffer;
    }

    /**
     * @inheritdoc
     */
    protected function compile()
    {
        $currentPage  = $this->getCurrentPage();
        $pageFinder   = new PageFinder();

        if ($this->customLanguage) {
            $languageText = LanguageText::createFromOptionWizard($this->customLanguageText);
        } else {
            $languageText = new LanguageText();
        }

        $navigationFactory = new NavigationFactory($pageFinder, $languageText);

        $navigationItems = $navigationFactory->findNavigationItems(
            $currentPage,
            $this->hideActiveLanguage,
            $this->hideNoFallback
        );

        // Do not generate module or header if there is none or only one link
        if (count($navigationItems) < 2) {
            return;
        }

        $templateItems        = [];
        $headerLinks          = new AlternateLinks();
        $defaultUrlParameters = UrlParameterBag::createFromGlobals();

        foreach ($navigationItems as $item) {
            $urlParameters = clone $defaultUrlParameters;

            if (false === $this->executeHook($item, $urlParameters)) {
                continue;
            }

            $templateItems[] = $this->generateTemplateArray($item, $urlParameters);
            $headerLinks->addFromNavigationItem($item, $urlParameters);
        }

        $this->Template->items = $this->generateNavigationTemplate($templateItems);
        $GLOBALS['TL_HEAD'][]  = $headerLinks->generate();
    }

    /**
     * Generates array suitable for nav_default template.
     *
     * @param NavigationItem  $item
     * @param UrlParameterBag $urlParameterBag
     *
     * @return array
     */
    protected function generateTemplateArray(NavigationItem $item, UrlParameterBag $urlParameterBag)
    {
        return [
            'isActive'  => $item->isCurrentPage(),
            'class'     => 'lang-' . $item->getNormalizedLanguage() . ($item->isDirectFallback() ? '' : ' nofallback') . ($item->isCurrentPage() ? ' active' : ''),
            'link'      => $item->getLabel(),
            'subitems'  => '',
            'href'      => specialchars($item->getHref($urlParameterBag)),
            'pageTitle' => strip_tags($item->getTitle()),
            'accesskey' => '',
            'tabindex'  => '',
            'nofollow'  => false,
            'target'    => ($item->isNewWindow() ? ' target="_blank"' : '') . ' hreflang="' . $item->getLanguageTag() . '" lang="' . $item->getLanguageTag() . '"',
            'item'      => $this,
        ];
    }

    /**
     * @param array $items
     *
     * @return string
     */
    protected function generateNavigationTemplate(array $items)
    {
        RowClass::withKey('class')->addFirstLast()->applyTo($items);

        /** @var FrontendTemplate|object $objTemplate */
        $objTemplate = new FrontendTemplate($this->navigationTpl ?: 'nav_default');

        $objTemplate->setData($this->arrData);
        $objTemplate->level = 'level_1';
        $objTemplate->items = $items;

        return $objTemplate->parse();
    }

    /**
     * @return PageModel
     */
    protected function getCurrentPage()
    {
        global $objPage;

        return $objPage;
    }

    /**
     * Returns false if navigation item should be skipped
     *
     * @param NavigationItem  $navigationItem
     * @param UrlParameterBag $urlParameterBag
     *
     * @return bool
     */
    protected function executeHook(NavigationItem $navigationItem, UrlParameterBag $urlParameterBag)
    {
        // HOOK: allow extensions to modify url parameters
        if (isset($GLOBALS['TL_HOOKS']['changelanguageNavigation'])
            && is_array($GLOBALS['TL_HOOKS']['changelanguageNavigation'])
        ) {
            $event = new ChangelanguageNavigationEvent($navigationItem, $urlParameterBag);

            foreach ($GLOBALS['TL_HOOKS']['changelanguageNavigation'] as $callback) {
                System::importStatic($callback[0])->{$callback[1]}($event);

                if ($event->isPropagationStopped()) {
                    break;
                }
            }

            return !$event->isSkipped();
        }

        return true;
    }
}
