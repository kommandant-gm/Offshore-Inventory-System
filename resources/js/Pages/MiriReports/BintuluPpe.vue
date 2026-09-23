<script setup>
import MonthlyInventoryReport from '@/Components/MonthlyInventoryReport.vue';
defineProps({ filters: Object, report: Object, columns: Object });
</script>

<template>
    <MonthlyInventoryReport :filters="filters" :report="report" :columns="columns"
        title="Bintulu Yard PPE Inventory Report" description="Monthly PPE inventory. Preview recorded stock and export to Excel."
        scope="Bintulu only. Items in the PPE category." preview-route="miri-reports.bintulu-ppe" export-route="miri-reports.ppe.export" />
</template>
