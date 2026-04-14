import { Controller } from '@hotwired/stimulus';
import { Editor } from '@tiptap/core';
import Image from '@tiptap/extension-image';
import Link from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
import Strike from '@tiptap/extension-strike';
import Underline from '@tiptap/extension-underline';
import StarterKit from '@tiptap/starter-kit';

type EditorCommand =
  | 'paragraph'
  | 'bold'
  | 'italic'
  | 'underline'
  | 'strike'
  | 'h2'
  | 'h3'
  | 'bulletList'
  | 'orderedList'
  | 'blockquote'
  | 'codeBlock'
  | 'horizontalRule'
  | 'undo'
  | 'redo'
  | 'link'
  | 'imageUpload'
  | 'imageUrl'
  | 'clearFormatting';

type UploadResponse = {
  alt?: string;
  message?: string;
  src?: string;
};

type InsertedImage = {
  alt: string;
  src: string;
};

export default class extends Controller<HTMLElement> {
  static targets = ['editor', 'input', 'uploadInput'];

  static values = {
    placeholder: String,
    uploadToken: String,
    uploadUrl: String,
  };

  declare readonly editorTarget: HTMLElement;

  declare readonly inputTarget: HTMLTextAreaElement;

  declare readonly placeholderValue: string;

  declare readonly uploadInputTarget: HTMLInputElement;

  declare readonly hasUploadInputTarget: boolean;

  declare readonly uploadTokenValue: string;

  declare readonly hasUploadTokenValue: boolean;

  declare readonly uploadUrlValue: string;

  declare readonly hasUploadUrlValue: boolean;

  #editor?: Editor;

  #isUploading = false;

