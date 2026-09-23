export function isGasCylinder(rental) {
    const classification = [rental.category, rental.section_1, rental.section_2].filter(Boolean).join(' ');
    const description = rental.description ?? '';
    return /\bgas\b/i.test(classification)
        || /\bgas[\s-]*cylinders?\b/i.test(description)
        || (/\bcylinders?\b/i.test(`${classification} ${description}`)
            && /\b(oxygen|acetylene|argon|nitrogen|helium|co2|carbon dioxide|lpg)\b/i.test(`${classification} ${description}`));
}

export function rentalDetailValue(key, value) {
    if (value === null || value === undefined || value === '') return 'Not recorded';
    if (key === 'active') return value ? 'Active' : 'Inactive';
    if (key.endsWith('_date') || ['created_at', 'updated_at'].includes(key)) {
        // Read the calendar date directly so browser timezone conversion cannot shift it.
        const date = String(value).match(/^(\d{4})-(\d{2})-(\d{2})(?:T|\s|$)/);
        return date ? `${date[3]}/${date[2]}/${date[1]}` : String(value);
    }
    return String(value);
}

export function rentalDetailGroups(rental) {
    return [
        { title: 'Item information', fields: [['description', 'Description'], ['category', 'Category'], ['section_1', 'Subcategory 1'], ['section_2', 'Subcategory 2'], ['serial_tag_equipment_no', 'Serial / Tag / Equipment No.'], ['unit', 'Unit'], ['supplier', 'Supplier'], ...(isGasCylinder(rental) ? [['rental_due_date', 'Rental Due Date']] : [])] },
        { title: 'Issue out', fields: [['issue_out_cog_no', 'Issue-out COG No.'], ['issue_out_cog_date', 'Issue-out COG Date'], ['onhire_certificate_no', 'On-hire Certificate No.'], ['onhire_certificate_date', 'On-hire Certificate Date']] },
        { title: 'Received backload', fields: [['received_backload_from_location', 'From Location'], ['received_backload_cog_no', 'Backload COG No.'], ['received_backload_cog_date', 'Backload COG Date']] },
        { title: 'Off hire and return to supplier', fields: [['offhire_certificate_no', 'Off-hire Certificate No.'], ['offhire_certificate_date', 'Off-hire Certificate Date'], ['return_cog_no', 'Return COG No.'], ['return_cog_date', 'Return COG Date']] },
        { title: 'Purchase documents', fields: [['mr_no', 'MR No.'], ['mr_date', 'MR Date'], ['po_or_sr_no', 'PO / SR No.'], ['po_or_sr_date', 'PO / SR Date'], ['do_no', 'DO No.'], ['do_date', 'DO Date']] },
        { title: 'Remarks and record information', fields: [['remarks', 'Remarks'], ['active', 'Record Status'], ['created_at', 'Created Date'], ['updated_at', 'Updated Date']] },
    ];
}
