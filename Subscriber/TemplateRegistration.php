<?php
declare(strict_types=1);
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2019 Hans Hoechtl <hhoechtl@1drop.de>
 *  All rights reserved
 ***************************************************************/
namespace OdSentry\Subscriber;

use Doctrine\Common\Collections\ArrayCollection;
use Enlight\Event\SubscriberInterface;
use Enlight_Event_EventArgs;
use OdSentry\OdSentry;
use Shopware\Components\Theme\LessDefinition;

class TemplateRegistration implements SubscriberInterface
{
    /**
     * @var string
     */
    private $pluginDirectory;
    /**
     * @var \Enlight_Template_Manager
     */
    private $templateManager;

    /**
     * TemplateRegistration constructor.
     *
     * @param \Enlight_Template_Manager $templateManager
     * @param                           $pluginDirectory
     */
    public function __construct(\Enlight_Template_Manager $templateManager, string $pluginDirectory)
    {
        $this->pluginDirectory = $pluginDirectory;
        $this->templateManager = $templateManager;
    }

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PreDispatch'                      => 'onPreDispatch',
            'Enlight_Controller_Action_PostDispatchSecure_Frontend'      => 'onPostDispatchFrontend',
            'Theme_Compiler_Collect_Plugin_Javascript'                   => 'addJsFiles',
            'Theme_Compiler_Collect_Plugin_Less'                         => 'addLessFiles',
            'Theme_Compiler_Collect_Javascript_Files_FilterResult'       => 'sortJs',
        ];
    }

    /**
     * Include templates
     */
    public function onPreDispatch()
    {
        $this->templateManager->addTemplateDir($this->pluginDirectory . '/Resources/views');
    }

    /**
     * We add templates from our plugin to include raven-js initialization after the JS libs
     *
     * @param \Enlight_Event_EventArgs $args
     */
    public function onPostDispatchFrontend(\Enlight_Event_EventArgs $args)
    {
        /** @var \Enlight_Controller_Action $controller */
        $controller = $args->get('subject');
        $controller->View()->addTemplateDir($this->pluginDirectory . '/Resources/views/');
    }

    /**
     * Add the raven-js library to the compiled JS.
     * It will we reordered by the sortJs function to be included
     * before the bootstrapping of most JS to track these errors too.
     *
     * @return ArrayCollection
     */
    public function addJsFiles()
    {
        $jsFiles = [];
        $jsDir = $this->pluginDirectory . '/Resources/views/frontend/_public/src/js/';
        if (Shopware()->Config()->getByNamespace(
            OdSentry::PLUGIN_NAME,
                'sentryLogJs'
        ) || Shopware()->Config()->getByNamespace(OdSentry::PLUGIN_NAME, 'sentryUserfeedback')) {
            $jsFiles[] = $jsDir . 'vendor/raven.min.js';
        }
        return new ArrayCollection($jsFiles);
    }

    /**
     * @return ArrayCollection
     */
    public function addLessFiles()
    {
        $less = new LessDefinition(
            [],
            [$this->pluginDirectory . '/Resources/views/frontend/_public/src/less/all.less']
        );

        return new ArrayCollection([$less]);
    }

    /**
     * Sort the raven-js library to the front of the JS compilation pipeline
     * so that it can track errors in the initialization of other JS libraries.
     *
     * @param  Enlight_Event_EventArgs $args
     * @return array
     */
    public function sortJs(Enlight_Event_EventArgs $args)
    {
        $files = $args->getReturn();
        $fileIdx = -1;
        foreach ($files as $idx => $file) {
            if (strpos($file, 'raven.min.js') !== false && strpos($file, OdSentry::PLUGIN_NAME) !== false) {
                $fileIdx = $idx;
                break;
            }
        }
        if ($fileIdx > -1) {
            $tmp = array_splice($files, $fileIdx, 1);
            // the 5th position is usually after the vendor libraries
            array_splice($files, 5, 0, $tmp);
        }
        return $files;
    }
}
