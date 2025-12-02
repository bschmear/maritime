<template>
    <div class="tt-editor">
        <InputLabel v-if="label" :for="id" :value="label" class="text-gray-900 dark:text-white" />
        
        <!-- Toolbar -->
        <div class="border border-gray-300 dark:border-gray-600 border-b-0 rounded-t-lg bg-gray-50 dark:bg-gray-800/50 toolbar mt-2">
            <div class="px-3 py-2 border-b border-gray-200 dark:border-gray-600">
                <div class="flex flex-wrap items-center gap-1">
                    <!-- Text formatting -->
                    <div class="flex items-center gap-1">
                        <button
                            @click="editor.chain().focus().toggleBold().run()"
                            :class="{ 'bg-secondary-100 dark:bg-secondary-900/50 text-secondary-600 dark:text-secondary-400': editor?.isActive('bold') }"
                            class="p-2 text-gray-600 dark:text-gray-400 rounded hover:text-gray-900 dark:hover:text-white hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                            type="button"
                            title="Bold (Ctrl+B)"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 4h8a4 4 0 014 4 4 4 0 01-4 4H6zm0 8h9a4 4 0 014 4 4 4 0 01-4 4H6z"/>
                            </svg>
                        </button>

                        <button
                            @click="editor.chain().focus().toggleItalic().run()"
                            :class="{ 'bg-secondary-100 dark:bg-secondary-900/50 text-secondary-600 dark:text-secondary-400': editor?.isActive('italic') }"
                            class="p-2 text-gray-600 dark:text-gray-400 rounded hover:text-gray-900 dark:hover:text-white hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                            type="button"
                            title="Italic (Ctrl+I)"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 4h6M8 20h6M13 4l-4 16"/>
                            </svg>
                        </button>

                        <button
                            @click="editor.chain().focus().toggleUnderline().run()"
                            :class="{ 'bg-secondary-100 dark:bg-secondary-900/50 text-secondary-600 dark:text-secondary-400': editor?.isActive('underline') }"
                            class="p-2 text-gray-600 dark:text-gray-400 rounded hover:text-gray-900 dark:hover:text-white hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                            type="button"
                            title="Underline (Ctrl+U)"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 3v7a6 6 0 0012 0V3M4 21h16"/>
                            </svg>
                        </button>

                        <button
                            @click="editor.chain().focus().toggleStrike().run()"
                            :class="{ 'bg-secondary-100 dark:bg-secondary-900/50 text-secondary-600 dark:text-secondary-400': editor?.isActive('strike') }"
                            class="p-2 text-gray-600 dark:text-gray-400 rounded hover:text-gray-900 dark:hover:text-white hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                            type="button"
                            title="Strikethrough"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 12h12M8 5s1-1 4-1 4 1 4 1M8 19s1 1 4 1 4-1 4-1"/>
                            </svg>
                        </button>
                    </div>

                    <div class="w-px h-6 bg-gray-300 dark:bg-gray-600 mx-1"></div>

                    <!-- Headers -->
                    <div class="flex items-center gap-1">
                        <button
                            @click="editor.chain().focus().toggleHeading({ level: 1 }).run()"
                            :class="{ 'bg-secondary-100 dark:bg-secondary-900/50 text-secondary-600 dark:text-secondary-400': editor?.isActive('heading', { level: 1 }) }"
                            class="px-2.5 py-1.5 text-sm font-bold text-gray-600 dark:text-gray-400 rounded hover:text-gray-900 dark:hover:text-white hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                            type="button"
                            title="Heading 1"
                        >
                            H1
                        </button>

                        <button
                            @click="editor.chain().focus().toggleHeading({ level: 2 }).run()"
                            :class="{ 'bg-secondary-100 dark:bg-secondary-900/50 text-secondary-600 dark:text-secondary-400': editor?.isActive('heading', { level: 2 }) }"
                            class="px-2.5 py-1.5 text-sm font-bold text-gray-600 dark:text-gray-400 rounded hover:text-gray-900 dark:hover:text-white hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                            type="button"
                            title="Heading 2"
                        >
                            H2
                        </button>

                        <button
                            @click="editor.chain().focus().toggleHeading({ level: 3 }).run()"
                            :class="{ 'bg-secondary-100 dark:bg-secondary-900/50 text-secondary-600 dark:text-secondary-400': editor?.isActive('heading', { level: 3 }) }"
                            class="px-2.5 py-1.5 text-sm font-bold text-gray-600 dark:text-gray-400 rounded hover:text-gray-900 dark:hover:text-white hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                            type="button"
                            title="Heading 3"
                        >
                            H3
                        </button>

                        <button
                            @click="editor.chain().focus().toggleHeading({ level: 4 }).run()"
                            :class="{ 'bg-secondary-100 dark:bg-secondary-900/50 text-secondary-600 dark:text-secondary-400': editor?.isActive('heading', { level: 4 }) }"
                            class="px-2.5 py-1.5 text-sm font-bold text-gray-600 dark:text-gray-400 rounded hover:text-gray-900 dark:hover:text-white hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                            type="button"
                            title="Heading 4"
                        >
                            H4
                        </button>
                    </div>

                    <div class="w-px h-6 bg-gray-300 dark:bg-gray-600 mx-1"></div>

                    <!-- Lists -->
                    <div class="flex items-center gap-1">
                        <button
                            @click="editor.chain().focus().toggleBulletList().run()"
                            :class="{ 'bg-secondary-100 dark:bg-secondary-900/50 text-secondary-600 dark:text-secondary-400': editor?.isActive('bulletList') }"
                            class="p-2 text-gray-600 dark:text-gray-400 rounded hover:text-gray-900 dark:hover:text-white hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                            type="button"
                            title="Bullet List"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>
                            </svg>
                        </button>

                        <button
                            @click="editor.chain().focus().toggleOrderedList().run()"
                            :class="{ 'bg-secondary-100 dark:bg-secondary-900/50 text-secondary-600 dark:text-secondary-400': editor?.isActive('orderedList') }"
                            class="p-2 text-gray-600 dark:text-gray-400 rounded hover:text-gray-900 dark:hover:text-white hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                            type="button"
                            title="Numbered List"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 6h12" />
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h12" />
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 18h12" />
                              <text x="2" y="7" font-size="4" font-family="sans-serif" fill="currentColor">1</text>
                              <text x="2" y="13" font-size="4" font-family="sans-serif" fill="currentColor">2</text>
                              <text x="2" y="19" font-size="4" font-family="sans-serif" fill="currentColor">3</text>
                            </svg>

                        </button>
                    </div>

                    <div class="w-px h-6 bg-gray-300 dark:bg-gray-600 mx-1"></div>

                    <!-- Quote and code -->
                    <div class="flex items-center gap-1">
                        <button
                            @click="editor.chain().focus().toggleBlockquote().run()"
                            :class="{ 'bg-secondary-100 dark:bg-secondary-900/50 text-secondary-600 dark:text-secondary-400': editor?.isActive('blockquote') }"
                            class="p-2 text-gray-600 dark:text-gray-400 rounded hover:text-gray-900 dark:hover:text-white hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                            type="button"
                            title="Quote"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M7 8h10M7 12h10M7 16h10M3 4h18v16H3z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 8h1M3 12h1M3 16h1"/>
                            </svg>
                        </button>

                        <button
                            @click="editor.chain().focus().toggleCode().run()"
                            :class="{ 'bg-secondary-100 dark:bg-secondary-900/50 text-secondary-600 dark:text-secondary-400': editor?.isActive('code') }"
                            class="p-2 text-gray-600 dark:text-gray-400 rounded hover:text-gray-900 dark:hover:text-white hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                            type="button"
                            title="Code"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                            </svg>
                        </button>

                        <button
                            @click="editor.chain().focus().toggleCodeBlock().run()"
                            :class="{ 'bg-secondary-100 dark:bg-secondary-900/50 text-secondary-600 dark:text-secondary-400': editor?.isActive('codeBlock') }"
                            class="p-2 text-gray-600 dark:text-gray-400 rounded hover:text-gray-900 dark:hover:text-white hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                            type="button"
                            title="Code Block"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </button>
                    </div>

                    <div class="w-px h-6 bg-gray-300 dark:bg-gray-600 mx-1"></div>

                    <!-- Alignment -->
                    <div class="flex items-center gap-1">
                        <button
                            @click="editor.chain().focus().setTextAlign('left').run()"
                            :class="{ 'bg-secondary-100 dark:bg-secondary-900/50 text-secondary-600 dark:text-secondary-400': editor?.isActive({ textAlign: 'left' }) }"
                            class="p-2 text-gray-600 dark:text-gray-400 rounded hover:text-gray-900 dark:hover:text-white hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                            type="button"
                            title="Align Left"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 6h16M4 12h10M4 18h16"/>
                            </svg>
                        </button>

                        <button
                            @click="editor.chain().focus().setTextAlign('center').run()"
                            :class="{ 'bg-secondary-100 dark:bg-secondary-900/50 text-secondary-600 dark:text-secondary-400': editor?.isActive({ textAlign: 'center' }) }"
                            class="p-2 text-gray-600 dark:text-gray-400 rounded hover:text-gray-900 dark:hover:text-white hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                            type="button"
                            title="Align Center"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 6h16M7 12h10M4 18h16"/>
                            </svg>
                        </button>

                        <button
                            @click="editor.chain().focus().setTextAlign('right').run()"
                            :class="{ 'bg-secondary-100 dark:bg-secondary-900/50 text-secondary-600 dark:text-secondary-400': editor?.isActive({ textAlign: 'right' }) }"
                            class="p-2 text-gray-600 dark:text-gray-400 rounded hover:text-gray-900 dark:hover:text-white hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                            type="button"
                            title="Align Right"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 6h16M10 12h10M4 18h16"/>
                            </svg>
                        </button>

                        <button
                            @click="editor.chain().focus().setHorizontalRule().run()"
                            class="p-2 text-gray-600 dark:text-gray-400 rounded hover:text-gray-900 dark:hover:text-white hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                            type="button"
                            title="Horizontal Rule"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 12h14"/>
                            </svg>
                        </button>
                    </div>

                    <div class="w-px h-6 bg-gray-300 dark:bg-gray-600 mx-1"></div>

                    <!-- Link and utilities -->
                    <div class="flex items-center gap-1">
                        <button
                            @click="setLink"
                            :class="{ 'bg-secondary-100 dark:bg-secondary-900/50 text-secondary-600 dark:text-secondary-400': editor?.isActive('link') }"
                            class="p-2 text-gray-600 dark:text-gray-400 rounded hover:text-gray-900 dark:hover:text-white hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                            type="button"
                            title="Add Link"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                            </svg>
                        </button>

                        <button
                            v-if="showAnchor"
                            @click="promptAnchor"
                            class="p-2 text-gray-600 dark:text-gray-400 rounded hover:text-gray-900 dark:hover:text-white hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                            type="button"
                            title="Add Anchor ID"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/>
                            </svg>
                        </button>

                        <button
                            @click="editor.chain().focus().clearNodes().unsetAllMarks().run()"
                            class="p-2 text-gray-600 dark:text-gray-400 rounded hover:text-gray-900 dark:hover:text-white hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                            type="button"
                            title="Clear Formatting"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Editor -->
        <editor-content
            :editor="editor"
            class="prose prose-sm sm:prose lg:prose-lg max-w-none block w-full px-4 py-3 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-b-lg focus-within:ring-2 focus-within:ring-secondary-500 focus-within:border-secondary-500 dark:focus-within:ring-secondary-400 dark:focus-within:border-secondary-400 min-h-[300px] transition-colors"
        />

        <InputError v-if="error" class="mt-2" :message="error" />
    </div>
