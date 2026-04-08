<?php

declare(strict_types=1);

namespace BeSmartAndPro\TiptapEditorBundle\EasyAdmin\Field;

use BeSmartAndPro\TiptapEditorBundle\Form\Type\TiptapType;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;

final class TiptapField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $propertyName, ?string $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setTemplatePath('')
            ->setLabel($label)
            ->addFormTheme('@BeSmartAndProTiptapEditor/form/tiptap_widget.html.twig')
            ->setFormType(TiptapType::class);
    }

    public function setPlaceholder(string $placeholder): self
    {
        $this->setFormTypeOption('tiptap_placeholder', $placeholder);

        return $this;
    }
}
