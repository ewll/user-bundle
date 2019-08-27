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
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('ewll_user');

        $rootNode
            ->children()
            ->scalarNode('salt')->isRequired()->cannotBeEmpty()->end()
            ->scalarNode('domain')->isRequired()->cannotBeEmpty()->end();

        return $treeBuilder;
    }
}
