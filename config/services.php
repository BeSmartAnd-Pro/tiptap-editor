<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use BeSmartAndPro\TiptapEditorBundle\Config\TiptapEditorConfig;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
    ;

    $services->load('BeSmartAndPro\\TiptapEditorBundle\\', '../src/*')
        ->exclude([
            '../src/BeSmartAndProTiptapEditorBundle.php',
            '../src/Config/',
            '../src/EasyAdmin/',
        ])
    ;

    $services->set(TiptapEditorConfig::class)
        ->args([
            param('besmartand_pro_tiptap_editor.upload.enabled'),
            param('besmartand_pro_tiptap_editor.upload.filesystem_service'),
            param('besmartand_pro_tiptap_editor.upload.public_url_prefix'),
            param('besmartand_pro_tiptap_editor.upload.security_attribute'),
            param('besmartand_pro_tiptap_editor.upload.max_file_size'),
            param('besmartand_pro_tiptap_editor.upload.allowed_mime_types'),
        ])
    ;
};
