<?php
declare(strict_types=1);
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2019 Hans Hoechtl <hhoechtl@1drop.de>
 *  All rights reserved
 ***************************************************************/
namespace OdSentry\Subscriber;

use Enlight\Event\SubscriberInterface;
use Enlight_Event_EventArgs;
use OdSentry\OdSentry;
use Sentry\SentrySdk;
use Sentry\State\Scope;
use Shopware\Components\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerInterface;
use function Sentry\captureException;
use function Sentry\configureScope;
use function Sentry\init as initSentry;
use Throwable;

class ErrorHandler implements SubscriberInterface
{
    /**
     * @var bool
     */
    private $sentryEnabled = false;
    /**
     * @var string
     */
    private $pluginDirectory;
    /**
     * @var Container
     */
    private $container;

    /**
     * ErrorHandler constructor.
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->pluginDirectory = $container->getParameter('od_sentry.plugin_dir');
        // Use composer autoloader if dependencies are bundles within the plugin (non-composer mode)
        if (file_exists($this->pluginDirectory . '/vendor/autoload.php')) {
            require_once $this->pluginDirectory . '/vendor/autoload.php';
        }
        $this->container = $container;
    }

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Front_StartDispatch'               => 'onStartDispatch',
            'Enlight_Controller_Action_PreDispatch_Frontend_Error' => 'onPreDispatchError',
            'Enlight_Controller_Action_PreDispatch_Backend_Error'  => 'onPreDispatchBackendError',
            'Enlight_Controller_Front_DispatchLoopShutdown'        => 'onDispatchLoopShutdown',
            'Shopware_Console_Add_Command'                         => 'onStartDispatch',
        ];
    }

    /**
     * Initialize Sentry error handling
     */
    private function initSentry()
    {
        $publicDsn = $this->container->get('config')->getByNamespace(OdSentry::PLUGIN_NAME, 'sentryPublicDsn');
        initSentry(['dsn' => $publicDsn]);
        $options = SentrySdk::getCurrentHub()->getClient()->getOptions();
        $options->setEnvironment($this->container->getParameter('kernel.environment'));
        if ($this->container->has('shopware.release')) {
            $options->setRelease(
                sprintf(
                    '%s-%s',
                    $this->container->get('shopware.release')->getVersion(),
                    $this->container->get('shopware.release')->getRevision()
                )
            );
        } else {
            $options->setRelease(sprintf('%s-%s@%s', getenv('SHOPWARE_VERSION'), getenv('SHOPWARE_REVISION'), getenv('SHOPWARE_VERSION_TEXT')));
        }
        // Restore Shopware default error handler
        restore_error_handler();
        restore_exception_handler();
    }

    /**
     * @param  Throwable $exception
     * @return bool
     */
    private function shouldExceptionCaptureBeSkipped(Throwable $exception)
    {
        $sentryConfig = $this->container->getParameter('shopware.sentry');
        foreach ($sentryConfig['skip_capture'] as $className) {
            if ($exception instanceof $className) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param  Throwable $exception
     * @return bool
     */
    private function captureException(Throwable $exception)
    {
        if ($this->shouldExceptionCaptureBeSkipped($exception)) {
            return false;
        }
        $serviceContainer = $this->container;
        configureScope(function (Scope $scope) use ($serviceContainer) {
            $scope->setTag('php_version', phpversion());
            // Frontend request
            if ($serviceContainer->initialized('front')) {
                /** @var \Enlight_Controller_Request_Request $request */
                $request = $serviceContainer->get('front')->Request();
                $scope->setTag('module', $request->getModuleName());
                $scope->setTag('controller', $request->getControllerName());
                $scope->setTag('action', $request->getActionName());
            }
            // Frontend user is logged in
            if ($serviceContainer->initialized('session')) {
                $userId = $serviceContainer->get('session')->get('sUserId');
                if (!empty($userId)) {
                    $userData = $serviceContainer->get('modules')->Admin()->sGetUserData();
                    $scope->setUser([
                        'id'    => $userId,
                        'email' => $userData['additional']['user']['email'],
                    ]);
                    $scope->setExtra('userData', $userData);
                }
            }

            if ($serviceContainer->initialized('shop')) {
                $scope->setTag('shop', $serviceContainer->get('shop')->getName());
            }

            // Check for backend request
            try {
                $auth = $serviceContainer->get('Auth');
                // Workaround due to interface violation of \Zend_Auth_Adapter_Interface
                if (method_exists($auth->getBaseAdapter(), 'refresh')) {
                    $auth = $serviceContainer->get('plugin_manager')->Backend()->Auth()->checkAuth();
                }
                if ($auth) {
                    $backendUser = $auth->getIdentity();
                    $scope->setUser([
                        'id'       => $backendUser->id,
                        'username' => $backendUser->username,
                        'email'    => $backendUser->email,
                    ]);
                }
            } catch (\Exception $e) {
            }
        });
        captureException($exception);
    }

    /**
     * Use the autoloader from the Raven library to load all necessary classes
     */
    public function onStartDispatch()
    {
        if ($this->container->get('config')->getByNamespace(OdSentry::PLUGIN_NAME, 'sentryLogPhp')) {
            $this->sentryEnabled = true;
            $this->initSentry();
        }
    }

    /**
     * @param Enlight_Event_EventArgs $args
     *
     * @return void
     */
    public function onPreDispatchBackendError(Enlight_Event_EventArgs $args)
    {
        if (!$this->sentryEnabled) {
            return;
        }
        /** @var \Shopware_Controllers_Backend_Error $subject */
        $subject = $args->getSubject();
        $error = $subject->Request()->getParam('error_handler');
        if ($error && $error->exception) {
            $this->captureException($error->exception);
        }
    }

    /**
     * @param Enlight_Event_EventArgs $args
     *
     * @return void
     */
    public function onPreDispatchError(Enlight_Event_EventArgs $args)
    {
        if (!$this->sentryEnabled) {
            return;
        }
        /** @var \Shopware_Controllers_Frontend_Error $subject */
        $subject = $args->getSubject();
        $error = $subject->Request()->getParam('error_handler');
        if (!($error && $error->exception)) {
            return;
        }
        $id = $this->captureException($error->exception);
        if (!$this->container->get('config')->getByNamespace(OdSentry::PLUGIN_NAME, 'sentryUserfeedback')) {
            return;
        }
        $templateManager = $this->container->get('template');
        $templateManager->assignGlobal('sentryId', $id);
        $templateManager->addTemplateDir($this->pluginDirectory . '/Resources/views/');
    }

    /**
     * Like the \Shopware_Plugins_Core_ErrorHandler_Bootstrap we want to catch all errors
     * that occured during a request and send them to Sentry
     *
     * @param \Enlight_Controller_EventArgs $args
     */
    public function onDispatchLoopShutdown(\Enlight_Controller_EventArgs $args)
    {
        if (!$this->sentryEnabled) {
            return;
        }
        $response = $args->getSubject()->Response();
        $exceptions = $response->getException();
        if (empty($exceptions)) {
            return;
        }
        foreach ($exceptions as $exception) {
            $this->captureException($exception);
        }
    }
}
