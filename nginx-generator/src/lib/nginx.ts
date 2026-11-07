// Mirrors Mehedi\StaticVel\Support\StaticPagePath and the nginx config from
// the package README - keep all three in sync.

export const QUERY_ALLOWLIST = /^[A-Za-z0-9_=&%.+,~-]+$/
export const MAX_QUERY_LENGTH = 128

export interface EndpointParam {
    key: string
    value: string
}

export interface Endpoint {
    path: string
    params: EndpointParam[]
}

export interface Settings {
    serverName: string
    publicRoot: string
    storageRoot: string
    cachePrefix: string
    authCookie: string
    runtime: 'fpm' | 'octane'
    fpmSocket: string
    octaneAddress: string
}

export type Validation =
    | { ok: true; file: string; warning: null }
    | { ok: false; file: null; warning: string }

type QueryResult = { token: string; warning: null } | { token: null; warning: string }

/**
 * Build the raw query string from the endpoint's parameter rows, in the
 * order they were entered - the same string nginx will see in $args.
 */
export function buildQuery(params: EndpointParam[]): string {
    return params
        .filter((param) => param.key !== '' || param.value !== '')
        .map((param) => (param.value === '' ? param.key : `${param.key}=${param.value}`))
        .join('&')
}

/**
 * Split the path into safe segments, or null when it attempts directory
 * traversal.
 */
export function safeSegments(path: string): string[] | null {
    const segments = path.replaceAll('\\', '/').split('/')

    if (segments.some((segment) => segment === '..' || segment.includes('\0'))) {
        return null
    }

    return segments.filter((segment) => segment !== '' && segment !== '.')
}

/**
 * The query string token as it is embedded into the file name, or null
 * when the query string is unsafe to cache.
 */
export function safeQuery(query: string | null): QueryResult {
    if (!query) {
        return { token: '', warning: null }
    }

    if (query.length > MAX_QUERY_LENGTH) {
        return { token: null, warning: `longer than ${MAX_QUERY_LENGTH} characters` }
    }

    if (!QUERY_ALLOWLIST.test(query)) {
        return { token: null, warning: 'contains characters outside the allowlist' }
    }

    let decoded: string

    try {
        decoded = decodeURIComponent(query)
    } catch {
        return { token: null, warning: 'contains malformed percent-encoding' }
    }

    if (['/', '\\', '\0', '..'].some((needle) => decoded.includes(needle))) {
        return { token: null, warning: 'decodes to a directory traversal attempt' }
    }

    return { token: query, warning: null }
}

/**
 * Validate a single endpoint and resolve the cache file it maps to.
 */
export function validateEndpoint(endpoint: Endpoint): Validation {
    const query = safeQuery(buildQuery(endpoint.params))

    if (query.warning !== null) {
        return { ok: false, file: null, warning: `Query string ${query.warning} - served by Laravel, never cached.` }
    }

    const segments = safeSegments(endpoint.path)

    if (segments === null) {
        return { ok: false, file: null, warning: 'Path attempts directory traversal - never cached.' }
    }

    const base = segments.length === 0 ? 'index' : segments.join('/')
    const token = query.token === '' ? '' : `.${query.token}`

    return { ok: true, file: `${base}${token}`, warning: null }
}

/**
 * Generate the complete nginx server block for the given settings.
 */
