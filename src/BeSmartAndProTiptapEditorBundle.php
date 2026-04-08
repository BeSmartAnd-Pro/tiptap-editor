<?php

declare(strict_types=1);

namespace BeSmartAndPro\TiptapEditorBundle;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class BeSmartAndProTiptapEditorBundle extends AbstractBundle
{
    protected string $extensionAlias = 'besmartand_pro_tiptap_editor';

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('default_placeholder')
                    ->defaultValue('Wpisz treść...')
                    ->cannotBeEmpty()
                ->end()
                ->arrayNode('upload')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                        ->scalarNode('filesystem_service')->defaultNull()->end()
                        ->scalarNode('public_url_prefix')->defaultNull()->end()
                        ->scalarNode('security_attribute')->defaultNull()->end()
                        ->integerNode('max_file_size')->defaultValue(8 * 1024 * 1024)->min(1)->end()
                        ->arrayNode('allowed_mime_types')
                            ->scalarPrototype()->end()
                            ->defaultValue([
                                'image/png',
                                'image/jpeg',
                                'image/jpg',
                                'image/webp',
                                'image/gif',
                            ])
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('twig')) {
            $container->extension('twig', [
                'form_themes' => [
                    '@BeSmartAndProTiptapEditor/form/tiptap_widget.html.twig',
                ],
            ]);
        }
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.php');

        $container->parameters()
            ->set('besmartand_pro_tiptap_editor.default_placeholder', $config['default_placeholder'])
            ->set('besmartand_pro_tiptap_editor.upload.enabled', $config['upload']['enabled'])
            ->set('besmartand_pro_tiptap_editor.upload.filesystem_service', $config['upload']['filesystem_service'])
            ->set('besmartand_pro_tiptap_editor.upload.public_url_prefix', $config['upload']['public_url_prefix'])
            ->set('besmartand_pro_tiptap_editor.upload.security_attribute', $config['upload']['security_attribute'])
            ->set('besmartand_pro_tiptap_editor.upload.max_file_size', $config['upload']['max_file_size'])
            ->set('besmartand_pro_tiptap_editor.upload.allowed_mime_types', $config['upload']['allowed_mime_types'])
        ;
    }
}
