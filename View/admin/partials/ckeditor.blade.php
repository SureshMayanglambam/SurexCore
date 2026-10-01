{{--
    CKEditor 5 (self-hosted in public/assets/vendor/ckeditor5, GPL license key).
    Include once per page: @include('admin.partials.ckeditor')
    Every <textarea data-editor> becomes an editor; call window.wdEditors.init(element) for
    textareas added later (e.g. new repeater rows). Editors write their HTML back into the
    textarea when the form is submitted. Images upload to /admin/upload.
--}}
@push('head')
    <link rel="stylesheet" href="{{ base_url('assets/vendor/ckeditor5/ckeditor5.css') }}">
@endpush

@push('scripts')
<script src="{{ base_url('assets/vendor/ckeditor5/ckeditor5.umd.js') }}"></script>
<script src="{{ base_url('assets/vendor/ckeditor5/ja.umd.js') }}"></script>
<script>
window.wdEditors = (() => {
    const {
        ClassicEditor, Essentials, Paragraph, Heading, Bold, Italic, Underline, Strikethrough, RemoveFormat,
        FontColor, Alignment, Link, AutoLink, List, BlockQuote, HorizontalLine, CodeBlock,
        Table, TableToolbar, TableProperties, TableCellProperties,
        Image, ImageToolbar, ImageCaption, ImageStyle, ImageResize, ImageInsertViaUrl, ImageUpload, AutoImage,
        SimpleUploadAdapter, MediaEmbed, SourceEditing, GeneralHtmlSupport, PasteFromOffice, Autoformat, WordCount
    } = CKEDITOR;

    const uploadUrl = {!! json_encode(url_to('admin.upload') . '?kind=image', JSON_HEX_TAG | JSON_UNESCAPED_SLASHES) !!};
    const csrfToken = {!! json_encode(csrf_hash(), JSON_HEX_TAG) !!};
    const instances = new Map();

    const config = counter => ({
        licenseKey: 'GPL',
        language: 'ja',
        translations: [window.CKEDITOR_TRANSLATIONS],
        plugins: [
            Essentials, Paragraph, Heading, Bold, Italic, Underline, Strikethrough, RemoveFormat,
            FontColor, Alignment, Link, AutoLink, List, BlockQuote, HorizontalLine, CodeBlock,
            Table, TableToolbar, TableProperties, TableCellProperties,
            Image, ImageToolbar, ImageCaption, ImageStyle, ImageResize, ImageInsertViaUrl, ImageUpload, AutoImage,
            SimpleUploadAdapter, MediaEmbed, SourceEditing, GeneralHtmlSupport, PasteFromOffice, Autoformat, WordCount
        ],
        toolbar: {
            items: [
                'undo', 'redo', '|', 'heading', '|',
                'bold', 'italic', 'underline', 'strikethrough', 'fontColor', 'removeFormat', '|',
                'alignment', 'bulletedList', 'numberedList', '|',
                'link', 'insertImage', 'mediaEmbed', 'insertTable', 'blockQuote', 'codeBlock', 'horizontalLine', '|',
                'sourceEditing'
            ],
            shouldNotGroupWhenFull: false
        },
        heading: {
            options: [
                { model: 'paragraph', title: '段落', class: 'ck-heading_paragraph' },
                { model: 'heading2', view: 'h2', title: '見出し2', class: 'ck-heading_heading2' },
                { model: 'heading3', view: 'h3', title: '見出し3', class: 'ck-heading_heading3' },
                { model: 'heading4', view: 'h4', title: '見出し4', class: 'ck-heading_heading4' }
            ]
        },
        link: { addTargetToExternalLinks: true, defaultProtocol: 'https://' },
        image: {
            toolbar: [
                'imageTextAlternative', 'toggleImageCaption', '|',
                'imageStyle:inline', 'imageStyle:block', 'imageStyle:side', '|', 'resizeImage'
            ]
        },
        simpleUpload: { uploadUrl, headers: { 'X-CSRF-TOKEN': csrfToken } },
        table: {
            contentToolbar: ['tableColumn', 'tableRow', 'mergeTableCells', 'tableProperties', 'tableCellProperties']
        },
        // Save embeds as real <iframe> HTML so the frontend shows them without extra JavaScript.
        mediaEmbed: { previewsInData: true },
        // Keep classes and styles written in Source mode, but never scripts or inline event handlers.
        htmlSupport: {
            allow: [{ name: /^.*$/, styles: true, attributes: true, classes: true }],
            disallow: [
                { name: /^(script|iframe|object|embed|form)$/ },
                { name: /^.*$/, attributes: [{ key: /^on/i, value: true }] }
            ]
        },
        wordCount: { onUpdate: stats => counter.textContent = '文字数: ' + stats.characters.toLocaleString() }
    });

    function init(root = document) {
        root.querySelectorAll('textarea[data-editor]:not([data-editor-ready])').forEach(textarea => {
            textarea.dataset.editorReady = '1';
            const counter = document.createElement('div');
            counter.className = 'form-text text-end';
            textarea.after(counter);

            ClassicEditor.create(textarea, config(counter))
                .then(editor => {
                    instances.set(textarea, editor);
                    // Let conditional logic react to editor content.
                    editor.model.document.on('change:data', () =>
                        textarea.dispatchEvent(new CustomEvent('fields:changed', { bubbles: true })));
                })
                .catch(error => console.error('CKEditor の読み込みに失敗しました:', error));
        });
    }

    function destroy(root) {
        const pending = [...root.querySelectorAll('textarea[data-editor-ready]')].map(textarea => {
            const editor = instances.get(textarea);
            instances.delete(textarea);
            return editor?.destroy();
        });
        return Promise.all(pending);
    }

    document.addEventListener('DOMContentLoaded', () => init());

    const getData = textarea => instances.get(textarea)?.getData();

    return { init, destroy, getData };
})();
</script>
@endpush
