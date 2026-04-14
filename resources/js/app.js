import './bootstrap.js'
import '../css/app.css'

import { createApp, h } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import { createPinia } from 'pinia'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'

createInertiaApp({
    title: (title) => `${title} — VisionaryAI`,
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob('./pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const pinia = createPinia()

        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(pinia)
            .mount(el)
    },
    progress: {
        color: '#6366f1',
    },
})