export function generateConfig(settings: Settings, results: Array<{ endpoint: Endpoint } & Validation>): string {
    const prefix = settings.cachePrefix.replace(/^\/+|\/+$/g, '')

    const endpointLines = results.map(({ endpoint, ok, file, warning }) => {
        const path = endpoint.path === '' ? '/' : endpoint.path
        const query = buildQuery(endpoint.params)
        const url = `${path}${query === '' ? '' : `?${query}`}`

        return `#   ${url.padEnd(45)}${ok ? `-> ${prefix}/${file}.html` : `!! ${warning}`}`
    })

    const cookieGuard = settings.authCookie
        ? [
            '        # Logged-in visitors always get fresh content from Laravel.',
            `        if ($cookie_${settings.authCookie}) {`,
            '            return 419;',
            '        }',
            '',
        ]
        : []

    const fallback = settings.runtime === 'octane'
        ? [
            '    # Fallback: hand the ORIGINAL request URI to the application server.',
            '    # $request_uri is untouched by the rewrites above.',
            '    location @laravel {',
            `        proxy_pass http://${settings.octaneAddress}$request_uri;`,
            '',
            '        proxy_set_header Host $host;',
            '        proxy_set_header X-Real-IP $remote_addr;',
            '        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;',
            '        proxy_set_header X-Forwarded-Proto $scheme;',
            '    }',
        ]
        : [
            '    # Fallback: let Laravel render (and capture) the page.',
            '    location @laravel {',
            '        rewrite ^ /index.php last;',
            '    }',
            '',
            '    location ~ \\.php$ {',
            '        include snippets/fastcgi-php.conf;',
            `        fastcgi_pass ${settings.fpmSocket};`,
            '    }',
        ]

    return [
        '# nginx configuration for the static-vel package.',
        '# Generated by the nginx-generator tool.',
        '#',
        `# Endpoints served from the ${prefix} cache:`,
        ...endpointLines,
        '#',
        '# Every file also exists as .json when it was captured from an Inertia visit.',
        '',
        'server {',
        '    listen 80;',
        `    server_name ${settings.serverName};`,
        '',
        `    root ${settings.publicRoot};`,
        '    index index.php;',
        '',
        '    # Internal redirect targets:',
        '    #   418 -> requests with a query string, served from their own file',
        '    #   419 -> "hand this to Laravel"',
        '    error_page 418 = @static_query;',
        '    error_page 419 = @laravel;',
        '',
        '    # 1. Rewrite every page request to its static file.',
        '    location / {',
        ...cookieGuard,
        '        # Partial reloads ask for fresh data - always let Laravel answer.',
        '        if ($http_x_inertia_partial_component) {',
        '            return 419;',
        '        }',
        '',
        '        # Query strings that must never become part of a file name -',
        '        # let Laravel answer. Mirrored by the capture middleware.',
        '        if ($args ~ "[^A-Za-z0-9_=&%.+,~-]") {',
        '            return 419;',
        '        }',
        '        if ($args ~ "(%2F|%2f|%5C|%5c)") {',
        '            return 419;',
        '        }',
        '        if ($args ~ "\\.\\.") {',
        '            return 419;',
        '        }',
        '',
        '        # Requests with a query string are served from their own file.',
        '        if ($args) {',
        '            return 418;',
        '        }',
        '',
        '        # Inertia navigations get the cached JSON payload, browsers get HTML.',
        '        set $ext html;',
        '        if ($http_x_inertia) {',
        '            set $ext json;',
        '        }',
        '',
        `        rewrite ^/$ /${prefix}/index.$ext last;`,
        `        rewrite ^ /${prefix}$uri.$ext last;`,
        '    }',
        '',
        '    # 2. Same as above, but the file name carries the query string.',
        '    location @static_query {',
        '        set $ext html;',
        '        if ($http_x_inertia) {',
        '            set $ext json;',
        '        }',
        '',
        `        rewrite ^/$ /${prefix}/index.$args.$ext last;`,
        `        rewrite ^ /${prefix}$uri.$args.$ext last;`,
        '    }',
        '',
        `    # 3. Serve the rewritten path from the ${settings.storageRoot}/${prefix} directory.`,
        '    #    `internal` keeps the cache from being requested directly.',
        `    location ^~ /${prefix}/ {`,
        '        internal;',
        '',
        '        # Defense in depth: $uri is already normalized by nginx, and',
        '        # the middleware never stores such file names.',
        '        if ($uri ~ "\\.\\.") {',
        '            return 404;',
        '        }',
        '',
        `        root ${settings.storageRoot};`,
        '',
        '        try_files $uri @laravel;',
        '    }',
        '',
        ...fallback,
        '}',
    ].join('\n')
}
