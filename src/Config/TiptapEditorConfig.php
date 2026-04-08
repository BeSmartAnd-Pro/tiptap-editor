<?php

declare(strict_types=1);

namespace BeSmartAndPro\TiptapEditorBundle\Config;

final readonly class TiptapEditorConfig
{
    /**
     * @param list<string> $allowedMimeTypes
     */
    public function __construct(
        private bool $uploadEnabled,
        private ?string $filesystemService,
        private ?string $publicUrlPrefix,
        private ?string $securityAttribute,
        private int $maxFileSize,
        private array $allowedMimeTypes,
    ) {
    }

    public function isUploadEnabled(): bool
    {
        return $this->uploadEnabled
            && null !== $this->filesystemService
            && null !== $this->publicUrlPrefix;
    }

    public function getFilesystemService(): ?string
    {
        return $this->filesystemService;
    }

    public function getPublicUrlPrefix(): ?string
    {
        return $this->publicUrlPrefix;
    }

    public function getSecurityAttribute(): ?string
    {
        return $this->securityAttribute;
    }

    public function getMaxFileSize(): int
    {
        return $this->maxFileSize;
    }

    /** @return list<string> */
    public function getAllowedMimeTypes(): array
    {
        return $this->allowedMimeTypes;
    }
}
