<script setup>
import MonthlyInventoryReport from '@/Components/MonthlyInventoryReport.vue';

const props = defineProps({ filters: Object, report: Object, columns: Object });
const headerGroups = [
    ...Object.entries(props.columns).slice(0, 10).map(([key, label]) => ({ label, keys: [key] })),
    { label: '1st Load Out Status (Offshore)', keys: ['load_location', 'load_cog', 'load_qty', 'load_date', 'load_balance'] },
    { label: '1st Back Load Status (Warehouse)', keys: ['back_location', 'back_cog', 'back_qty', 'back_date', 'back_rack'] },
    { label: 'Transfer For Disposal Status', keys: ['disposal_supplier', 'disposal_cog', 'disposal_qty', 'disposal_date'] },
    { label: 'Remarks', keys: ['remarks'] },
];
</script>

<template>
    <MonthlyInventoryReport :filters="filters" :report="report" :columns="columns" :header-groups="headerGroups"
        title="Labuan Warehouse Paint Inventory Report" description="Preview paint inventory and export using the supplied Labuan warehouse Excel template."
        scope="Labuan only. Includes receipt details and the first recorded load-out and backload for the selected month."
        preview-route="miri-reports.labuan-paint" export-route="miri-reports.labuan-paint.export" />
</template>
