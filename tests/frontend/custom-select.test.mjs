import assert from 'node:assert/strict';
import { after, test } from 'node:test';
import { readFile, writeFile, unlink } from 'node:fs/promises';
import { parse, compileScript } from '@vue/compiler-sfc';
import { createRenderer, h, nextTick, ref } from 'vue';

// Exercise the actual compiled SFC with Vue's renderer, without a browser dependency.
const source = await readFile(new URL('../../resources/js/Components/CustomSelect.vue', import.meta.url), 'utf8');
const { descriptor } = parse(source);
const compiledUrl = new URL(`./.custom-select-${process.pid}.mjs`, import.meta.url);
await writeFile(compiledUrl, compileScript(descriptor, { id: 'select-test', inlineTemplate: true }).content);
after(() => unlink(compiledUrl));
const { default: CustomSelect } = await import(compiledUrl.href);

function node(type, text = '') {
    return {
        type, text, children: [], props: {}, parent: null,
        contains(target) { return this === target || this.children.some(child => child.contains(target)); },
        getBoundingClientRect: () => ({ top: 100, bottom: 140, left: 20, width: 250 }),
        querySelector: () => null, focus() {},
    };
}
const body = node('body');
const listeners = new Map();
globalThis.document = {
    addEventListener: (name, handler) => listeners.set(name, handler),
    removeEventListener: name => listeners.delete(name),
};
globalThis.window = { ...document, innerHeight: 800, innerWidth: 1200 };
function remove(child) {
    if (child.parent) child.parent.children.splice(child.parent.children.indexOf(child), 1);
    child.parent = null;
}
const { createApp } = createRenderer({
    createElement: tag => node(tag), createText: text => node('text', text), createComment: () => node('comment'),
    setText: (el, text) => el.text = text, setElementText: (el, text) => { el.text = text; el.children = []; },
    patchProp: (el, key, prev, value) => el.props[key] = value,
    insert(child, parent, anchor) {
        remove(child);
        const index = anchor ? parent.children.indexOf(anchor) : -1;
        parent.children.splice(index < 0 ? parent.children.length : index, 0, child);
        child.parent = parent;
    },
    remove, parentNode: el => el.parent,
    nextSibling: el => el.parent?.children[el.parent.children.indexOf(el) + 1],
    querySelector: () => body,
});
const find = (el, predicate) => predicate(el) ? el : el.children.map(child => find(child, predicate)).find(Boolean);
const label = el => el.text + el.children.map(label).join('');
const flush = async () => { await nextTick(); await nextTick(); };
function mount(t, initialOptions, props = {}) {
    const options = ref(initialOptions);
    const value = ref(props.modelValue ?? '');
    const disabled = ref(false);
    const changes = [];
    const container = node('root');
    const app = createApp({ render: () => h(CustomSelect, {
        ...props, disabled: disabled.value, modelValue: value.value,
        'onUpdate:modelValue': next => value.value = next,
        onChange: event => changes.push(event.target.value),
    }, { default: () => options.value.map(option => h('option', option.props ?? {}, option.label)) }) });
    app.mount(container);
    t.after(() => app.unmount());
    const button = find(container, el => el.props.role === 'combobox');
    return {
        options, value, disabled, changes, button,
        click: async () => { button.props.onClick(); await flush(); },
        key: async key => { button.props.onKeydown({ key, preventDefault() {}, stopPropagation() {} }); await flush(); },
        option: text => find(body, el => el.props.role === 'option' && label(el) === text),
    };
}

test('implicit text values, empty options and existing change handlers', async t => {
    const select = mount(t, [{ label: 'All', props: { value: '' } }, { label: 'DESB' }, { label: 'FTSB' }]);
    assert.equal(label(select.button), 'All');
    await select.click();
    select.option('FTSB').props.onClick();
    await flush();
    assert.equal(select.value.value, 'FTSB');
    assert.deepEqual(select.changes, ['FTSB']);
    assert.equal(label(select.button), 'FTSB');
    assert.equal(select.button.props['aria-expanded'], false);
});

test('keyboard skips disabled options and preserves numeric models', async t => {
    const select = mount(t, [
        { label: 'Choose', props: { value: '', disabled: '' } },
        { label: '12', props: { value: '12' } },
        { label: '24 unavailable', props: { value: '24', disabled: true } },
        { label: '48', props: { value: '48' } },
    ], { modelModifiers: { number: true } });
    await select.key('ArrowDown');
    await select.key('ArrowDown');
    await select.key('Enter');
    assert.equal(select.value.value, 48);
    assert.deepEqual(select.changes, ['48']);
});

test('cascading options refresh while the dropdown is open', async t => {
    const select = mount(t, [{ label: 'Before', props: { value: 'a' } }], { modelValue: 'a' });
    await select.click();
    select.options.value = [{ label: 'After', props: { value: 'a' } }, { label: 'New item', props: { value: 7 } }];
    await flush();
    assert.equal(label(select.button), 'After');
    assert.ok(select.option('New item'));
    assert.equal(select.option('Before'), undefined);
    select.option('New item').props.onClick();
    await flush();
    assert.equal(select.value.value, 7);
    assert.deepEqual(select.changes, ['7']);
});

test('type-ahead, Escape, outside click and disabled state', async t => {
    const select = mount(t, [{ label: 'Cargo' }, { label: 'Machinery' }, { label: 'Rental' }]);
    await select.key('m');
    await select.key('Enter');
    assert.equal(select.value.value, 'Machinery');
    await select.click();
    await select.key('Escape');
    assert.equal(select.button.props['aria-expanded'], false);
    await select.click();
    listeners.get('pointerdown')({ target: node('outside') });
    await flush();
    assert.equal(select.button.props['aria-expanded'], false);
    await select.click();
    select.disabled.value = true;
    await flush();
    assert.equal(select.button.props['aria-expanded'], false);
    await select.key('ArrowDown');
    assert.equal(select.button.props['aria-expanded'], false);
});
