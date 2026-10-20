<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div
        wire:ignore
        x-data="{
            state: $wire.$entangle('{{ $getStatePath() }}'),
            editor: null,
            wordCount: 0,
            charCount: 0,
            mediaModalOpen: false,
            mediaTab: 'upload',
            mediaTypeFilter: 'all',
            libraryItems: [],
            externalUrl: '',
            externalTitle: '',
            externalMediaType: 'auto',

            async loadMediaLibrary() {
                try {
                    const res = await fetch('/admin/media/library');
                    if (res.ok) {
                        const json = await res.json();
                        this.libraryItems = json.data || [];
                    }
                } catch (e) {}
            },

            insertHtmlToEditor(html) {
                if (!this.editor) return;
                const viewFragment = this.editor.data.processor.toView(html);
                const modelFragment = this.editor.data.toModel(viewFragment);
                this.editor.model.insertContent(modelFragment);
                this.mediaModalOpen = false;
            },

            extractGoogleDriveId(url) {
                const match = url.match(/(?:\/file\/d\/|\/d\/|[?&]id=)([a-zA-Z0-9_-]{10,})/);
                return match ? match[1] : null;
            },

            insertFromExternalLink() {
                const url = this.externalUrl.trim();
                if (!url) return;
                const title = this.externalTitle.trim() || 'Lampiran Media Redaksi';
                const driveId = this.extractGoogleDriveId(url);

                // Jika link Google Drive
                if (driveId) {
                    if (this.externalMediaType === 'image') {
                        const imgUrl = `https://drive.google.com/uc?export=view&id=${driveId}`;
                        this.insertHtmlToEditor(`<figure class='image'><img src='${imgUrl}' alt='${title}' /><figcaption>${title}</figcaption></figure>`);
                    } else {
                        const previewUrl = `https://drive.google.com/file/d/${driveId}/preview`;
                        this.insertHtmlToEditor(`<div class='my-4'><iframe src='${previewUrl}' width='100%' height='480' allow='autoplay' allowfullscreen></iframe><p><em>📄 ${title} (<a href='https://drive.google.com/file/d/${driveId}/view' target='_blank' rel='noopener noreferrer'>Buka di Google Drive</a>)</em></p></div>`);
                    }
                    this.externalUrl = '';
                    return;
                }

                // Jika link PDF
                if (url.toLowerCase().endsWith('.pdf') || this.externalMediaType === 'pdf') {
                    this.insertHtmlToEditor(`<div class='my-4'><iframe src='${url}' width='100%' height='520'></iframe><p><strong>📄 Dokumen PDF:</strong> <a href='${url}' target='_blank' rel='noopener noreferrer'>${title} (Unduh / Buka Penuh)</a></p></div>`);
                    this.externalUrl = '';
                    return;
                }

                // Jika link Video MP4/WebM
                if (/\.(mp4|webm)(\?.*)?$/i.test(url) || this.externalMediaType === 'video') {
                    this.insertHtmlToEditor(`<figure class='my-4'><video src='${url}' controls preload='metadata' width='100%'></video><figcaption>${title}</figcaption></figure>`);
                    this.externalUrl = '';
                    return;
                }

                // Default: Link Gambar
                this.insertHtmlToEditor(`<figure class='image'><img src='${url}' alt='${title}' /><figcaption>${title}</figcaption></figure>`);
                this.externalUrl = '';
            },

            init() {
                const initEditor = () => {
                    CKEDITOR.ClassicEditor
                        .create(this.$refs.ckeditor, {
                            toolbar: {
                                items: [
                                    'undo', 'redo', '|',
                                    'findAndReplace', 'selectAll', 'sourceEditing', '|',
                                    'heading', 'style', '|',
                                    'fontFamily', 'fontSize', 'fontColor', 'fontBackgroundColor', 'highlight', 'removeFormat', '|',
                                    'bold', 'italic', 'underline', 'strikethrough', 'subscript', 'superscript', 'code', '|',
                                    'alignment', 'outdent', 'indent', '|',
                                    'bulletedList', 'numberedList', 'todoList', '|',
                                    'link', 'uploadImage', 'insertImage', 'mediaEmbed', 'blockQuote', 'insertTable', 'codeBlock', 'horizontalLine', 'pageBreak', 'specialCharacters'
                                ],
                                shouldNotGroupWhenFull: true
                            },
                            htmlSupport: {
                                allow: [
                                    { name: /^(iframe|video|source|div|figure|figcaption|p|a)$/, attributes: true, classes: true, styles: true }
                                ]
                            },
                            image: {
                                resizeUnit: '%',
                                resizeOptions: [
                                    { name: 'resizeImage:original', value: null, label: 'Ukuran Asli (100%)' },
                                    { name: 'resizeImage:75', value: '75', label: '75% Lebar' },
                                    { name: 'resizeImage:50', value: '50', label: '50% Lebar' },
                                    { name: 'resizeImage:25', value: '25', label: '25% Kecil' }
                                ],
                                toolbar: [
                                    'imageStyle:inline', 'imageStyle:wrapText', 'imageStyle:breakText', '|',
                                    'toggleImageCaption', 'imageTextAlternative', '|',
                                    'resizeImage:25', 'resizeImage:50', 'resizeImage:75', 'resizeImage:original'
                                ]
                            },
                            mediaEmbed: { previewsInData: true },
                            wordCount: {
                                onUpdate: (stats) => {
                                    this.wordCount = stats.words;
                                    this.charCount = stats.characters;
                                }
                            },
                            simpleUpload: {
                                uploadUrl: '{{ $getUploadUrl() }}',
                                headers: {
                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || ''
                                }
                            },
                            removePlugins: [
                                'AIAssistant', 'CKBox', 'CKFinder', 'EasyImage', 'MultiLevelList',
                                'RealTimeCollaborativeComments', 'RealTimeCollaborativeTrackChanges',
                                'RealTimeCollaborativeRevisionHistory', 'PresenceList', 'Comments',
                                'TrackChanges', 'TrackChangesData', 'RevisionHistory', 'Pagination',
                                'WProofreader', 'MathType', 'SlashCommand', 'Template', 'DocumentOutline',
                                'FormatPainter', 'TableOfContents', 'PasteFromOfficeEnhanced', 'CaseChange'
                            ]
                        })
                        .then((editorInstance) => {
                            this.editor = editorInstance;
                            if (this.state) editorInstance.setData(this.state);
                            editorInstance.model.document.on('change:data', () => {
                                this.state = editorInstance.getData();
                            });
                        });
                };

                if (window.CKEDITOR && window.CKEDITOR.ClassicEditor) {
                    initEditor();
                } else {
                    const script = document.createElement('script');
                    script.src = 'https://cdn.ckeditor.com/ckeditor5/41.4.2/super-build/ckeditor.js';
                    script.onload = initEditor;
                    document.head.appendChild(script);
                }
            }
        }"
        class="ckeditor-filament-wrapper space-y-2"
    >
        <!-- Bar Tombol Media Manager (Gambar Direktori, Video, PDF, Google Drive) -->
        <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-gray-200 bg-gray-50 p-2.5 text-xs">
            <span class="font-semibold text-gray-700">Sisipkan Media ke Konten:</span>
            <div class="flex flex-wrap gap-2">
                <button type="button" @click="mediaModalOpen = true; mediaTab = 'library'; loadMediaLibrary()" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 font-semibold text-gray-700 hover:border-rose-500">
                    🖼️ Pilih dari Direktori Media
                </button>
                <button type="button" @click="mediaModalOpen = true; mediaTab = 'upload'" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 font-semibold text-gray-700 hover:border-rose-500">
                    🎬📄 Unggah Gambar / Video / PDF
                </button>
                <button type="button" @click="mediaModalOpen = true; mediaTab = 'link'" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 font-semibold text-gray-700 hover:border-rose-500">
                    ☁️🔗 Tempel Link URL / Google Drive
                </button>
            </div>
        </div>

        <textarea x-ref="ckeditor" class="hidden"></textarea>
    </div>
</x-dynamic-component>
