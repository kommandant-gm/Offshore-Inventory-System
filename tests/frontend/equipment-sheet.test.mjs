import assert from 'node:assert/strict';
import { test } from 'node:test';
import { cellValue, columnLetter, parseClipboard, pasteCells, sheetChanges, sheetRows } from '../../resources/js/Support/equipmentSheet.js';

const columns = [{ key: 'tag_no', type: 'text' }, { key: 'description', type: 'text' }, { key: 'issue_out_cog_date', type: 'date' }];

test('sheet keeps identifiers, blank values and dates and sends only edited cells', () => {
    const original = sheetRows([{ id: 1, tag_no: '00123', description: null, issue_out_cog_date: '2026-08-10T00:00:00.000000Z' }], columns);
    assert.equal(original[0].tag_no, '00123');
    assert.equal(original[0].description, '');
    assert.equal(original[0].issue_out_cog_date, '2026-08-10');
    assert.deepEqual(sheetChanges(original, original, columns), []);
    const edited = [{ ...original[0], description: 'Compressor' }];
    assert.deepEqual(sheetChanges(edited, original, columns), [{ id: 1, changes: { description: 'Compressor' }, original: { description: '' } }]);
    assert.equal(cellValue(0, 'number'), '0');
    assert.equal(cellValue('10/8/2026', 'date'), '2026-08-10');
    assert.equal(cellValue('invalid date', 'date'), 'invalid date');
});

test('Excel paste handles quoted tabs, newlines, escaped quotes and empty cells', () => {
    assert.deepEqual(parseClipboard('00123\t"Air\tcompressor"\t\r\n00456\t"two\nlines and ""quotes"""\t2026-08-10\r\n'), [
        ['00123', 'Air\tcompressor', ''], ['00456', 'two\nlines and "quotes"', '2026-08-10'],
    ]);
    assert.deepEqual(parseClipboard('\t\n'), [['', '']]);
    assert.throws(() => parseClipboard('"unfinished\tcell'), /unfinished quote/);
});

test('rectangular paste changes the intended cells and rejects overflow without partial writes', () => {
    const rows = sheetRows([{ id: 1 }, { id: 2 }], columns);
    const pasted = pasteCells(rows, columns, 0, 1, 'Pump\t10/08/2026\nCrane\t11/08/2026\n');
    assert.deepEqual(pasted.map(row => [row.description, row.issue_out_cog_date]), [['Pump', '2026-08-10'], ['Crane', '2026-08-11']]);
    assert.equal(rows[0].description, '');
    assert.throws(() => pasteCells(rows, columns, 1, 1, 'A\nB'), /beyond this page/);
    assert.throws(() => pasteCells(rows, columns, 0, 2, 'A\tB'), /beyond this page/);
    assert.equal(rows[0].issue_out_cog_date, '');
    assert.equal(columnLetter(0), 'A');
    assert.equal(columnLetter(25), 'Z');
    assert.equal(columnLetter(26), 'AA');
});
