<?php
/**
 * (c) Onedrop GmbH & Co. KG <info@1drop.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OdSentry;

use Doctrine\Common\Collections\ArrayCollection;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RavenHandler;
use Monolog\Logger;
use Shopware\Components\Plugin;

class OdSentry extends Plugin
{
    /**
     * @var \Raven_Client
     */
    protected $ravenClient;

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'Theme_Compiler_Collect_Plugin_Javascript' => 'addJsFiles',
            'Enlight_Controller_Action_PostDispatch_Frontend' => 'onPostDispatchFrontend',
            'Enlight_Controller_Front_StartDispatch' => 'onStartDispatch',
            'Enlight_Controller_Front_DispatchLoopShutdown' => 'onDispatchLoopShutdown',
            'Shopware_Console_Add_Command' => 'onStartDispatch'
        ];
    }

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
     * Use the autoloader from the Raven library to load all necessary classes
     */
    public function onStartDispatch()
    {
        require_once __DIR__ . '/vendor/autoload.php';
        if (Shopware()->Config()->getByNamespace('OdSentry', 'logPhp')) {
            $privateDsn = Shopware()->Config()->getByNamespace('OdSentry', 'privateDsn');
            // todo: find out why the default handlers cause the Shopware BE to break
            $this->ravenClient = new \Raven_Client($privateDsn, ['install_default_breadcrumb_handlers' => false]);

            $ravenHandler = new RavenHandler($this->ravenClient, Logger::WARNING);
            $ravenHandler->setFormatter(new LineFormatter("%message% %context% %extra%\n"));
            /** @var Logger $coreLogger */
            $coreLogger = $this->container->get('corelogger');
            $coreLogger->pushHandler($ravenHandler);
        }
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
