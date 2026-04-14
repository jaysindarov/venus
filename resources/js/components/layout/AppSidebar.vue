<script setup>
import { Link, usePage } from '@inertiajs/vue3'

const page = usePage()

const nav = [
    { label: 'Dashboard',  href: '/dashboard',     icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
    { label: 'Generate',   href: '/generate',      icon: 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z' },
    { label: 'Pricing',    href: '/billing/plans', icon: 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z' },
]
</script>

<template>
    <aside class="w-56 shrink-0 border-r border-border bg-surface-alt flex flex-col">
        <nav class="flex-1 px-3 py-4 space-y-1">
            <Link
                v-for="item in nav"
                :key="item.href"
                :href="item.href"
                :class="[
                    'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                    page.url.startsWith(item.href)
                        ? 'bg-brand/10 text-brand'
                        : 'text-text-muted hover:bg-surface hover:text-text'
                ]"
            >
                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon"/>
                </svg>
                {{ item.label }}
            </Link>
        </nav>
    </aside>
</template>
