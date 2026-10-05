export function cellValue(value, type = 'text') {
    if (value == null) return '';
    const text = String(value);
    if (type === 'date') {
        const iso = text.match(/^(\d{4}-\d{2}-\d{2})(?:T|\s|$)/);
        if (iso) return iso[1];
        const local = text.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
        if (local) return `${local[3]}-${local[2].padStart(2, '0')}-${local[1].padStart(2, '0')}`;
    }
    return text;
}

export function sheetRows(records, columns) {
    return records.map(record => ({ id: record.id, ...Object.fromEntries(columns.map(column => [column.key, cellValue(record[column.key], column.type)])) }));
}

export function sheetChanges(rows, originals, columns) {
    return rows.flatMap((row, index) => {
        const keys = columns.filter(column => !column.readonly).map(column => column.key).filter(key => row[key] !== originals[index][key]);
        return keys.length ? [{ id: row.id, changes: Object.fromEntries(keys.map(key => [key, row[key]])), original: Object.fromEntries(keys.map(key => [key, originals[index][key]])) }] : [];
    });
}

// Excel quotes cells containing tabs, line breaks or quotes.
export function parseClipboard(text) {
    const rows = [];
    let row = [], cell = '', quoted = false;
    text = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n');
    for (let i = 0; i < text.length; i++) {
        const char = text[i];
        if (char === '"') {
            if (quoted && text[i + 1] === '"') { cell += '"'; i++; }
            else if (quoted || cell === '') quoted = !quoted;
            else cell += char;
        } else if (!quoted && (char === '\t' || char === '\n')) {
            row.push(cell); cell = '';
            if (char === '\n') { rows.push(row); row = []; }
        } else cell += char;
    }
    if (quoted) throw new Error('The pasted cells contain an unfinished quote. Copy the cells again.');
    if (cell !== '' || row.length || !rows.length) { row.push(cell); rows.push(row); }
    return rows;
}

export function pasteCells(rows, columns, rowIndex, columnIndex, text) {
    const cells = parseClipboard(text);
    if (rowIndex + cells.length > rows.length || cells.some(row => columnIndex + row.length > columns.length)) {
        throw new Error('The pasted cells extend beyond this page. Paste a smaller selection.');
    }
    const next = rows.map(row => ({ ...row }));
    cells.forEach((row, y) => row.forEach((value, x) => {
        const column = columns[columnIndex + x];
        if (column.readonly) return;
        next[rowIndex + y][column.key] = cellValue(value, column.type);
    }));
    return next;
}

export function columnLetter(index) {
    let label = '';
    for (let value = index + 1; value; value = Math.floor((value - 1) / 26)) label = String.fromCharCode(65 + (value - 1) % 26) + label;
    return label;
}
