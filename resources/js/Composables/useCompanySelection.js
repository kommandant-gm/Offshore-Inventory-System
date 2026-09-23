import { computed, ref, watch } from 'vue';
export function useCompanySelection(rows) {
    const selectedIds = ref([]);
    const allSelected = computed({
        get: () => rows().length > 0 && rows().every(item => selectedIds.value.includes(item.id)),
        set: value => selectedIds.value = value ? rows().map(item => item.id) : [],
    });
    watch(rows, () => selectedIds.value = []);
    return { selectedIds, allSelected };
}
