<?php

declare(strict_types=1);

namespace BeSmartAndPro\TiptapEditorBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(name: 'besmartand-pro:tiptap-editor:install', description: 'Scaffold host app integration for the BeSmartAnd.Pro Tiptap bundle.')]
final class InstallTiptapEditorCommand extends Command
{
    private const REQUIRED_NODE_DEPENDENCIES = [
        '@tiptap/core' => '^3.22.2',
        '@tiptap/extension-image' => '^3.22.2',
        '@tiptap/extension-link' => '^3.22.2',
        '@tiptap/extension-placeholder' => '^3.22.2',
        '@tiptap/extension-strike' => '^3.22.2',
        '@tiptap/extension-underline' => '^3.22.2',
        '@tiptap/starter-kit' => '^3.22.2',
        'bootstrap-icons' => '^1.13.1',
    ];

    private const string STIMULUS_BUNDLE_PACKAGE = 'symfony/stimulus-bundle';

    public function __construct(
        private readonly KernelInterface $kernel,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $projectDir = $this->kernel->getProjectDir();
        $bundler = $this->detectBundler($projectDir);
        $frontendPackageFile = $this->detectFrontendPackageFile($projectDir);
        $packageManager = $this->detectPackageManager($projectDir);

        $this->warnIfStimulusBundleIsMissing($projectDir, $io);
        $packageJsonUpdated = $this->ensurePackageJsonDependencies($projectDir);
        $this->ensureRouteImport($projectDir);
        $this->ensureBundleConfig($projectDir);
        $this->ensureStimulusControllerAsset($projectDir);
        $this->ensureStylesheetAsset($projectDir);
        $entryFile = $this->ensureEntryImport($projectDir, $bundler);

        $io->success('BeSmartAnd.Pro Tiptap Editor scaffolding is ready.');
        $io->writeln(sprintf('Detected bundler: <info>%s</info>', $bundler));

        if (null !== $entryFile) {
            $io->writeln(sprintf('Updated entry file: <info>%s</info>', $this->relativePath($projectDir, $entryFile)));
        } else {
            $io->warning('Could not find a JS/TS entry file to import the bundle stylesheet. Add it manually as described in README.');
        }

        if (null === $frontendPackageFile) {
            $io->warning('No package.json was found. The bundle PHP side is ready, but you still need a frontend setup with Stimulus and the required Tiptap packages.');
        } elseif ($packageJsonUpdated) {
            $io->writeln(sprintf('Frontend dependencies were added to <info>%s</info>.', $this->relativePath($projectDir, $frontendPackageFile)));
        } else {
            $io->writeln(sprintf('<info>%s</info> already contains the required frontend dependencies.', $this->relativePath($projectDir, $frontendPackageFile)));
        }

        if ('unknown' === $bundler) {
            $io->warning('Could not detect Encore or Vite. The generated bridge files are ready, but you may need to import the stylesheet manually.');
        }

        if (null !== $packageManager) {
            $io->writeln(sprintf('Next step: refresh frontend packages with <info>%s install</info>.', $packageManager));
        } else {
            $io->writeln('Next step: refresh frontend packages with your package manager.');
        }

        return Command::SUCCESS;
    }

    private function detectBundler(string $projectDir): string
    {
        foreach (['vite.config.ts', 'vite.config.js', 'vite.config.mjs'] as $file) {
            if (is_file($projectDir . '/' . $file)) {
                return 'vite';
            }
        }

        if (is_file($projectDir . '/webpack.config.js')) {
            return 'encore';
        }

        return 'unknown';
    }

