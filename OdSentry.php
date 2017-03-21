<?php
/**
 * (c) Onedrop GmbH & Co. KG <info@1drop.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OdSentry;

use Doctrine\Common\Collections\ArrayCollection;
use Enlight_Event_EventArgs;
use OdSentry\Components\SentryClient;
use Shopware\Components\Plugin;
use Shopware\Components\Plugin\Context\ActivateContext;
use Shopware\Components\Plugin\Context\InstallContext;

class OdSentry extends Plugin
{
    /**
     * @var SentryClient
     */
    protected $sentryClient;

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Front_StartDispatch' => 'onStartDispatch',
            'Enlight_Controller_Action_PreDispatch_Frontend_Error' => 'onPreDispatchError',
            'Enlight_Controller_Action_PreDispatch_Backend_Error' => 'onPreDispatchBackendError',
            'Theme_Compiler_Collect_Plugin_Javascript' => 'addJsFiles',
            'Theme_Compiler_Collect_Javascript_Files_FilterResult' => 'sortJs',
            'Enlight_Controller_Action_PostDispatch_Frontend' => 'onPostDispatchFrontend',
            'Enlight_Controller_Front_DispatchLoopShutdown' => 'onDispatchLoopShutdown',
            'Shopware_Console_Add_Command' => 'onStartDispatch'
        ];
    }

    /**
     * Theme must be recompiled to include JS
     *
     * @param ActivateContext $context
     */
    public function activate(ActivateContext $context)
    {
        $context->scheduleClearCache(InstallContext::CACHE_LIST_ALL);
    }

    /**
     * Use the autoloader from the Raven library to load all necessary classes
     */
    public function onStartDispatch()
    {
        require_once __DIR__ . '/vendor/autoload.php';
        if (Shopware()->Config()->getByNamespace('OdSentry', 'sentryLogPhp')) {
            $privateDsn = Shopware()->Config()->getByNamespace('OdSentry', 'sentryPrivateDsn');
            $this->sentryClient = new SentryClient($privateDsn, [
                'release' => \Shopware::VERSION,
                'environment' => $this->container->getParameter('kernel.environment'),
                'install_default_breadcrumb_handlers' => false
            ]);
            $this->sentryClient->setContainer($this->container);

            // Register global error handler
            $errorHandler = new \Raven_ErrorHandler($this->sentryClient);
            $errorHandler->registerExceptionHandler();
            $errorHandler->registerShutdownFunction();
            // Restore Shopware default error handler
            restore_error_handler();
            restore_exception_handler();
        }
    }

    /**
     * @param Enlight_Event_EventArgs $args
     *
     * @return void
     */
    public function onPreDispatchBackendError(Enlight_Event_EventArgs $args)
    {
        /** @var \Shopware_Controllers_Frontend_Error $subject */
        $subject = $args->getSubject();
        if ($this->sentryClient) {
            $error = $subject->Request()->getParam('error_handler');
            if ($error && $error->exception) {
                $this->sentryClient->captureException($error->exception);
            }
        }
    }

    /**
     * @param Enlight_Event_EventArgs $args
     *
     * @return void
     */
    public function onPreDispatchError(Enlight_Event_EventArgs $args)
    {
        /** @var \Shopware_Controllers_Frontend_Error $subject */
        $subject = $args->getSubject();
        if ($this->sentryClient) {
            $error = $subject->Request()->getParam('error_handler');
            if ($error && $error->exception) {
                $id = $this->sentryClient->captureException($error->exception);
                if (!Shopware()->Config()->getByNamespace('OdSentry', 'sentryUserfeedback')) {
                    return;
                }
                $this->container->get('template')->assignGlobal('sentryId', $id);
                $this->container->get('template')->addTemplateDir($this->getPath() . '/Resources/views/');
            }
        }
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
        $jsDir = __DIR__ . '/Resources/views/frontend/_public/src/js/';
        if (Shopware()->Config()->getByNamespace('OdSentry', 'sentryLogJs') || Shopware()->Config()->getByNamespace('OdSentry', 'sentryUserfeedback')) {
            $jsFiles[] = $jsDir . 'vendor/raven.min.js';
        }
        return new ArrayCollection($jsFiles);
    }

    /**
     * Sort the raven-js library to the front of the JS compilation pipeline
     * so that it can track errors in the initialization of other JS libraries.
     *
     * @param Enlight_Event_EventArgs $args
     * @return array
     */
    public function sortJs(\Enlight_Event_EventArgs $args)
    {
        $files = $args->getReturn();
        $fileIdx = -1;
        foreach ($files as $idx => $file) {
            if (strpos($file, 'raven.min.js') !== false && strpos($file, 'OdSentry') !== false) {
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

    /**
     * Like the \Shopware_Plugins_Core_ErrorHandler_Bootstrap we want to catch all errors
     * that occured during a request and send them to Sentry
     *
     * @param \Enlight_Controller_EventArgs $args
     */
    public function onDispatchLoopShutdown(\Enlight_Controller_EventArgs $args)
    {
        $response = $args->getSubject()->Response();
        $exceptions = $response->getException();
        if (empty($exceptions) || !$this->sentryClient) {
            return;
        }
        foreach ($exceptions as $exception) {
            $this->sentryClient->captureException($exception);
        }
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
        $request = $controller->Request();
        $response = $controller->Response();
        $view = $controller->View();

        if (!$request->isDispatched() || $response->isException() || $request->getModuleName() !== 'frontend' || !$view->hasTemplate()) {
            return;
        }
        $view->addTemplateDir($this->getPath() . '/Resources/views/');
    }
}
