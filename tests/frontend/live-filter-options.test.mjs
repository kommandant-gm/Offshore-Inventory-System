import assert from 'node:assert/strict';
import { test } from 'node:test';
import axios from 'axios';
import { createRenderer, h, nextTick, ref } from 'vue';
import { useLiveFilterOptions } from '../../resources/js/Composables/useLiveFilterOptions.js';

const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
function setup(t) {
    const calls = [];
    const original = axios.get;
    axios.get = (url, config) => new Promise((resolve, reject) => calls.push({ url, ...config, resolve, reject }));
    const draft = ref({ category: '', search: '', company: '' });
    const initial = ref({ options: { location: ['BTU', 'LBN'] } });
    const renderer = createRenderer({
        createElement: () => ({}), createComment: () => ({}), insert() {}, remove() {},
        setElementText() {}, patchProp() {}, parentNode: () => null, nextSibling: () => null,
    });
    let state;
    const app = renderer.createApp({ setup() {
        state = useLiveFilterOptions(() => '/register', () => ({ ...draft.value }), () => initial.value);
        return () => h('div');
    } });
    app.mount({});
    t.after(() => { app.unmount(); axios.get = original; });
    return { calls, draft, initial, state, app };
}
test('selections fetch only option previews without replacing applied data', async t => {
    const { calls, draft, initial, state } = setup(t);
    draft.value.category = 'PPE';
    await delay(5);
    assert.equal(calls.length, 1);
    assert.equal(calls[0].params.category, 'PPE');
    assert.equal(calls[0].params.filter_options, 1);
    calls[0].resolve({ data: { options: { location: ['BTU'] } } });
    await nextTick();
    assert.deepEqual(state.options.value.options.location, ['BTU']);
    assert.deepEqual(initial.value.options.location, ['BTU', 'LBN']);
    assert.equal(draft.value.category, 'PPE');
});
test('rapid changes cancel requests and ignore stale responses', async t => {
    const { calls, draft, state } = setup(t);
    draft.value.category = 'PPE'; await delay(5);
    draft.value.category = 'TEC'; await delay(5);
    assert.equal(calls[0].signal.aborted, true);
    calls[1].resolve({ data: { options: { location: ['LBN'] } } }); await nextTick();
    calls[0].resolve({ data: { options: { location: ['BTU'] } } }); await nextTick();
    assert.deepEqual(state.options.value.options.location, ['LBN']);
});
test('search is debounced and failed previews retain options with retry', async t => {
    const { calls, draft, state } = setup(t);
    draft.value.search = 'r'; draft.value.search = 'respirator';
    await delay(10); assert.equal(calls.length, 0);
    await delay(310); assert.equal(calls.length, 1);
    calls[0].reject(new Error('Offline')); await nextTick();
    assert.ok(state.error.value);
    assert.deepEqual(state.options.value.options.location, ['BTU', 'LBN']);
    state.retry(); await delay(310);
    assert.equal(calls.length, 2);
    calls[1].resolve({ data: { options: { location: [] } } }); await nextTick();
    assert.equal(state.error.value, '');
    assert.deepEqual(state.options.value.options.location, []);
});
test('applied page options supersede pending previews', async t => {
    const { calls, draft, initial, state } = setup(t);
    draft.value.category = 'PPE'; await delay(5);
    initial.value = { options: { location: ['Applied'] } }; await nextTick();
    calls[0].resolve({ data: { options: { location: ['Stale'] } } }); await nextTick();
    assert.deepEqual(state.options.value.options.location, ['Applied']);
    assert.equal(calls[0].signal.aborted, true);
});
