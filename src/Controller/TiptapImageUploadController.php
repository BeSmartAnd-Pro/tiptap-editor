<?php

declare(strict_types=1);

namespace BeSmartAndPro\TiptapEditorBundle\Controller;

use BeSmartAndPro\TiptapEditorBundle\Config\TiptapEditorConfig;
use Psr\Container\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class TiptapImageUploadController extends AbstractController
{
    public function __construct(
        private TiptapEditorConfig $config,
        private ContainerInterface $serviceLocator,
        private SluggerInterface $slugger,
    ) {
    }

    #[Route('/_besmartand-pro/tiptap-editor/upload-image', name: 'besmartand_pro_tiptap_editor_upload_image', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        if (!$this->config->isUploadEnabled()) {
            return $this->json([
                'message' => 'Upload obrazów jest wyłączony w konfiguracji bundla.',
            ], Response::HTTP_NOT_FOUND);
        }

        if (null !== $this->config->getSecurityAttribute()) {
            $this->denyAccessUnlessGranted($this->config->getSecurityAttribute());
        }

        if (!$this->isCsrfTokenValid('besmartand_pro_tiptap_editor_upload', (string) $request->headers->get('X-CSRF-TOKEN'))) {
            return $this->json([
                'message' => 'Sesja wygasła. Odśwież stronę i spróbuj ponownie.',
            ], Response::HTTP_FORBIDDEN);
        }

        $file = $request->files->get('image');

        if (!$file instanceof UploadedFile) {
            return $this->json([
                'message' => 'Nie znaleziono pliku do wysłania.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $mimeType = (string) $file->getMimeType();

        if (!in_array($mimeType, $this->config->getAllowedMimeTypes(), true)) {
            return $this->json([
                'message' => 'Ten typ pliku nie jest obsługiwany.',
            ], Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
        }

        if (($file->getSize() ?? 0) > $this->config->getMaxFileSize()) {
            return $this->json([
                'message' => sprintf('Obraz jest za duży. Limit wynosi %d MB.', (int) ceil($this->config->getMaxFileSize() / 1024 / 1024)),
            ], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        $filesystemService = $this->config->getFilesystemService();

        if (null === $filesystemService || !$this->serviceLocator->has($filesystemService)) {
            return $this->json([
                'message' => 'Nie znaleziono skonfigurowanego storage dla uploadu.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $storage = $this->serviceLocator->get($filesystemService);

        if (!is_object($storage) || !method_exists($storage, 'writeStream')) {
            return $this->json([
                'message' => 'Skonfigurowany storage nie wspiera uploadu strumieniowego.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $extension = $file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin';
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeName = $this->slugger->slug('' !== $originalName ? $originalName : 'image')->lower()->toString();
        $filename = sprintf('%s-%s.%s', $safeName, Uuid::v7()->toRfc4122(), $extension);

        $stream = fopen($file->getPathname(), 'rb');

        if (false === $stream) {
            return $this->json([
                'message' => 'Nie udało się odczytać przesłanego pliku.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        try {
            $storage->writeStream($filename, $stream, ['visibility' => 'public']);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return $this->json([
            'src' => rtrim((string) $this->config->getPublicUrlPrefix(), '/') . '/' . $filename,
            'alt' => $originalName,
            'filename' => $filename,
        ]);
    }
}
