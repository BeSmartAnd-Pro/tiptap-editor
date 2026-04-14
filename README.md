# BeSmartAnd.Pro Tiptap Editor Bundle

Reusable Symfony bundle with a compact Tiptap-based editor for classic Symfony forms and optional EasyAdmin integration.

It ships with:

- `BeSmartAndPro\TiptapEditorBundle\Form\Type\TiptapType`
- `BeSmartAndPro\TiptapEditorBundle\EasyAdmin\Field\TiptapField`
- a Stimulus controller namespace: `besmartand-pro--tiptap-editor`
- a compact icon-based toolbar with hover tooltips
- optional image uploads, including drag and drop and paste from clipboard

## Requirements

- PHP `^8.3`
- Symfony `^7.4`
- `symfony/stimulus-bundle`
- a frontend build using Encore or Vite
- these frontend packages in the host app:
  - `@tiptap/core`
  - `@tiptap/starter-kit`
  - `@tiptap/extension-image`
  - `@tiptap/extension-link`
  - `@tiptap/extension-placeholder`
  - `@tiptap/extension-strike`
  - `@tiptap/extension-underline`
  - `bootstrap-icons`

## Installation

```bash
composer require besmartand-pro/tiptap-editor
php bin/console besmartand-pro:tiptap-editor:install
```

Then refresh frontend dependencies with your package manager:

```bash
pnpm install
```

or:

```bash
npm install
```

or:

```bash
yarn install
```

## What The Install Command Does

`php bin/console besmartand-pro:tiptap-editor:install` is the "make it work in a real app" step.

It will:

- check whether `symfony/stimulus-bundle` is installed
- inspect `package.json`
- add missing Tiptap and `bootstrap-icons` dependencies
- detect `webpack.config.js` or `vite.config.*`
- create `assets/controllers/besmartand_pro/tiptap_editor_controller.ts`
- create `assets/styles/besmartand_pro_tiptap_editor.scss`
- try to import the stylesheet into a likely frontend entry file
- scaffold `config/packages/besmartand_pro_tiptap_editor.yaml`
- scaffold `config/routes/besmartand_pro_tiptap_editor.yaml`

If your project layout is unusual, the generated bridge files are still enough to wire things manually.

## Symfony Flex

This bundle is ready for a Symfony Flex recipe, but Flex should be treated as the first half of the setup, not the whole installer.

Recommended approach:

- Flex recipe copies config, routes and asset bridge files into the host app
- the bundle install command finishes the smart bits like dependency checks and frontend entry imports

The included `flex-recipe/` directory is a starter recipe template for publishing to your own Flex recipes repository or to `symfony/recipes-contrib`.

## Encore

The install command will try to add:

```ts
import './styles/besmartand_pro_tiptap_editor.scss';
```

to a likely entry file such as `assets/admin.ts` or `assets/app.ts`.

Your Stimulus bootstrap should keep scanning `assets/controllers`.

The bundle stylesheet imports Bootstrap Icons using:

```scss
@import "bootstrap-icons/font/bootstrap-icons.css";
```

This works with Vite and with current Encore/Webpack setups. Legacy `~bootstrap-icons/...` imports can still exist in host applications if their loader configuration supports them, but the bundle itself uses the bundler-neutral form so it can build cleanly in both environments.

## Vite

The install command will try to add the same stylesheet import to a likely Vite entry such as `assets/main.ts` or `assets/app.ts`.

Your Stimulus bootstrap should still expose controllers from `assets/controllers`.

## Uploads

Uploads are optional.

If upload config is missing or disabled:

- the editor still works
- image upload buttons are hidden
- drag and drop image uploads are disabled
- paste-from-clipboard uploads are disabled

Example with Oneup Flysystem:

```yaml
besmartand_pro_tiptap_editor:
    default_placeholder: 'Wpisz treść...'
    upload:
        enabled: true
        filesystem_service: 'oneup_flysystem.images_filesystem'
        public_url_prefix: '/cdn/images'
        security_attribute: 'ROLE_ADMIN'
        max_file_size: 8388608
```

The configured service only needs to expose `writeStream()`, so any Flysystem-compatible filesystem service is enough.

## Usage

### Symfony Form

```php
use BeSmartAndPro\TiptapEditorBundle\Form\Type\TiptapType;

$builder->add('content', TiptapType::class, [
    'tiptap_placeholder' => 'Wpisz treść...',
]);
```

### EasyAdmin

```php
use BeSmartAndPro\TiptapEditorBundle\EasyAdmin\Field\TiptapField;

yield TiptapField::new('content', 'Treść')
    ->setPlaceholder('Wpisz treść...');
```
