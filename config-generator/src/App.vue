<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { buildQuery, generateConfig, validateEndpoint, type Endpoint } from './lib/nginx'

const settings = reactive({
    serverName: 'example.com',
    publicRoot: '/var/www/example.com/public',
    storageRoot: '/var/www/example.com/storage',
    cachePrefix: 'static-vel',
    authCookie: 'static_vel_auth',
    handleInertia: true,
    runtime: 'fpm',
    fpmSocket: 'unix:/var/run/php/php8.3-fpm.sock',
    octaneAddress: '127.0.0.1:8000',
}) as {
    serverName: string
    publicRoot: string
    storageRoot: string
    cachePrefix: string
    authCookie: string
    handleInertia: boolean
    runtime: 'fpm' | 'octane'
    fpmSocket: string
    octaneAddress: string
}

const endpoints = reactive<Endpoint[]>([
    { path: '/', params: [] },
    { path: '/about', params: [] },
    { path: '/blog', params: ['category'] },
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
    endpoints.push({ path: '', params: [] })
}

function removeEndpoint(index: number): void {
    endpoints.splice(index, 1)
}

function addParam(endpoint: Endpoint): void {
    endpoint.params.push('')
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
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">
                static-vel <span class="text-emerald-600">config generator</span>
            </h1>
            <p class="mt-2 max-w-3xl text-sm text-slate-500">
                Enter your endpoints, pick your runtime, and copy the generated nginx configuration.
                The file name rules mirror the capture middleware: <code class="rounded bg-slate-100 px-1 text-slate-700">/blog?category=…</code>
                is stored as <code class="rounded bg-slate-100 px-1 text-slate-700">blog.category=….html</code>.
            </p>
        </header>

        <div class="grid gap-8 lg:grid-cols-2">
            <!-- Left column: settings + endpoints -->
            <div class="space-y-8">
                <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="mb-4 text-sm font-semibold uppercase tracking-wider text-slate-500">Settings</h2>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block">
                            <span class="mb-1 block text-xs font-medium text-slate-500">Server name</span>
                            <input
                                v-model="settings.serverName"
                                type="text"
                                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none"
                            />
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-xs font-medium text-slate-500">Auth cookie (empty = disabled)</span>
                            <input
                                v-model="settings.authCookie"
                                type="text"
                                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none"
                            />
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-xs font-medium text-slate-500">Public root</span>
                            <input
                                v-model="settings.publicRoot"
                                type="text"
                                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none"
                            />
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-xs font-medium text-slate-500">Storage root (parent of the cache)</span>
                            <input
                                v-model="settings.storageRoot"
                                type="text"
                                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none"
                            />
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-xs font-medium text-slate-500">Cache directory name</span>
                            <input
                                v-model="settings.cachePrefix"
                                type="text"
                                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none"
                            />
                        </label>
                    </div>

                    <div class="mt-5">
                        <span class="mb-2 block text-xs font-medium text-slate-500">Inertia</span>
                        <label class="flex items-center gap-2 text-sm text-slate-600">
                            <input
                                v-model="settings.handleInertia"
                                type="checkbox"
                                class="h-4 w-4 accent-emerald-600"
                            />
                            Handle Inertia (serve cached <code class="mx-1">.json</code> payloads for X-Inertia visits)
                        </label>
                        <p v-if="!settings.handleInertia" class="mt-2 text-xs text-slate-500">
                            X-Inertia requests always reach the application — only HTML page loads are served statically.
                        </p>
                    </div>

                    <div class="mt-5">
                        <span class="mb-2 block text-xs font-medium text-slate-500">Runtime</span>
                        <div class="flex gap-2">
                            <button
                                type="button"
                                :class="settings.runtime === 'fpm' ? 'border-emerald-600 bg-emerald-50 text-emerald-700' : 'border-slate-300 text-slate-600 hover:border-slate-400'"
                                class="rounded-lg border px-3 py-1.5 text-sm font-medium"
                                @click="settings.runtime = 'fpm'"
                            >
                                PHP-FPM
                            </button>
                            <button
                                type="button"
                                :class="settings.runtime === 'octane' ? 'border-emerald-600 bg-emerald-50 text-emerald-700' : 'border-slate-300 text-slate-600 hover:border-slate-400'"
                                class="rounded-lg border px-3 py-1.5 text-sm font-medium"
                                @click="settings.runtime = 'octane'"
                            >
                                Octane / PHP-PM
                            </button>
                        </div>

                        <label v-if="settings.runtime === 'fpm'" class="mt-4 block">
                            <span class="mb-1 block text-xs font-medium text-slate-500">FastCGI pass</span>
                            <input
                                v-model="settings.fpmSocket"
                                type="text"
                                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none"
                            />
                        </label>
                        <label v-else class="mt-4 block">
                            <span class="mb-1 block text-xs font-medium text-slate-500">Application server address</span>
                            <input
                                v-model="settings.octaneAddress"
                                type="text"
                                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none"
                            />
                        </label>
                    </div>
                </section>

                <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500">Endpoints</h2>
                        <button
                            type="button"
                            class="rounded-lg border border-emerald-600/50 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-100"
                            @click="addEndpoint"
                        >
                            + Add endpoint
                        </button>
                    </div>

                    <div class="space-y-4">
                        <div
                            v-for="(endpoint, index) in endpoints"
                            :key="index"
                            class="rounded-lg border border-slate-200 bg-slate-50 p-4"
                        >
                            <div class="flex items-center gap-2">
                                <input
                                    v-model="endpoint.path"
                                    type="text"
                                    placeholder="/path (empty = homepage)"
                                    class="flex-1 rounded-lg border border-slate-300 bg-white px-3 py-2 font-mono text-sm text-slate-900 focus:border-emerald-500 focus:outline-none"
                                />
                                <button
                                    type="button"
                                    class="rounded-lg border border-slate-300 px-2.5 py-2 text-xs text-slate-400 hover:border-red-300 hover:text-red-500"
                                    title="Remove endpoint"
                                    @click="removeEndpoint(index)"
                                >
                                    ✕
                                </button>
                            </div>

                            <div class="mt-3 space-y-2">
                                <div
                                    v-for="(_param, paramIndex) in endpoint.params"
                                    :key="paramIndex"
                                    class="flex items-center gap-2"
                                >
                                    <input
                                        v-model="endpoint.params[paramIndex]"
                                        type="text"
                                        placeholder="parameter name"
                                        class="flex-1 rounded-lg border border-slate-300 bg-white px-3 py-1.5 font-mono text-xs text-slate-900 focus:border-emerald-500 focus:outline-none"
                                    />
                                    <span class="text-xs text-slate-400">= any value</span>
                                    <button
                                        type="button"
                                        class="px-1 text-xs text-slate-400 hover:text-red-500"
                                        title="Remove parameter"
                                        @click="removeParam(endpoint, paramIndex)"
                                    >
                                        ✕
                                    </button>
                                </div>

                                <button
                                    type="button"
                                    class="text-xs text-slate-500 hover:text-emerald-600"
                                    @click="addParam(endpoint)"
                                >
                                    + Add query parameter
                                </button>
                            </div>
                        </div>
                    </div>

                    <p class="mt-4 text-xs text-slate-500">
                        Query parameter values are dynamic plain strings — every distinct query
                        string is cached under its own file name.
                    </p>
                </section>
            </div>

            <!-- Right column: preview + config -->
            <div class="space-y-8">
                <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="mb-4 text-sm font-semibold uppercase tracking-wider text-slate-500">Cache file preview</h2>

                    <div class="overflow-hidden rounded-lg border border-slate-200">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                                <tr>
                                    <th class="px-4 py-2.5 font-medium">Request</th>
                                    <th class="px-4 py-2.5 font-medium">Cache file</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="(result, index) in results" :key="index">
                                    <td class="px-4 py-2.5 font-mono text-xs text-slate-600">
                                        {{ endpointUrl(result.endpoint) }}
                                    </td>
                                    <td class="px-4 py-2.5 font-mono text-xs">
                                        <template v-if="result.ok">
                                            <span class="text-emerald-700">{{ settings.cachePrefix }}/{{ result.file }}.html</span>
                                            <span class="block text-slate-400">(+ .json for Inertia visits)</span>
                                        </template>
                                        <span v-else class="text-amber-600">{{ result.warning }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <p class="mt-3 text-xs text-slate-500">
                        Endpoints with warnings are served by Laravel on every visit - their responses are never captured.
                    </p>
                </section>

                <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                        <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500">Generated nginx config</h2>
                        <button
                            type="button"
                            class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-emerald-600/50 hover:text-emerald-700"
                            @click="copyConfig"
                        >
                            {{ copied ? 'Copied!' : 'Copy' }}
                        </button>
                    </div>

                    <pre class="max-h-[32rem] overflow-auto rounded-b-xl bg-slate-50 px-6 py-4 font-mono text-xs leading-relaxed text-slate-800">{{ config }}</pre>
                </section>
            </div>
        </div>
    </div>
</template>
