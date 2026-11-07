<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { buildQuery, generateConfig, validateEndpoint, type Endpoint } from './lib/nginx'

const settings = reactive({
    serverName: 'example.com',
    publicRoot: '/var/www/example.com/public',
    storageRoot: '/var/www/example.com/storage',
    cachePrefix: 'static-vel',
    authCookie: 'static_vel_auth',
    runtime: 'fpm',
    fpmSocket: 'unix:/var/run/php/php8.3-fpm.sock',
    octaneAddress: '127.0.0.1:8000',
}) as {
    serverName: string
    publicRoot: string
    storageRoot: string
    cachePrefix: string
    authCookie: string
    runtime: 'fpm' | 'octane'
    fpmSocket: string
    octaneAddress: string
}

const endpoints = reactive<Endpoint[]>([
    { path: '/', params: [] },
    { path: '/about', params: [] },
    { path: '/blog', params: [{ key: 'category', value: 'e-commerce' }] },
])

const results = computed(() =>
    endpoints.map((endpoint) => ({ endpoint, ...validateEndpoint(endpoint) })),
)

const config = computed(() => generateConfig(settings, results.value))

const copied = ref(false)

async function copyConfig(): Promise<void> {
    await navigator.clipboard.writeText(config.value)
    copied.value = true
    setTimeout(() => (copied.value = false), 1500)
}

function addEndpoint(): void {
    endpoints.push({ path: '', params: [{ key: '', value: '' }] })
}

function removeEndpoint(index: number): void {
    endpoints.splice(index, 1)
}

function addParam(endpoint: Endpoint): void {
    endpoint.params.push({ key: '', value: '' })
}

function removeParam(endpoint: Endpoint, index: number): void {
    endpoint.params.splice(index, 1)
}

function endpointUrl(endpoint: Endpoint): string {
    const path = endpoint.path === '' ? '/' : endpoint.path
    const query = buildQuery(endpoint.params)

    return `${path}${query === '' ? '' : `?${query}`}`
}
</script>