    private function ensurePackageJsonDependencies(string $projectDir): bool
    {
        $packageJsonPath = $this->detectFrontendPackageFile($projectDir);

        if (null === $packageJsonPath) {
            return false;
        }

        $packageJson = json_decode((string) file_get_contents($packageJsonPath), true, flags: JSON_THROW_ON_ERROR);
        $packageJson['dependencies'] ??= [];
        $updated = false;

        foreach (self::REQUIRED_NODE_DEPENDENCIES as $package => $version) {
            if (!isset($packageJson['dependencies'][$package])) {
                $packageJson['dependencies'][$package] = $version;
                $updated = true;
            }
        }

        ksort($packageJson['dependencies']);

        if ($updated) {
            file_put_contents($packageJsonPath, json_encode($packageJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
        }

        return $updated;
    }

    private function ensureRouteImport(string $projectDir): void
    {
        $routeConfigPath = $projectDir . '/config/routes/besmartand_pro_tiptap_editor.yaml';
        $contents = <<<'YAML'
besmartand_pro_tiptap_editor:
    resource: '@BeSmartAndProTiptapEditorBundle/src/Controller/'
    type: attribute
YAML;

        $this->writeIfMissing($routeConfigPath, $contents . PHP_EOL);
    }

    private function ensureBundleConfig(string $projectDir): void
    {
        $configPath = $projectDir . '/config/packages/besmartand_pro_tiptap_editor.yaml';
        $contents = <<<'YAML'
besmartand_pro_tiptap_editor:
    default_placeholder: 'Wpisz treść...'
    upload:
        enabled: false
        # filesystem_service: 'oneup_flysystem.images_filesystem'
        # public_url_prefix: '/cdn/images'
        # security_attribute: 'ROLE_ADMIN'
        max_file_size: 8388608
YAML;

        $this->writeIfMissing($configPath, $contents . PHP_EOL);
    }

    private function ensureStimulusControllerAsset(string $projectDir): void
    {
        $targetPath = $projectDir . '/assets/controllers/besmartand_pro/tiptap_editor_controller.ts';
        $sourcePath = $this->kernel->getBundle('BeSmartAndProTiptapEditorBundle')->getPath() . '/assets/controllers/tiptap_editor_controller.ts';

        $this->copyAssetIfMissingOrLegacy(
            $targetPath,
            $sourcePath,
            [
                "export { default } from '../../../vendor/besmartand-pro/tiptap-editor/assets/controllers/tiptap_editor_controller';\n",
                "export { default } from '../../../../vendor/besmartand-pro/tiptap-editor/assets/controllers/tiptap_editor_controller';\n",
            ],
        );
    }

    private function ensureStylesheetAsset(string $projectDir): void
    {
        $targetPath = $projectDir . '/assets/styles/besmartand_pro_tiptap_editor.scss';
        $sourcePath = $this->kernel->getBundle('BeSmartAndProTiptapEditorBundle')->getPath() . '/assets/styles/editor.scss';

        $this->copyAssetIfMissingOrLegacy(
            $targetPath,
            $sourcePath,
            [
                "@use '../../../vendor/besmartand-pro/tiptap-editor/assets/styles/editor';\n",
                "@use '../../vendor/besmartand-pro/tiptap-editor/assets/styles/editor';\n",
            ],
        );
    }

    private function ensureEntryImport(string $projectDir, string $bundler): ?string
    {
        $candidates = [
            $projectDir . '/assets/admin.ts',
            $projectDir . '/assets/admin.js',
            $projectDir . '/assets/app.ts',
            $projectDir . '/assets/app.js',
            $projectDir . '/assets/main.ts',
            $projectDir . '/assets/main.js',
            $projectDir . '/resources/app.ts',
            $projectDir . '/resources/app.js',
        ];

        if ('vite' === $bundler) {
            array_unshift(
                $candidates,
                $projectDir . '/assets/main.ts',
                $projectDir . '/assets/main.js',
            );
        }

        foreach ($candidates as $candidate) {
            if (!is_file($candidate)) {
                continue;
            }

            $importLine = sprintf(
                "import '%s';",
                $this->relativeImportPath(dirname($candidate), $projectDir . '/assets/styles/besmartand_pro_tiptap_editor.scss'),
            );

            $contents = (string) file_get_contents($candidate);

            if (!str_contains($contents, $importLine)) {
                file_put_contents($candidate, $contents . PHP_EOL . $importLine . PHP_EOL);
            }

            return $candidate;
        }

        return null;
    }

    private function writeIfMissing(string $path, string $contents): void
    {
        if (is_file($path)) {
            return;
        }

        $this->ensureDirectoryExists(dirname($path));
        file_put_contents($path, $contents);
    }

    /**
     * Copies bundle frontend assets into the host app so Vite and Encore do not
     * need to resolve TS/SCSS imports from composer vendor directories.
     *
     * Existing files are only replaced when they are missing, already identical
     * to the bundle source, or still contain the legacy vendor bridge scaffold.
     *
     * @param list<string> $legacyContents
     */
    private function copyAssetIfMissingOrLegacy(string $targetPath, string $sourcePath, array $legacyContents): void
    {
        $sourceContents = (string) file_get_contents($sourcePath);

        if (is_file($targetPath)) {
            $targetContents = (string) file_get_contents($targetPath);

            if ($targetContents === $sourceContents || in_array($targetContents, $legacyContents, true)) {
                $this->ensureDirectoryExists(dirname($targetPath));
                file_put_contents($targetPath, $sourceContents);

                return;
            }

            return;
        }

        $this->ensureDirectoryExists(dirname($targetPath));
        file_put_contents($targetPath, $sourceContents);
    }

    private function warnIfStimulusBundleIsMissing(string $projectDir, SymfonyStyle $io): void
    {
        $composerJsonPath = $projectDir . '/composer.json';

        if (!is_file($composerJsonPath)) {
            return;
        }

        $composerJson = json_decode((string) file_get_contents($composerJsonPath), true, flags: JSON_THROW_ON_ERROR);
        $requirements = array_merge(
            $composerJson['require'] ?? [],
            $composerJson['require-dev'] ?? [],
        );

        if (!array_key_exists(self::STIMULUS_BUNDLE_PACKAGE, $requirements)) {
            $io->warning(sprintf(
                'Missing %s. The generated controller expects Symfony StimulusBundle to be installed and scanning assets/controllers.',
                self::STIMULUS_BUNDLE_PACKAGE,
            ));
        }
    }

    private function detectFrontendPackageFile(string $projectDir): ?string
    {
        foreach ([
            $projectDir . '/package.json',
            $projectDir . '/assets/package.json',
        ] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function detectPackageManager(string $projectDir): ?string
    {
        return match (true) {
            is_file($projectDir . '/pnpm-lock.yaml') => 'pnpm',
            is_file($projectDir . '/yarn.lock') => 'yarn',
            is_file($projectDir . '/package-lock.json') => 'npm',
            default => null,
        };
    }

    private function ensureDirectoryExists(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
    }

    private function relativePath(string $projectDir, string $path): string
    {
        return ltrim(str_replace($projectDir, '', $path), '/');
    }

    private function relativeImportPath(string $fromDir, string $targetWithoutExtension): string
    {
        $from = explode('/', trim($fromDir, '/'));
        $target = explode('/', trim($targetWithoutExtension, '/'));

        while ([] !== $from && [] !== $target && $from[0] === $target[0]) {
            array_shift($from);
            array_shift($target);
        }

        $prefix = str_repeat('../', count($from));

        return '' !== $prefix ? $prefix . implode('/', $target) : './' . implode('/', $target);
    }
}
