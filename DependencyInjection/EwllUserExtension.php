<?php namespace Ewll\UserBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * {@inheritdoc}
 */
class EwllUserExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $container->setParameter('ewll_user.redirect', $config['redirect']);
        $container->setParameter('ewll_user.salt', $config['salt']);
        $container->setParameter('ewll_user.domain', $config['domain']);
        $container->setParameter('ewll_user.telegram_bot_name', $config['telegram_bot_name']);
        $container->setParameter('ewll_user.telegram_bot_token', $config['telegram_bot_token']);
        $container->setParameter('ewll_user.telegram_proxy', $config['telegram_proxy']);

        foreach ($config['oauth'] as $name => $params) {
            $container->setParameter("ewll_user.oauth.$name.parameters", $params);
            $container
                ->register("ewll_user.oauth.$name", sprintf('Ewll\UserBundle\Oauth\Item\%sOauth', ucfirst($name)))
                ->addTag('ewll_user_oauth')
                ->addArgument("%ewll_user.oauth.$name.parameters%")
                ->addMethodCall('setRouter', [new Reference('router')]);
        }

        $twofaActions = $config['twofa']['actions'] ?? [];
        $container->setParameter("ewll_user.twofa.actions", $twofaActions);
    }
}
