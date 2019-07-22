<?php
/**
 * (c) Onedrop GmbH & Co. KG <info@1drop.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace OdSentry;

use OdSentry\Components\SentryCompilerPass;
use Shopware\Components\Plugin;
use Shopware\Components\Plugin\Context\ActivateContext;
use Shopware\Components\Plugin\Context\InstallContext;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class OdSentry extends Plugin
{
    const PLUGIN_NAME = 'OdSentry';

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
     * @param ContainerBuilder $container
     */
    public function build(ContainerBuilder $container)
    {
        parent::build($container);
        $container->addCompilerPass(new SentryCompilerPass());
    }
}
