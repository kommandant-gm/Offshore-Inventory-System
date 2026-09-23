<script setup>
import MonthlyInventoryReport from '@/Components/MonthlyInventoryReport.vue';
defineProps({ filters: Object, report: Object, columns: Object });
</script>

<template>
    <MonthlyInventoryReport :filters="filters" :report="report" :columns="columns"
        title="Bintulu Yard Consumable Inventory Report" description="Monthly consumable inventory. Preview recorded stock and export to Excel."
        scope="Bintulu only. Items in the Consumable category." preview-route="miri-reports.bintulu-consumable" export-route="miri-reports.consumable.export" />
</template>
