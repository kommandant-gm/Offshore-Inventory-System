<script setup>
import MonthlyInventoryReport from '@/Components/MonthlyInventoryReport.vue';
defineProps({ filters: Object, report: Object, columns: Object });
</script>

<template>
    <MonthlyInventoryReport :filters="filters" :report="report" :columns="columns"
        title="Bintulu Yard Paint Inventory Report" description="Monthly paint inventory. Preview recorded stock and export to Excel."
        scope="Bintulu only, including Hempel and IP paint." preview-route="miri-reports.bintulu-paint" export-route="miri-reports.paint.export" />
</template>
