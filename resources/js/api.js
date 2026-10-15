/* 
    // NKS browser API client
    // used by non-Livewire fetches (exports, downloads)
*/
export const NksApi = {
    baseUrl: '/api/v1',

    headers() {
        return {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        };
    },

    async request(method, path, body = null) {
        const response = await fetch(this.baseUrl + path, {
            method,
            headers: this.headers(),
            credentials: 'same-origin',
            body: body ? JSON.stringify(body) : null,
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw Object.assign(new Error(data.message || 'Request failed'), {
                status: response.status,
                data,
            });
        }

        return data;
    },

    get(path)         { return this.request('GET', path); },
    post(path, body)  { return this.request('POST', path, body); },
    put(path, body)   { return this.request('PUT', path, body); },
    patch(path, body) { return this.request('PATCH', path, body); },
    del(path)         { return this.request('DELETE', path); },

    download(url, filename = 'download') {
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    },
};

window.NksApi = NksApi;