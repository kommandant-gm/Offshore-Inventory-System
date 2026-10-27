<script setup>
defineProps({ layout: Object });
const display = value => typeof value === 'number' ? value.toLocaleString(undefined, { maximumFractionDigits: 3 }) : value;
</script>
<template>
    <div class="overflow-auto border bg-white p-3">
        <div class="relative" :style="{ width: layout.widths.reduce((a, b) => a + b, 0) + 'pt' }">
            <img v-if="layout.logo" :src="layout.logo.src" alt="Company logo" class="absolute" :style="{ left: layout.logo.left + 'pt', top: layout.logo.top + 'pt', width: layout.logo.width + 'pt', height: layout.logo.height + 'pt' }" />
            <table class="workbook-preview"><colgroup><col v-for="(width, index) in layout.widths.filter(width => width > 0)" :key="index" :style="{ width: width + 'pt' }" /></colgroup>
                <tbody><tr v-for="row in layout.rows" :key="row.number" :style="{ height: row.height + 'pt' }"><td v-for="cell in row.cells" :key="cell.column" :colspan="cell.colspan" :rowspan="cell.rowspan" :style="layout.styles[cell.style]">{{ display(cell.value) }}</td></tr></tbody>
            </table>
        </div>
    </div>
</template>
<style scoped>
.workbook-preview { table-layout: fixed; border-collapse: collapse; width: 100%; }
.workbook-preview td { padding: 0 2px; white-space: pre-line; overflow-wrap: break-word; line-height: 1.15; }
</style>
