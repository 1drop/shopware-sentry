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
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RavenHandler;
use Monolog\Logger;
use OdSentry\Components\SentryClient;
use Shopware\Components\Plugin;

class OdSentry extends Plugin
{
    /**
     * @var SentryClient
     */
    protected $ravenClient;

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Front_StartDispatch' => 'onStartDispatch',
//            'Enlight_Controller_Action_PreDispatch_Frontend_Error' => 'onPreDispatchError',
            'Enlight_Controller_Action_PreDispatch_Backend_Error' => 'onPreDispatchBackendError',
            'Theme_Compiler_Collect_Plugin_Javascript' => 'addJsFiles',
            'Enlight_Controller_Action_PostDispatch_Frontend' => 'onPostDispatchFrontend',
            'Enlight_Controller_Front_DispatchLoopShutdown' => 'onDispatchLoopShutdown',
            'Shopware_Console_Add_Command' => 'onStartDispatch'
        ];
    }


    /**
     * Use the autoloader from the Raven library to load all necessary classes
     */
    public function onStartDispatch()
    {
        require_once __DIR__ . '/vendor/autoload.php';
        if (Shopware()->Config()->getByNamespace('OdSentry', 'logPhp')) {
            $privateDsn = Shopware()->Config()->getByNamespace('OdSentry', 'privateDsn');
            $this->ravenClient = new SentryClient($privateDsn, [
                'release' => \Shopware::VERSION,
                'environment' => $this->container->getParameter('kernel.environment'),
                'install_default_breadcrumb_handlers' => false
            ]);
            $this->ravenClient->setContainer($this->container);

            // Register additional handler for corelogger and pluginlogger
            $ravenHandler = new RavenHandler($this->ravenClient, Logger::WARNING);
            $ravenHandler->setFormatter(new LineFormatter("%message% %context% %extra%\n"));
            /** @var Logger $coreLogger */
            $coreLogger = $this->container->get('corelogger');
            $coreLogger->pushHandler($ravenHandler);
            /** @var Logger $pluginLogger */
            $pluginLogger = $this->container->get('pluginlogger');
            $pluginLogger->pushHandler($ravenHandler);

            // Register global error handler
            $errorHandler = new \Raven_ErrorHandler($this->ravenClient);
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
        if ($this->ravenClient) {
            $error = $subject->Request()->getParam('error_handler');
            if ($error && $error->exception) {
                $this->ravenClient->captureException($error->exception);
            }
        }
    }

//    /**
//     * @param Enlight_Event_EventArgs $args
//     *
//     * @return void
//     */
//    public function onPreDispatchError(Enlight_Event_EventArgs $args)
//    {
//        /** @var \Shopware_Controllers_Frontend_Error $subject */
//        $subject = $args->getSubject();
//        if ($this->ravenClient) {
//            $error = $subject->Request()->getParam('error_handler');
//            if ($error && $error->exception) {
//                $id = $this->ravenClient->captureException($error->exception);
//                $pluginConfig = $this->getConfig();
//                if (!$pluginConfig['sentryUserfeedback']) {
//                    return;
//                }
//                $this->container->get('template')->assignGlobal('sentryId', $id);
//                $this->container->get('template')->assignGlobal('sentryUrlFeedback', $pluginConfig['sentryUrlFeedback']);
//                $this->container->get('template')->addTemplateDir($this->getPath() . '/Resources/views');
//            }
//        }
//    }

    /**
     *
     *
     * @return ArrayCollection
     */
    public function addJsFiles()
    {
        // todo: include raven js before jquery plugins to detect errors in plugin bootstrapping
        $jsDir = __DIR__ . '/Resources/views/frontend/_public/src/js/';
        $jsFiles = [
            $jsDir . 'vendor/raven.min.js',
        ];

        return new ArrayCollection($jsFiles);
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
        if (empty($exceptions) || !$this->ravenClient instanceof \Raven_Client) {
            return;
        }
        foreach ($exceptions as $exception) {
            $this->ravenClient->captureException($exception);
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