</template>

<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue'
import { Editor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Underline from '@tiptap/extension-underline'
import TextAlign from '@tiptap/extension-text-align'
import Link from '@tiptap/extension-link'
import { Node } from '@tiptap/core'
import InputLabel from '@/Components/InputLabel.vue'
import InputError from '@/Components/InputError.vue'

const props = defineProps({
    id: {
        type: String,
        default: 'editor'
    },
    label: {
        type: String,
        default: ''
    },
    modelValue: {
        type: String,
        default: ''
    },
    showAnchor: {
        type: Boolean,
        default: false
    },
    error: {
        type: String,
        default: ''
    }
})

const emit = defineEmits(['update:modelValue'])

// Anchor extension
const Anchor = Node.create({
    name: 'anchor',
    inline: false,
    group: 'block',
    atom: true,
    selectable: false,
    draggable: false,

    addAttributes() {
        return {
            id: { default: null },
        }
    },

    parseHTML() {
        return [
            { tag: 'span[data-anchor-id]' },
        ]
    },

    renderHTML({ HTMLAttributes }) {
        return [
            'span',
            {
                id: HTMLAttributes.id,
                'data-anchor-id': HTMLAttributes.id,
                class: 'scroll-offset',
            },
        ]
    },

    addCommands() {
        return {
            setAnchor: (id) => ({ chain }) => {
                return chain()
                    .insertContent({
                        type: this.name,
                        attrs: { id },
                    })
                    .run()
            },
        }
    },
})

const editor = ref(null)

onMounted(() => {
    editor.value = new Editor({
        extensions: [
            StarterKit,
            Underline,
            TextAlign.configure({ types: ['heading', 'paragraph'] }),
            Link.configure({
                openOnClick: false,
                HTMLAttributes: {
                    class: 'text-secondary-600 dark:text-secondary-400 underline hover:text-secondary-800 dark:hover:text-secondary-300'
                },
            }),
            ...(props.showAnchor ? [Anchor] : [])
        ],
        content: props.modelValue,
        onUpdate: ({ editor }) => {
            emit('update:modelValue', editor.getHTML())
        },
    })
})

onBeforeUnmount(() => {
    if (editor.value) {
        editor.value.destroy()
    }
})

watch(() => props.modelValue, (value) => {
    const isSame = editor.value.getHTML() === value
    if (isSame) {
        return
    }
    editor.value.commands.setContent(value, false)
})

const setLink = () => {
    if (!editor.value) return

    const previousUrl = editor.value.getAttributes('link').href
    const url = window.prompt('URL', previousUrl)

    if (url === null) return

    if (url === '') {
        editor.value.chain().focus().extendMarkRange('link').unsetLink().run()
        return
    }

    editor.value.chain().focus().extendMarkRange('link').setLink({ href: url }).run()
}

const promptAnchor = () => {
    if (!editor.value) return

    const { state } = editor.value
    const { from } = state.selection
    const { doc } = state

    let pos = from
    let parent = doc.nodeAt(pos)

    while (parent && parent.type.isInline) {
        pos -= 1
        parent = doc.nodeAt(pos)
    }

    if (!parent) {
        window.alert('Could not find a block to attach anchor to.')
        return
    }

    const input = window.prompt('Anchor id (used as element id, e.g. "section-1"):', '')
    if (!input) return

    const id = input.trim()
    if (!id) return

    editor.value
        .chain()
        .insertContentAt(pos, {
            type: 'anchor',
            attrs: { id },
        })
        .run()
}
</script>

<style>
/* TipTap Editor Styles */
.ProseMirror {
    outline: none;
    min-height: 300px;
}

.ProseMirror > * + * {
    margin-top: 0.75em;
}

.ProseMirror h1 {
    font-size: 2em;
    font-weight: bold;
    line-height: 1.2;
}

.ProseMirror h2 {
    font-size: 1.5em;
    font-weight: bold;
    line-height: 1.3;
}

.ProseMirror h3 {
    font-size: 1.25em;
    font-weight: bold;
    line-height: 1.4;
}

.ProseMirror h4 {
    font-size: 1.1em;
    font-weight: bold;
    line-height: 1.4;
}

.ProseMirror ul,
.ProseMirror ol {
    padding: 0 1rem;
    margin: 1rem 0;
}

.ProseMirror ul {
    list-style: disc;
}

.ProseMirror ol {
    list-style: decimal;
}

.ProseMirror blockquote {
    padding-left: 1rem;
    border-left: 3px solid #d1d5db;
    font-style: italic;
    margin: 1rem 0;
}

.dark .ProseMirror blockquote {
    border-left-color: #4b5563;
}

.ProseMirror code {
    background-color: #f3f4f6;
    color: #1f2937;
    padding: 0.2em 0.4em;
    border-radius: 0.25rem;
    font-size: 0.875em;
    font-family: 'Courier New', monospace;
}

.dark .ProseMirror code {
    background-color: #374151;
    color: #f9fafb;
}

.ProseMirror pre {
    background-color: #1f2937;
    color: #f9fafb;
    padding: 0.75rem 1rem;
    border-radius: 0.5rem;
    overflow-x: auto;
    font-family: 'Courier New', monospace;
}

.dark .ProseMirror pre {
    background-color: #111827;
}

.ProseMirror pre code {
    background: none;
    color: inherit;
    padding: 0;
}

.ProseMirror hr {
    border: none;
    border-top: 2px solid #e5e7eb;
    margin: 2rem 0;
}

.dark .ProseMirror hr {
    border-top-color: #4b5563;
}

.ProseMirror a {
    color: #4f46e5;
    text-decoration: underline;
    cursor: pointer;
}

.dark .ProseMirror a {
    color: #818cf8;
}

.ProseMirror a:hover {
    color: #4338ca;
}

.dark .ProseMirror a:hover {
    color: #a5b4fc;
}

.ProseMirror p.is-editor-empty:first-child::before {
    color: #9ca3af;
    content: attr(data-placeholder);
    float: left;
    height: 0;
    pointer-events: none;
}
</style>
