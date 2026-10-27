<script setup>
defineProps({ layout: Object });
const display = value => value === null ? '—' : typeof value === 'number' ? value.toLocaleString(undefined, { maximumFractionDigits: 3 }) : value;
</script>
<template>
    <div class="overflow-auto bg-white">
        <table class="scaffold-template" :style="{ width: layout.widths.reduce((sum, width) => sum + width, 0) + 'pt' }">
            <colgroup><col v-for="(width, index) in layout.widths" :key="index" :style="{ width: width + 'pt' }" /></colgroup>
            <tbody><tr v-for="row in layout.rows" :key="row.number" :style="{ height: row.height + 'pt' }"><template v-for="cell in row.cells" :key="cell.col"><td v-if="cell.span" :colspan="cell.span" :style="layout.styles[cell.style]">{{ display(cell.value) }}</td></template></tr></tbody>
        </table>
    </div>
</template>
<style scoped>
.scaffold-template { border-collapse: collapse; table-layout: fixed; background: white; color: black; }
.scaffold-template td { padding: 0 2px; white-space: pre-line; overflow-wrap: break-word; line-height: 1.1; }
</style>
