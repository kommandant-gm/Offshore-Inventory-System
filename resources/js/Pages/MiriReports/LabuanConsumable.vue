<script setup>
import MonthlyInventoryReport from '@/Components/MonthlyInventoryReport.vue';
defineProps({ filters: Object, report: Object, columns: Object });
</script>

<template>
    <MonthlyInventoryReport :filters="filters" :report="report" :columns="columns"
        title="Labuan Warehouse General Store Consumable Inventory Report" description="Monthly consumable inventory. Preview recorded stock and export to Excel."
        scope="Labuan only. Consumable and PPE categories." preview-route="miri-reports.labuan-consumable" export-route="miri-reports.labuan-consumable.export" />
</template>