  connect(): void {
    this.#editor = new Editor({
      element: this.editorTarget,
      extensions: [
        StarterKit.configure({
          heading: {
            levels: [2, 3, 4],
          },
        }),
        Underline,
        Strike,
        Link.configure({
          openOnClick: false,
          autolink: true,
          defaultProtocol: 'https',
        }),
        Image.configure({
          inline: false,
          allowBase64: false,
        }),
        Placeholder.configure({
          placeholder: this.placeholderValue || 'Wpisz treść...',
        }),
      ],
      content: this.inputTarget.value,
      editorProps: {
        attributes: {
          class: 'tiptap-editor__prose',
        },
        handleDrop: (_view, event): boolean => {
          if (!(event instanceof DragEvent)) {
            return false;
          }

          return this.handleImageDrop(event);
        },
        handlePaste: (_view, event): boolean => {
          if (!(event instanceof ClipboardEvent)) {
            return false;
          }

          return this.handleImagePaste(event);
        },
      },
      onCreate: ({ editor }): void => {
        this.syncInput(editor);
        this.refreshUi(editor);
      },
      onUpdate: ({ editor }): void => {
        this.syncInput(editor);
        this.refreshUi(editor);
      },
      onSelectionUpdate: ({ editor }): void => {
        this.refreshUi(editor);
      },
    });
  }

  disconnect(): void {
    this.#editor?.destroy();
    this.#editor = undefined;
  }

  exec(event: Event): void {
    event.preventDefault();

    if (!(event.currentTarget instanceof HTMLElement) || !this.#editor || this.#isUploading) {
      return;
    }

    const command = event.currentTarget.dataset.command as EditorCommand | undefined;

    if (!command) {
      return;
    }

    const chain = this.#editor.chain().focus();

    switch (command) {
      case 'paragraph':
        chain.setParagraph().run();
        break;
      case 'bold':
        chain.toggleBold().run();
        break;
      case 'italic':
        chain.toggleItalic().run();
        break;
      case 'underline':
        chain.toggleUnderline().run();
        break;
      case 'strike':
        chain.toggleStrike().run();
        break;
      case 'h2':
        chain.toggleHeading({ level: 2 }).run();
        break;
      case 'h3':
        chain.toggleHeading({ level: 3 }).run();
        break;
      case 'bulletList':
        chain.toggleBulletList().run();
        break;
      case 'orderedList':
        chain.toggleOrderedList().run();
        break;
      case 'blockquote':
        chain.toggleBlockquote().run();
        break;
      case 'codeBlock':
        chain.toggleCodeBlock().run();
        break;
      case 'horizontalRule':
        chain.setHorizontalRule().run();
        break;
      case 'undo':
        chain.undo().run();
        break;
      case 'redo':
        chain.redo().run();
        break;
      case 'link':
        this.toggleLink();
        break;
      case 'imageUpload':
        this.openUploadDialog();
        break;
      case 'imageUrl':
        this.insertImageFromUrl();
        break;
      case 'clearFormatting':
        chain.clearNodes().unsetAllMarks().run();
        break;
      default:
        break;
    }
  }

  async uploadSelectedImage(): Promise<void> {
    if (!this.hasUploadInputTarget) {
      return;
    }

    const [file] = Array.from(this.uploadInputTarget.files ?? []);
    this.uploadInputTarget.value = '';

    if (!file) {
      return;
    }

    await this.uploadImageAndInsert(file);
  }

  private syncInput(editor: Editor): void {
    this.inputTarget.value = editor.isEmpty ? '' : editor.getHTML();
  }

  private refreshUi(editor: Editor): void {
    this.element.classList.toggle('is-busy', this.#isUploading);
    this.refreshToolbar(editor);
  }

  private refreshToolbar(editor: Editor): void {
    this.element.querySelectorAll<HTMLButtonElement>('[data-command]').forEach((button): void => {
      const command = button.dataset.command as EditorCommand | undefined;

      if (!command) {
        return;
      }

      button.classList.toggle('is-active', this.isCommandActive(editor, command));
      button.disabled = this.isCommandDisabled(editor, command);
    });
  }

  private isCommandActive(editor: Editor, command: EditorCommand): boolean {
    switch (command) {
      case 'paragraph':
        return editor.isActive('paragraph');
      case 'bold':
        return editor.isActive('bold');
      case 'italic':
        return editor.isActive('italic');
      case 'underline':
        return editor.isActive('underline');
      case 'strike':
        return editor.isActive('strike');
      case 'h2':
        return editor.isActive('heading', { level: 2 });
      case 'h3':
        return editor.isActive('heading', { level: 3 });
      case 'bulletList':
        return editor.isActive('bulletList');
      case 'orderedList':
        return editor.isActive('orderedList');
      case 'blockquote':
        return editor.isActive('blockquote');
      case 'codeBlock':
        return editor.isActive('codeBlock');
      case 'link':
        return editor.isActive('link');
      default:
        return false;
    }
  }

  private isCommandDisabled(editor: Editor, command: EditorCommand): boolean {
    if (this.#isUploading) {
      return true;
    }

    if (command === 'undo') {
      return !editor.can().chain().focus().undo().run();
    }

    if (command === 'redo') {
      return !editor.can().chain().focus().redo().run();
    }

    if (command === 'imageUpload') {
      return !this.hasUploadInputTarget || !this.hasUploadUrlValue;
    }

    return false;
  }

  private toggleLink(): void {
    if (!this.#editor) {
      return;
    }

    const previousUrl = this.#editor.getAttributes('link').href as string | undefined;
    const url = window.prompt('Podaj URL linku', previousUrl ?? 'https://');

    if (url === null) {
      return;
    }

    if (url.trim() === '') {
      this.#editor.chain().focus().extendMarkRange('link').unsetLink().run();

      return;
    }

    this.#editor.chain().focus().extendMarkRange('link').setLink({ href: url.trim() }).run();
  }

  private openUploadDialog(): void {
    if (!this.hasUploadInputTarget || !this.hasUploadUrlValue) {
      return;
    }

    this.uploadInputTarget.click();
  }

  private insertImageFromUrl(): void {
    if (!this.#editor) {
      return;
    }

    const src = window.prompt('Podaj URL obrazka');

    if (!src || src.trim() === '') {
      return;
    }

    const alt = window.prompt('Podaj opis obrazka (alt)', '') ?? '';

    this.#editor.chain().focus().setImage({ src: src.trim(), alt }).run();
  }

  private handleImageDrop(event: DragEvent): boolean {
    if (!this.#editor || !this.hasUploadUrlValue || !event.dataTransfer) {
      return false;
    }

    const files = Array.from(event.dataTransfer.files).filter((file): boolean => file.type.startsWith('image/'));

    if (files.length === 0) {
      return false;
    }

    event.preventDefault();

    const position = this.#editor.view.posAtCoords({
      left: event.clientX,
      top: event.clientY,
    })?.pos;

    void this.uploadImagesAndInsert(files, position);

    return true;
  }

  private handleImagePaste(event: ClipboardEvent): boolean {
    if (!this.#editor || !this.hasUploadUrlValue || !event.clipboardData) {
      return false;
    }

    const files = Array.from(event.clipboardData.files).filter((file): boolean => file.type.startsWith('image/'));

    if (files.length === 0) {
      return false;
    }

    event.preventDefault();

    void this.uploadImagesAndInsert(files, this.#editor.state.selection.from);

    return true;
  }

  private async uploadImagesAndInsert(files: File[], position?: number): Promise<void> {
    let insertPosition = position;

    for (const file of files) {
      insertPosition = await this.uploadImageAndInsert(file, insertPosition);

      if (typeof insertPosition === 'number') {
        insertPosition += 1;
      }
    }
  }

  private async uploadImageAndInsert(file: File, position?: number): Promise<number | undefined> {
    const uploadedImage = await this.uploadImage(file);

    if (!uploadedImage || !this.#editor) {
      return position;
    }

    const editor = this.#editor;
    const chain = editor.chain();

    if (typeof position === 'number') {
      chain.focus(position);
    } else {
      chain.focus();
    }

    chain.setImage(uploadedImage).run();

    return editor.state.selection.to;
  }

  private async uploadImage(file: File): Promise<InsertedImage | null> {
    if (!this.#editor || !this.hasUploadUrlValue || !this.hasUploadTokenValue) {
      return null;
    }

    if (!file.type.startsWith('image/')) {
      window.alert('Możesz wrzucić tylko plik graficzny.');

      return null;
    }

    const formData = new FormData();
    formData.append('image', file);

    this.#isUploading = true;
    this.refreshUi(this.#editor);

    try {
      const response = await fetch(this.uploadUrlValue, {
        method: 'POST',
        body: formData,
        headers: {
          'X-CSRF-TOKEN': this.uploadTokenValue,
          'X-Requested-With': 'XMLHttpRequest',
        },
      });

      const payload = (await response.json()) as UploadResponse;

      if (!response.ok || !payload.src) {
        throw new Error(payload.message ?? 'Nie udało się wgrać obrazu.');
      }

      const alt = window.prompt('Podaj opis obrazka (alt)', payload.alt ?? file.name) ?? payload.alt ?? file.name;

      return {
        alt,
        src: payload.src,
      };
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Nie udało się wgrać obrazu.';
      window.alert(message);

      return null;
    } finally {
      this.#isUploading = false;

      if (this.#editor) {
        this.refreshUi(this.#editor);
      }
    }
  }
}
