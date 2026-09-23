import axios from 'axios';
import { ref, watch, onBeforeUnmount } from 'vue';

// Preview only option data. Inertia navigation (and therefore record changes) stays with Apply.
export function useLiveFilterOptions(url, draft, initialOptions) {
    const options = ref(initialOptions());
    const loading = ref(false);
    const error = ref('');
    let timer;
    let controller;
    let sequence = 0;
    function cancel() {
        sequence++;
        clearTimeout(timer);
        controller?.abort();
        loading.value = false;
    }
    async function fetchOptions(snapshot, request) {
        controller = new AbortController();
        try {
            const { data } = await axios.get(url(), { params: { ...snapshot, page: undefined, filter_options: 1 }, signal: controller.signal });
            if (!data || typeof data !== 'object' || Array.isArray(data)) throw new Error('Invalid options response');
            if (request === sequence) options.value = data;
        } catch (failure) {
            if (request === sequence && !axios.isCancel(failure)) error.value = 'Unable to refresh choices. Try again or click Apply filters.';
        } finally {
            if (request === sequence) loading.value = false;
        }
    }
    function schedule(snapshot, previous = {}) {
        cancel();
        error.value = '';
        loading.value = true;
        const request = sequence;
        const delay = snapshot.search !== previous.search ? 300 : 0;
        timer = setTimeout(() => fetchOptions(snapshot, request), delay);
    }
    watch(draft, (value, previous) => schedule({ ...value }, previous), { deep: true, flush: 'sync' });
    watch(initialOptions, value => { cancel(); options.value = value; error.value = ''; }, { deep: true });
    onBeforeUnmount(cancel);
    return { options, loading, error, retry: () => schedule({ ...draft() }) };
}
