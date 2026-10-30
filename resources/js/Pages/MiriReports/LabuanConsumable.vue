<script setup>
import MonthlyInventoryReport from '@/Components/MonthlyInventoryReport.vue';
defineProps({ filters: Object, report: Object, columns: Object });
</script>

<template>
    <MonthlyInventoryReport :filters="filters" :report="report" :columns="columns"
        title="Labuan Warehouse General Store Consumable Inventory Report" description="Monthly consumable inventory. Preview recorded stock and export to Excel."
        scope="Construction TEC, Garnet & PPE register. All categories at GENERAL STORE LABUAN YARD 1 only." preview-route="miri-reports.labuan-consumable" export-route="miri-reports.labuan-consumable.export" />
</template>
