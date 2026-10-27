import assert from 'node:assert/strict';
import { after, test } from 'node:test';
import { readFile, writeFile, unlink } from 'node:fs/promises';
import { parse, compileScript } from '@vue/compiler-sfc';
import { reactive } from 'vue';

const source = await readFile(new URL('../../resources/js/Components/MiriCogIssueItems.vue', import.meta.url), 'utf8');
const { descriptor } = parse(source);
const compiledUrl = new URL(`./.cog-issue-items-${process.pid}.mjs`, import.meta.url);
const compiled = compileScript(descriptor, { id: 'issue-items-test' }).content
    .replace(/import CustomSelect from [^;]+;/, 'const CustomSelect = {};')
    .replace(/import MiriCogItemPicker from [^;]+;/, 'const MiriCogItemPicker = {};');
await writeFile(compiledUrl, compiled);
after(() => unlink(compiledUrl));
const { default: Component } = await import(compiledUrl.href);
function setup() {
    const props = reactive({ modelValue: [], registerTypes: ['Major equipment', 'Paint', 'Construction'], movementType: 'Issue out' });
    const state = Component.setup(props, { expose() {}, emit(event, value) { if (event === 'update:modelValue') props.modelValue = value; } });
    return { props, ...state };
}
const item = { id: 42, description: 'AIR COMPRESSOR', unit: 'UNIT', identifier: 'TAG-42', serial_no: 'SERIAL-42', size_model: 'AIRMAN', mr_reference: '96000A', location: 'Miri' };

test('selection copies register details and add creates an independent row; delete permits re-selection', () => {
    const state = setup();
    state.select(item);
    assert.equal(state.sourceUnit.value, 'UNIT');
    state.draft.quantity = 2;
    state.draft.remarks = 'For yard';
    state.add();
    assert.equal(state.props.modelValue.length, 1);
    assert.equal(state.props.modelValue[0].serial_no, 'SERIAL-42');
    assert.equal(state.props.modelValue[0].mr_reference, '96000A');
    assert.equal(state.props.modelValue[0].quantity, 2);
    assert.equal(state.props.modelValue[0].unit, 'UNIT');
    assert.equal(state.draft.item_id, '');
    state.draft.remarks = 'Next line';
    assert.equal(state.props.modelValue[0].remarks, 'For yard');
    state.select(item); state.add();
    assert.equal(state.props.modelValue.length, 1);
    assert.match(state.error.value, /already/);
    state.remove(0); state.add();
    assert.equal(state.props.modelValue.length, 1);
});

test('missing units and invalid quantities cannot be added; paint requires a unit choice', () => {
    const state = setup();
    state.select({ ...item, unit: '' }); state.add();
    assert.equal(state.props.modelValue.length, 0);
    state.draft.unit = 'PC'; state.draft.quantity = '1.0001'; state.add();
    assert.equal(state.props.modelValue.length, 0);
    state.draft.quantity = '0.125'; state.add();
    assert.equal(state.props.modelValue.length, 1);
    state.draft.item_type = 'Paint';
    state.select({ id: 9, description: 'Paint', batch_no: 'BATCH-A' }); state.add();
    assert.equal(state.props.modelValue.length, 1);
    state.draft.unit = 'CAN'; state.add();
    assert.equal(state.props.modelValue[1].batch_no, 'BATCH-A');
    assert.equal(state.props.modelValue[1].unit, 'CAN');
});

test('construction transfers retain destination records and enforce the item limit', () => {
    const state = setup();
    state.props.movementType = 'Transfer'; state.draft.item_type = 'Construction';
    state.select(item); state.add();
    assert.equal(state.props.modelValue.length, 0);
    state.draft.construction_destination_id = 50; state.add();
    assert.equal(state.props.modelValue[0].construction_destination_id, 50);
    state.props.modelValue = Array.from({ length: 100 }, (_, i) => ({ item_type: 'Paint', item_id: i }));
    state.select(item); state.draft.construction_destination_id = 50; state.add();
    assert.equal(state.props.modelValue.length, 100);
    assert.match(state.error.value, /100/);
});
