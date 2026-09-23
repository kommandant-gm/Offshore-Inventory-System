import assert from 'node:assert/strict';
import { test } from 'node:test';
import { isGasCylinder, rentalDetailGroups, rentalDetailValue } from '../../resources/js/Support/rentalDetails.js';

test('details show due dates for gas cylinders but not ordinary rental equipment', () => {
    const fields = rental => rentalDetailGroups(rental).flatMap(group => group.fields.map(([key]) => key));
    for (const rental of [
        { section_1: 'GAS CYLINDER', description: 'Oxygen' },
        { section_2: 'Gas', description: 'Argon' },
        { description: 'Gas-cylinder 50L' },
        { description: 'OXYGEN CYLINDER' },
    ]) {
        assert.equal(isGasCylinder(rental), true);
        assert.ok(fields(rental).includes('rental_due_date'));
    }
    for (const rental of [{ description: 'AIR DRYER C/W 4 LEGGED SLING', rental_due_date: '2026-08-01' }, { description: 'Hydraulic cylinder' }, {}]) {
        assert.equal(isGasCylinder(rental), false);
        assert.equal(fields(rental).includes('rental_due_date'), false);
    }
    assert.equal(fields({ id: 15, branch_id: 1 }).includes('branch_id'), false);
});

test('all displayed dates omit timestamps without timezone date shifts', () => {
    for (const key of ['issue_out_cog_date', 'received_backload_cog_date', 'return_cog_date', 'offhire_certificate_date', 'onhire_certificate_date', 'mr_date', 'po_or_sr_date', 'do_date', 'rental_due_date', 'created_at', 'updated_at']) {
        assert.equal(rentalDetailValue(key, '2026-08-01T00:00:00.000000Z'), '01/08/2026');
        assert.equal(rentalDetailValue(key, '2026-08-01'), '01/08/2026');
        assert.equal(rentalDetailValue(key, null), 'Not recorded');
    }
    assert.equal(rentalDetailValue('active', false), 'Inactive');
    assert.equal(rentalDetailValue('remarks', 'Line 1\nLine 2'), 'Line 1\nLine 2');
    assert.equal(rentalDetailValue('serial_tag_equipment_no', '0'), '0');
});
