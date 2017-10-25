<?php

namespace OdSentry\Components;

use Psr\Log\LoggerInterface;
use Shopware\Components\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SentryClient extends \Raven_Client
{
    /**
     * @var bool
     */
    private $contextSet = false;
    /**
     * @var Container
     */
    private $container;

    /**
     * @var array
     */
    private $skipCapture;

    /**
     * @param ContainerInterface $container
     */
    public function setContainer(ContainerInterface $container)
    {
        $this->container = $container;
        $this->skipCapture = $container->getParameter('shopware.sentry')['skip_capture'];
    }

    /**
     * @param \Exception $exception
     * @param array|null $data
     * @param LoggerInterface|null $logger
     * @param array|null $vars
     * @return mixed
     */
    public function captureException($exception, $data = null, $logger = null, $vars = null)
    {
        if ($this->shouldExceptionCaptureBeSkipped($exception)) {
            return false;
        }

        $this->tags_context([
            'php_version' => phpversion()
        ]);
        if ($this->container) {
            if ($this->container->initialized('session') && !$this->contextSet) {
                // Frontend user is logged in
                $userId = $this->container->get('session')->get('sUserId');
                if (!empty($userId)) {
                    $userData = Shopware()->Modules()->Admin()->sGetUserData();
                    $this->user_context([
                        'id' => $userId,
                        'email' => $userData['additional']['user']['email']
                    ]);
                    $this->extra_context($userData);
                    $this->contextSet = true;
                }
            } else {
                // Probably backend user
                try {
                    $auth = Shopware()->Plugins()->Backend()->Auth()->checkAuth();

                    if ($auth) {
                        $backendUser = $auth->getIdentity();

                        $this->user_context([
                            'id' => $backendUser->id,
                            'username' => $backendUser->username,
                            'email' => $backendUser->email
                        ]);
                        $this->contextSet = true;

                    }
                } catch (\Exception $e) {
                }
            }
            if ($this->container->initialized('front')) {
                $request = $this->container->get('front')->Request();
                $this->tags_context([
                    'module' => $request->getModuleName(),
                    'controller' => $request->getControllerName(),
                    'action' => $request->getActionName(),
                ]);
            }
        }
        return parent::captureException($exception, $data, $logger, $vars);
    }

    /**
     * @param \Exception $exception
     * @return bool
     */
    private function shouldExceptionCaptureBeSkipped(\Exception $exception)
    {
        foreach ($this->skipCapture as $className) {
            if ($exception instanceof $className) {
                return true;
            }
        }

        return false;
    }

}
