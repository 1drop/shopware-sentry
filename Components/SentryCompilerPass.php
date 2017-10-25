<?php
/**
 * (c) Onedrop GmbH & Co. KG <info@1drop.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OdSentry\Components;


use Enlight_Controller_Exception;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Class SentryCompilerPass
 * @package OdSentry\Components
 */
class SentryCompilerPass implements CompilerPassInterface
{
    /**
     * @var array
     */
    private $defaultConfiguration = [
        'skip_capture' => [
            CommandNotFoundException::class,
            Enlight_Controller_Exception::class
        ]
    ];

    /**
     * You can modify the container here before it is dumped to PHP code.
     *
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasParameter('shopware.sentry')) {
            $container->setParameter('shopware.sentry', $this->defaultConfiguration);
        }
    }
}