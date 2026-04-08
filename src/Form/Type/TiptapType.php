<?php

declare(strict_types=1);

namespace BeSmartAndPro\TiptapEditorBundle\Form\Type;

use BeSmartAndPro\TiptapEditorBundle\Config\TiptapEditorConfig;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class TiptapType extends AbstractType
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private TiptapEditorConfig $config,
    ) {
    }

    /** @param array<string, mixed> $options */
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['tiptap_placeholder'] = $options['tiptap_placeholder'];
        $view->vars['tiptap_upload_url'] = $options['tiptap_upload_url']
            ?? ($this->config->isUploadEnabled() ? $this->urlGenerator->generate('besmartand_pro_tiptap_editor_upload_image') : null);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'attr' => [
                'class' => 'd-none',
            ],
            'required' => false,
            'sanitize_html' => false,
            'tiptap_placeholder' => 'Wpisz treść...',
            'tiptap_upload_url' => null,
        ]);
    }

    public function getParent(): string
    {
        return TextareaType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'tiptap';
    }
}
