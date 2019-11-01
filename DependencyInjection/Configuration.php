<?php namespace Ewll\UserBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * {@inheritdoc}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('ewll_user');

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('salt')->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('domain')->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('telegram_bot_name')->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('telegram_bot_token')->isRequired()->cannotBeEmpty()->end()
                ->arrayNode('oauth')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('client_id')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('client_secret')->isRequired()->cannotBeEmpty()->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
