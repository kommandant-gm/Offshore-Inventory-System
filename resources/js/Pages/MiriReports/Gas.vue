<script setup>
import MonthlyInventoryReport from '@/Components/MonthlyInventoryReport.vue';
const props = defineProps({ filters: Object, report: Object, columns: Object });
const headerGroups = [
    ...Object.entries(props.columns).slice(0, 10).map(([key, label]) => ({ label, keys: [key] })),
    { label: '1st Load Out Status', keys: ['load_location', 'load_cog', 'load_qty', 'load_date', 'load_unit'] },
    { label: '1st Back Load Status', keys: ['back_location', 'back_cog', 'back_qty', 'back_date', 'back_unit'] },
    { label: '1st Return To Supplier', keys: ['return_supplier', 'return_cog', 'return_qty', 'return_date'] },
    { label: 'Remarks', keys: ['remarks'] },
];
</script>

<template>
    <MonthlyInventoryReport :filters="filters" :report="report" :columns="columns" :header-groups="headerGroups"
        title="Gas Inventory Report" description="Gas register details and monthly COG movements, using the warehouse paint report layout adapted for gas."
        scope="All locations. BIG and LGM gas records. Shows the first signed/confirmed load-out, backload and supplier return for the selected month, with dated register references where available."
        preview-route="miri-reports.gas" export-route="miri-reports.gas.export" />
</template>
