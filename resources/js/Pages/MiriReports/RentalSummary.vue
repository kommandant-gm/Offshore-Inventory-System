<script setup>
import MonthlyInventoryReport from '@/Components/MonthlyInventoryReport.vue';
defineProps({ filters: Object, report: Object, columns: Object, options: Object });
</script>

<template>
    <MonthlyInventoryReport :filters="filters" :report="report" :columns="columns" :options="options"
        title="Equipment Rental List Summary" description="Preview rental records and export form DE-F-187A to Excel."
        scope="Miri rental equipment, excluding gas cylinders. Filter by month, project and warehouse/location."
        preview-route="miri-reports.rental" export-route="miri-reports.rental.export" />
</template>
