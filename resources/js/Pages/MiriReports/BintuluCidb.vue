<script setup>
import MonthlyInventoryReport from '@/Components/MonthlyInventoryReport.vue';
defineProps({ filters: Object, report: Object, columns: Object });
</script>

<template>
    <MonthlyInventoryReport :filters="filters" :report="report" :columns="columns"
        title="Bintulu Yard CIDB Training Item Inventory Report" description="Monthly CIDB training inventory. Preview recorded stock and export to Excel."
        scope="Bintulu only. Items with Section 1 set to WQT TRAINING." preview-route="miri-reports.bintulu-cidb" export-route="miri-reports.cidb.export" />
</template>