<template>
    <div class="mx-auto max-w-7xl px-6 py-10">
        <header class="mb-10">
            <h1 class="text-2xl font-bold tracking-tight text-white">
                static-vel <span class="text-emerald-400">nginx generator</span>
            </h1>
            <p class="mt-2 max-w-3xl text-sm text-slate-400">
                Enter your endpoints, pick your runtime, and copy the generated nginx configuration.
                The file name rules mirror the capture middleware: <code class="text-slate-300">/blog?category=e-commerce</code>
                is stored as <code class="text-slate-300">blog.category=e-commerce.html</code>.
            </p>
        </header>

        <div class="grid gap-8 lg:grid-cols-2">
            <!-- Left column: settings + endpoints -->
            <div class="space-y-8">
                <section class="rounded-xl border border-slate-800 bg-slate-900 p-6">
                    <h2 class="mb-4 text-sm font-semibold uppercase tracking-wider text-slate-400">Settings</h2>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block">
                            <span class="mb-1 block text-xs font-medium text-slate-400">Server name</span>
                            <input
                                v-model="settings.serverName"
                                type="text"
                                class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none"
                            />
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-xs font-medium text-slate-400">Auth cookie (empty = disabled)</span>
                            <input
                                v-model="settings.authCookie"
                                type="text"
                                class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none"
                            />
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-xs font-medium text-slate-400">Public root</span>
                            <input
                                v-model="settings.publicRoot"
                                type="text"
                                class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none"
                            />
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-xs font-medium text-slate-400">Storage root (parent of the cache)</span>
                            <input
                                v-model="settings.storageRoot"
                                type="text"
                                class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none"
                            />
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-xs font-medium text-slate-400">Cache directory name</span>
                            <input
                                v-model="settings.cachePrefix"
                                type="text"
                                class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none"
                            />
                        </label>
                    </div>

                    <div class="mt-5">
                        <span class="mb-2 block text-xs font-medium text-slate-400">Runtime</span>
                        <div class="flex gap-2">
                            <button
                                type="button"
                                :class="settings.runtime === 'fpm' ? 'border-emerald-500 bg-emerald-500/10 text-emerald-300' : 'border-slate-700 text-slate-400 hover:border-slate-600'"
                                class="rounded-lg border px-3 py-1.5 text-sm font-medium"
                                @click="settings.runtime = 'fpm'"
                            >
                                PHP-FPM
                            </button>
                            <button
                                type="button"
                                :class="settings.runtime === 'octane' ? 'border-emerald-500 bg-emerald-500/10 text-emerald-300' : 'border-slate-700 text-slate-400 hover:border-slate-600'"
                                class="rounded-lg border px-3 py-1.5 text-sm font-medium"
                                @click="settings.runtime = 'octane'"
                            >
                                Octane / PHP-PM
                            </button>
                        </div>

                        <label v-if="settings.runtime === 'fpm'" class="mt-4 block">
                            <span class="mb-1 block text-xs font-medium text-slate-400">FastCGI pass</span>
                            <input
                                v-model="settings.fpmSocket"
                                type="text"
                                class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none"
                            />
                        </label>
                        <label v-else class="mt-4 block">
                            <span class="mb-1 block text-xs font-medium text-slate-400">Application server address</span>
                            <input
                                v-model="settings.octaneAddress"
                                type="text"
                                class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none"
                            />
                        </label>
                    </div>
                </section>

                <section class="rounded-xl border border-slate-800 bg-slate-900 p-6">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-400">Endpoints</h2>
                        <button
                            type="button"
                            class="rounded-lg border border-emerald-500/50 bg-emerald-500/10 px-3 py-1.5 text-xs font-semibold text-emerald-300 hover:bg-emerald-500/20"
                            @click="addEndpoint"
                        >
                            + Add endpoint
                        </button>
                    </div>

                    <div class="space-y-4">
                        <div
                            v-for="(endpoint, index) in endpoints"
                            :key="index"
                            class="rounded-lg border border-slate-800 bg-slate-950 p-4"
                        >
                            <div class="flex items-center gap-2">
                                <input
                                    v-model="endpoint.path"
                                    type="text"
                                    placeholder="/path (empty = homepage)"
                                    class="flex-1 rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 font-mono text-sm focus:border-emerald-500 focus:outline-none"
                                />
                                <button
                                    type="button"
                                    class="rounded-lg border border-slate-700 px-2.5 py-2 text-xs text-slate-400 hover:border-red-500/50 hover:text-red-400"
                                    title="Remove endpoint"
                                    @click="removeEndpoint(index)"
                                >
                                    ✕
                                </button>
                            </div>

                            <div class="mt-3 space-y-2">
                                <div
                                    v-for="(param, paramIndex) in endpoint.params"
                                    :key="paramIndex"
                                    class="flex items-center gap-2"
                                >
                                    <input
                                        v-model="param.key"
                                        type="text"
                                        placeholder="key"
                                        class="w-36 rounded-lg border border-slate-700 bg-slate-900 px-3 py-1.5 font-mono text-xs focus:border-emerald-500 focus:outline-none"
                                    />
                                    <span class="text-slate-500">=</span>
                                    <input
                                        v-model="param.value"
                                        type="text"
                                        placeholder="value"
                                        class="flex-1 rounded-lg border border-slate-700 bg-slate-900 px-3 py-1.5 font-mono text-xs focus:border-emerald-500 focus:outline-none"
                                    />
                                    <button
                                        type="button"
                                        class="px-1 text-xs text-slate-500 hover:text-red-400"
                                        title="Remove parameter"
                                        @click="removeParam(endpoint, paramIndex)"
                                    >
                                        ✕
                                    </button>
                                </div>

                                <button
                                    type="button"
                                    class="text-xs text-slate-500 hover:text-emerald-400"
                                    @click="addParam(endpoint)"
                                >
                                    + Add query parameter
                                </button>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Right column: preview + config -->
            <div class="space-y-8">
                <section class="rounded-xl border border-slate-800 bg-slate-900 p-6">
                    <h2 class="mb-4 text-sm font-semibold uppercase tracking-wider text-slate-400">Cache file preview</h2>

                    <div class="overflow-hidden rounded-lg border border-slate-800">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-950 text-xs uppercase tracking-wider text-slate-500">
                                <tr>
                                    <th class="px-4 py-2.5 font-medium">Request</th>
                                    <th class="px-4 py-2.5 font-medium">Cache file</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800">
                                <tr v-for="(result, index) in results" :key="index">
                                    <td class="px-4 py-2.5 font-mono text-xs text-slate-300">
                                        {{ endpointUrl(result.endpoint) }}
                                    </td>
                                    <td class="px-4 py-2.5 font-mono text-xs">
                                        <template v-if="result.ok">
                                            <span class="text-emerald-400">{{ settings.cachePrefix }}/{{ result.file }}.html</span>
                                            <span class="block text-slate-500">(+ .json for Inertia visits)</span>
                                        </template>
                                        <span v-else class="text-amber-400">{{ result.warning }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <p class="mt-3 text-xs text-slate-500">
                        Endpoints with warnings are served by Laravel on every visit - their responses are never captured.
                    </p>
                </section>

                <section class="rounded-xl border border-slate-800 bg-slate-900">
                    <div class="flex items-center justify-between border-b border-slate-800 px-6 py-4">
                        <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-400">Generated nginx config</h2>
                        <button
                            type="button"
                            class="rounded-lg border border-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:border-emerald-500/50 hover:text-emerald-300"
                            @click="copyConfig"
                        >
                            {{ copied ? 'Copied!' : 'Copy' }}
                        </button>
                    </div>

                    <pre class="max-h-[32rem] overflow-auto px-6 py-4 font-mono text-xs leading-relaxed text-slate-300">{{ config }}</pre>
                </section>
            </div>
        </div>
    </div>
</template>
