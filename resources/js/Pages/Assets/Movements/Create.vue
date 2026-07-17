<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CustomSelect from '@/Components/CustomSelect.vue';
import InputError from '@/Components/InputError.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps({
    items: Array,
    locations: Array,
    transactionTypes: Array,
    draftCogNo: String,
});

const page = usePage();
const authUserName = computed(() => page.props.auth?.user?.name ?? '');

const form = useForm({
    transaction_date: new Date().toISOString().slice(0, 10),
    item_id: props.items[0]?.value ?? '',
    transaction_type: props.transactionTypes[0]?.value ?? '',
    location_id: '',
    source_location_id: '',
    destination_location_id: '',
    quantity: '',
    unit_cost: props.items[0]?.unit_cost ?? '',
    po_no: '',
    do_no: '',
    cog_issued_out: '',
    cog_received: '',
    remarks: '',
    generate_cog: false,
    cog: {
        document_date: new Date().toISOString().slice(0, 10),
        consignee: '',
        shipper: 'Dayang Enterprise Sdn. Bhd.',
        destination: '',
        vessel: '',
        department: '',
        receiver_name: '',
        receiver_email: '',
        cc_emails: '',
        receiver_designation: '',
        issued_by_name: authUserName.value,
        issued_by_designation: '',
        checked_by_name: '',
        checked_by_designation: '',
        measurement_cu_metre: '',
        gross_weight_kg: '',
        po_no: '',
        ex_location: '',
        serial_no: '',
        item_remarks: '',
        remarks: '',
    },
});

const selectedItem = computed(() => props.items.find((item) => item.value === form.item_id) ?? null);
const selectedType = computed(() => props.transactionTypes.find((type) => type.value === form.transaction_type) ?? null);
const sourceLocationLabel = computed(() => props.locations.find((location) => location.value === form.source_location_id)?.label ?? 'Source pending');
const destinationLocationLabel = computed(() => props.locations.find((location) => location.value === form.destination_location_id)?.label ?? 'Destination pending');
const showCogSection = computed(() => form.transaction_type === 'issue');
const movementValue = computed(() => {
    const quantity = Number(form.quantity || 0);
    const unitCost = Number(form.unit_cost || 0);

    return (quantity * unitCost).toFixed(2);
});

watch(() => form.item_id, (value) => {
    const item = props.items.find((entry) => entry.value === value);
    if (item && !form.isDirty) {
        form.unit_cost = item.unit_cost ?? '';
    }
});

watch(() => form.transaction_type, (type) => {
    if (type === 'receive' && !form.destination_location_id && form.location_id) {
        form.destination_location_id = form.location_id;
    }

    if (type !== 'issue') {
        form.generate_cog = false;
    }
});

watch(() => form.transaction_date, (value, previousValue) => {
    if (!form.cog.document_date || form.cog.document_date === previousValue) {
        form.cog.document_date = value;
    }
});

const trackingState = computed(() => {
    switch (form.transaction_type) {
        case 'receive':
            return { label: 'Receiving', progress: 100, tone: 'text-emerald-700' };
        case 'issue':
            return { label: 'Issued Out', progress: 72, tone: 'text-amber-700' };
        case 'interloc_transfer':
            return { label: 'In Transfer', progress: 58, tone: 'text-sky-700' };
        case 'material_return':
            return { label: 'Returned', progress: 86, tone: 'text-emerald-700' };
        case 'physical_adjustment':
            return { label: 'Adjusted', progress: 64, tone: 'text-orange-700' };
        case 'price_adjustment':
            return { label: 'Repriced', progress: 60, tone: 'text-fuchsia-700' };
        default:
            return { label: 'Movement Draft', progress: 38, tone: 'text-slate-700' };
    }
});

const progressWidth = computed(() => `${trackingState.value.progress}%`);

const submit = () => {
    form.post(route('asset-movements.store'));
};
</script>

<template>
    <Head title="New Stock Item Movement" />

    <AuthenticatedLayout>
        <PageHeader title="New Stock Item Movement" description="Create a stock item movement with a live route preview and structured references.">
            <div class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row">
                <Link class="btn w-full border-[#d8e7d4] bg-white text-[#2f6f2d] hover:bg-[#eef8ea] sm:w-auto" :href="route('asset-movements.index')">
                    Back To Tracking
                </Link>
                <Link class="btn w-full border-none bg-[linear-gradient(135deg,#6fbb68_0%,#4f9f4a_100%)] text-white shadow-[0_16px_36px_rgba(79,159,74,0.24)] hover:opacity-95 sm:w-auto" :href="route('assets.index')">
                    Stock Item List
                </Link>
            </div>
        </PageHeader>

        <div class="grid gap-6 xl:grid-cols-[0.92fr,1.08fr]">
            <section class="rounded-[2rem] border border-[#d8e7d4] bg-white p-5 shadow-[0_18px_45px_rgba(79,159,74,0.10)]">
                <div class="rounded-[1.6rem] border border-[#d8e7d4] bg-[linear-gradient(180deg,#ffffff_0%,#f7fcf5_100%)] p-4 text-[#234222] shadow-[inset_0_1px_0_rgba(255,255,255,0.5)]">
                    <div class="inline-flex items-center gap-2 rounded-full border border-[#cfe6c8] bg-[#eef8ea] px-4 py-2 text-[11px] font-semibold uppercase tracking-[0.28em] text-[#3c8a39]">
                        Live Preview
                    </div>

                    <div class="mt-5 overflow-hidden rounded-[1.8rem] border border-[#d8e7d4] bg-[radial-gradient(circle_at_top_left,rgba(111,187,104,0.14),transparent_24%),linear-gradient(180deg,#ffffff_0%,#f6fbf3_100%)] p-5 text-[#234222] shadow-[0_24px_60px_rgba(79,159,74,0.12)]">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold tracking-[0.08em] text-[#2f6f2d]">
                                    {{ selectedItem?.label?.split(' - ')[0] ?? 'ITEM-CODE' }}
                                </p>
                                <p class="mt-1 text-xs text-[#6f8a6b]">
                                    {{ selectedItem?.label?.split(' - ').slice(1).join(' - ') || 'Select a stock item to preview this movement.' }}
                                </p>
                            </div>
                            <div class="rounded-full border border-[#cfe6c8] bg-[#eef8ea] px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em]" :class="trackingState.tone">
                                {{ trackingState.label }}
                            </div>
                        </div>

                            <div class="mt-6 rounded-[1.3rem] border border-[#e1efdc] bg-white px-4 py-4">
                                <div class="flex flex-col gap-2 text-sm text-[#4f6b4b] sm:flex-row sm:items-center sm:justify-between">
                                    <span>{{ sourceLocationLabel }}</span>
                                    <span>{{ destinationLocationLabel }}</span>
                                </div>

                            <div class="relative mt-4 h-10">
                                <div class="absolute left-0 right-0 top-1/2 h-[2px] -translate-y-1/2 bg-[#d8e7d4]" />
                                <div class="absolute left-0 top-1/2 h-[2px] -translate-y-1/2 bg-[#4f9f4a]" :style="{ width: progressWidth }" />
                                <div class="absolute left-0 top-1/2 h-3 w-3 -translate-y-1/2 rounded-full border-2 border-[#4f9f4a] bg-white" />
                                <div class="absolute top-1/2 h-3 w-3 -translate-y-1/2 rounded-full border-2 border-[#4f9f4a] bg-white" :style="{ left: `calc(${progressWidth} - 0.375rem)` }" />
                                <div class="absolute right-0 top-1/2 h-3 w-3 -translate-y-1/2 rounded-full border-2 border-[#d8e7d4] bg-white" />
                            </div>

                            <div class="mt-3 flex items-center justify-between text-[11px] uppercase tracking-[0.14em] text-[#7f9a7a]">
                                <span>Source</span>
                                <span>{{ selectedType?.label ?? 'Movement Type' }}</span>
                                <span>{{ trackingState.label }}</span>
                            </div>
                        </div>

                        <div class="mt-5 grid gap-3 sm:grid-cols-2">
                            <div class="rounded-2xl border border-[#e1efdc] bg-white px-4 py-3">
                                <p class="text-[11px] uppercase tracking-[0.2em] text-[#7f9a7a]">Quantity</p>
                                <p class="mt-2 text-lg font-semibold text-[#234222]">{{ form.quantity || '0' }}</p>
                            </div>
                            <div class="rounded-2xl border border-[#e1efdc] bg-white px-4 py-3">
                                <p class="text-[11px] uppercase tracking-[0.2em] text-[#7f9a7a]">Movement Value</p>
                                <p class="mt-2 text-lg font-semibold text-[#234222]">RM {{ movementValue }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 rounded-[1.4rem] border border-[#d8e7d4] bg-[#fbfefa] px-4 py-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-[#234222]">Preview Summary</p>
                                <p class="mt-1 text-xs text-[#6f8a6b]">This route preview updates as you change the movement type, item, quantity, and locations.</p>
                            </div>
                            <div class="inline-flex w-fit rounded-full bg-white px-4 py-2 text-[11px] font-semibold uppercase tracking-[0.22em] text-[#3c8a39] ring-1 ring-[#cfe6c8]">
                                {{ showCogSection && form.generate_cog ? draftCogNo : 'Draft' }}
                            </div>
                        </div>
                        <p v-if="showCogSection && form.generate_cog" class="mt-3 text-xs text-[#4f9f4a]">
                            This issue movement will open a generated COG document after save and send the receiver approval email if an email address is provided.
                        </p>
                    </div>
                </div>
            </section>

            <form class="rounded-[2rem] border border-[#d8e7d4] bg-white p-5 shadow-[0_18px_45px_rgba(79,159,74,0.10)]" @submit.prevent="submit">
                <div class="relative overflow-hidden rounded-[1.75rem] border border-[#d8e7d4] bg-[radial-gradient(circle_at_top_left,_rgba(111,187,104,0.16),_transparent_30%),linear-gradient(180deg,_#ffffff_0%,_#f7fcf5_52%,_#eef8ea_100%)] p-5 shadow-[0_20px_60px_rgba(79,159,74,0.12)]">
                    <div class="pointer-events-none absolute inset-0 bg-[linear-gradient(135deg,rgba(255,255,255,0.55),transparent_32%,transparent_70%,rgba(111,187,104,0.08))]" />

                    <div class="relative">
                        <div class="mb-3 inline-flex items-center gap-2 rounded-full border border-[#cfe6c8] bg-white/80 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.22em] text-[#4f6b4b]">
                            <span class="h-2 w-2 rounded-full bg-[#4f9f4a] shadow-[0_0_10px_rgba(79,159,74,0.45)]" />
                            Movement Control
                        </div>
                        <h2 class="text-2xl font-semibold tracking-tight text-[#234222]">Create movement record</h2>
                        <p class="mt-2 text-sm text-[#5f7b5e]">
                            Capture one stock item movement with quantity, value, route, and reference details in a single control panel.
                        </p>
                    </div>
                </div>

                <div class="mt-5 grid gap-5">
                    <section class="rounded-[1.6rem] border border-[#d8e7d4] bg-[#fbfefa] p-5">
                        <div class="mb-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-[#7f9a7a]">Movement Setup</p>
                            <h3 class="mt-2 text-lg font-semibold text-[#234222]">Core movement details</h3>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="group rounded-[1.35rem] border border-[#d8e7d4] bg-white p-4 transition hover:border-[#a8d09e]">
                                <label class="mb-3 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Transaction Date</label>
                                <TextInput v-model="form.transaction_date" type="date" class="w-full border-[#cfe6c8] bg-white text-[#234222]" />
                                <InputError class="mt-2" :message="form.errors.transaction_date" />
                            </div>

                            <div class="group rounded-[1.35rem] border border-[#d8e7d4] bg-white p-4 transition hover:border-[#a8d09e]">
                                <label class="mb-3 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Movement Type</label>
                                <CustomSelect v-model="form.transaction_type" class="select w-full border-[#cfe6c8] bg-white text-[#234222]">
                                    <option v-for="type in transactionTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                                </CustomSelect>
                                <InputError class="mt-2" :message="form.errors.transaction_type" />
                            </div>
                        </div>

                        <div class="mt-4 group rounded-[1.35rem] border border-[#d8e7d4] bg-white p-4 transition hover:border-[#a8d09e]">
                            <label class="mb-3 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Stock Item</label>
                            <CustomSelect v-model="form.item_id" class="select w-full border-[#cfe6c8] bg-white text-[#234222]">
                                <option v-for="item in items" :key="item.value" :value="item.value">{{ item.label }}</option>
                            </CustomSelect>
                            <InputError class="mt-2" :message="form.errors.item_id" />
                        </div>

                        <div class="mt-4 grid gap-4 md:grid-cols-3">
                            <div class="group rounded-[1.35rem] border border-[#d8e7d4] bg-white p-4 transition hover:border-[#a8d09e]">
                                <label class="mb-3 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Quantity</label>
                                <TextInput v-model="form.quantity" type="number" step="0.01" class="w-full border-[#cfe6c8] bg-white text-[#234222]" placeholder="0" />
                                <InputError class="mt-2" :message="form.errors.quantity" />
                            </div>
                            <div class="group rounded-[1.35rem] border border-[#d8e7d4] bg-white p-4 transition hover:border-[#a8d09e]">
                                <label class="mb-3 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Unit Cost</label>
                                <TextInput v-model="form.unit_cost" type="number" step="0.01" class="w-full border-[#cfe6c8] bg-white text-[#234222]" placeholder="0.00" />
                                <InputError class="mt-2" :message="form.errors.unit_cost" />
                            </div>
                            <div class="group rounded-[1.35rem] border border-[#d8e7d4] bg-white p-4 transition hover:border-[#a8d09e]">
                                <label class="mb-3 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Primary Location</label>
                                <CustomSelect v-model="form.location_id" class="select w-full border-[#cfe6c8] bg-white text-[#234222]">
                                    <option value="">Primary location</option>
                                    <option v-for="location in locations" :key="location.value" :value="location.value">{{ location.label }}</option>
                                </CustomSelect>
                                <InputError class="mt-2" :message="form.errors.location_id" />
                            </div>
                        </div>
                    </section>

                    <section class="rounded-[1.6rem] border border-[#d8e7d4] bg-[#fbfefa] p-5">
                        <div class="mb-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-[#7f9a7a]">Route</p>
                            <h3 class="mt-2 text-lg font-semibold text-[#234222]">Movement path</h3>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="group rounded-[1.35rem] border border-[#d8e7d4] bg-white p-4 transition hover:border-[#a8d09e]">
                                <label class="mb-3 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">From Location</label>
                                <CustomSelect v-model="form.source_location_id" class="select w-full border-[#cfe6c8] bg-white text-[#234222]">
                                    <option value="">None</option>
                                    <option v-for="location in locations" :key="location.value" :value="location.value">{{ location.label }}</option>
                                </CustomSelect>
                                <InputError class="mt-2" :message="form.errors.source_location_id" />
                            </div>
                            <div class="group rounded-[1.35rem] border border-[#d8e7d4] bg-white p-4 transition hover:border-[#a8d09e]">
                                <label class="mb-3 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">To Location</label>
                                <CustomSelect v-model="form.destination_location_id" class="select w-full border-[#cfe6c8] bg-white text-[#234222]">
                                    <option value="">None</option>
                                    <option v-for="location in locations" :key="location.value" :value="location.value">{{ location.label }}</option>
                                </CustomSelect>
                                <InputError class="mt-2" :message="form.errors.destination_location_id" />
                            </div>
                        </div>
                    </section>

                    <section class="rounded-[1.6rem] border border-[#d8e7d4] bg-[#fbfefa] p-5">
                        <div class="mb-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-[#7f9a7a]">References</p>
                            <h3 class="mt-2 text-lg font-semibold text-[#234222]">Operational references</h3>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="group rounded-[1.35rem] border border-[#d8e7d4] bg-white p-4 transition hover:border-[#a8d09e]">
                                <label class="mb-3 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Purchase Order No.</label>
                                <TextInput v-model="form.po_no" class="w-full border-[#cfe6c8] bg-white text-[#234222]" placeholder="PO no." />
                            </div>
                            <div class="group rounded-[1.35rem] border border-[#d8e7d4] bg-white p-4 transition hover:border-[#a8d09e]">
                                <label class="mb-3 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Delivery Order No.</label>
                                <TextInput v-model="form.do_no" class="w-full border-[#cfe6c8] bg-white text-[#234222]" placeholder="DO no." />
                            </div>
                            <div class="group rounded-[1.35rem] border border-[#d8e7d4] bg-white p-4 transition hover:border-[#a8d09e]">
                                <label class="mb-3 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">COG Issued Out</label>
                                <TextInput v-model="form.cog_issued_out" class="w-full border-[#cfe6c8] bg-white text-[#234222]" placeholder="COG issued out" />
                            </div>
                            <div class="group rounded-[1.35rem] border border-[#d8e7d4] bg-white p-4 transition hover:border-[#a8d09e]">
                                <label class="mb-3 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">COG Received</label>
                                <TextInput v-model="form.cog_received" class="w-full border-[#cfe6c8] bg-white text-[#234222]" placeholder="COG received" />
                            </div>
                        </div>
                    </section>

                    <section
                        v-if="showCogSection"
                        class="rounded-[1.6rem] border border-[#cfe6c8] bg-[linear-gradient(180deg,#fbfefa_0%,#eef8ea_100%)] p-5"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-[#4f9f4a]">COG Workflow</p>
                                <h3 class="mt-2 text-lg font-semibold text-[#234222]">Generate consignment note from this issue</h3>
                                <p class="mt-2 max-w-2xl text-sm text-[#5f7b5e]">
                                    Use this when the issued item needs a formal COG document and email acknowledgement from the receiver.
                                </p>
                            </div>
                            <label class="inline-flex cursor-pointer items-center gap-3 rounded-full border border-[#cfe6c8] bg-white px-4 py-3 text-sm font-medium text-[#234222]">
                                <input v-model="form.generate_cog" type="checkbox" class="h-4 w-4 rounded border-[#b8d7b1] bg-white text-[#4f9f4a] focus:ring-[#4f9f4a]/30" />
                                Generate COG
                            </label>
                        </div>

                        <InputError class="mt-3" :message="form.errors.generate_cog" />

                        <div v-if="form.generate_cog" class="mt-5 space-y-5">
                            <div class="rounded-[1.35rem] border border-[#d8e7d4] bg-white p-4">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">COG Draft</p>
                                        <p class="mt-2 text-lg font-semibold text-[#234222]">{{ draftCogNo }}</p>
                                    </div>
                                    <div class="rounded-full border border-[#cfe6c8] bg-[#eef8ea] px-4 py-2 text-[11px] font-semibold uppercase tracking-[0.22em] text-[#3c8a39]">
                                        Pending Approval Email
                                    </div>
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                <div class="rounded-[1.35rem] border border-[#d8e7d4] bg-white p-4">
                                    <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Document Date</label>
                                    <TextInput v-model="form.cog.document_date" type="date" class="w-full border-[#cfe6c8] bg-white text-[#234222]" />
                                    <InputError class="mt-2" :message="form.errors['cog.document_date']" />
                                </div>
                                <div class="rounded-[1.35rem] border border-[#d8e7d4] bg-white p-4">
                                    <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Consignee</label>
                                    <TextInput v-model="form.cog.consignee" class="w-full border-[#cfe6c8] bg-white text-[#234222]" placeholder="Receiving party" />
                                    <InputError class="mt-2" :message="form.errors['cog.consignee']" />
                                </div>
                                <div class="rounded-[1.35rem] border border-[#d8e7d4] bg-white p-4">
                                    <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Destination</label>
                                    <TextInput v-model="form.cog.destination" class="w-full border-[#cfe6c8] bg-white text-[#234222]" placeholder="Destination" />
                                    <InputError class="mt-2" :message="form.errors['cog.destination']" />
                                </div>
                                <div class="rounded-[1.35rem] border border-[#d8e7d4] bg-white p-4">
                                    <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Vessel</label>
                                    <TextInput v-model="form.cog.vessel" class="w-full border-[#cfe6c8] bg-white text-[#234222]" placeholder="Vessel / project" />
                                    <InputError class="mt-2" :message="form.errors['cog.vessel']" />
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                <div class="rounded-[1.35rem] border border-[#d8e7d4] bg-white p-4">
                                    <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Department</label>
                                    <TextInput v-model="form.cog.department" class="w-full border-[#cfe6c8] bg-white text-[#234222]" placeholder="Department" />
                                    <InputError class="mt-2" :message="form.errors['cog.department']" />
                                </div>
                                <div class="rounded-[1.35rem] border border-[#d8e7d4] bg-white p-4">
                                    <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Receiver Name</label>
                                    <TextInput v-model="form.cog.receiver_name" class="w-full border-[#cfe6c8] bg-white text-[#234222]" placeholder="Receiver name" />
                                    <InputError class="mt-2" :message="form.errors['cog.receiver_name']" />
                                </div>
                                <div class="rounded-[1.35rem] border border-[#d8e7d4] bg-white p-4">
                                    <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Receiver Email</label>
                                    <TextInput v-model="form.cog.receiver_email" type="email" class="w-full border-[#cfe6c8] bg-white text-[#234222]" placeholder="receiver@email.com" />
                                    <InputError class="mt-2" :message="form.errors['cog.receiver_email']" />
                                </div>
                                <div class="rounded-[1.35rem] border border-[#d8e7d4] bg-white p-4 md:col-span-2 xl:col-span-1">
                                    <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">CC Acknowledgement Emails</label>
                                    <TextInput v-model="form.cog.cc_emails" class="w-full border-[#cfe6c8] bg-white text-[#234222]" placeholder="manager@company.com, ops@company.com" />
                                    <InputError class="mt-2" :message="form.errors['cog.cc_emails']" />
                                </div>
                                <div class="rounded-[1.35rem] border border-[#d8e7d4] bg-white p-4">
                                    <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Receiver Designation</label>
                                    <TextInput v-model="form.cog.receiver_designation" class="w-full border-[#cfe6c8] bg-white text-[#234222]" placeholder="Designation" />
                                    <InputError class="mt-2" :message="form.errors['cog.receiver_designation']" />
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                <div class="rounded-[1.35rem] border border-[#d8e7d4] bg-white p-4">
                                    <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Issued By</label>
                                    <TextInput v-model="form.cog.issued_by_name" class="w-full border-[#cfe6c8] bg-white text-[#234222]" placeholder="Issued by name" />
                                    <InputError class="mt-2" :message="form.errors['cog.issued_by_name']" />
                                </div>
                                <div class="rounded-[1.35rem] border border-[#d8e7d4] bg-white p-4">
                                    <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Issued By Designation</label>
                                    <TextInput v-model="form.cog.issued_by_designation" class="w-full border-[#cfe6c8] bg-white text-[#234222]" placeholder="Issued by designation" />
                                    <InputError class="mt-2" :message="form.errors['cog.issued_by_designation']" />
                                </div>
                                <div class="rounded-[1.35rem] border border-[#d8e7d4] bg-white p-4">
                                    <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Checked By</label>
                                    <TextInput v-model="form.cog.checked_by_name" class="w-full border-[#cfe6c8] bg-white text-[#234222]" placeholder="Checked by name" />
                                    <InputError class="mt-2" :message="form.errors['cog.checked_by_name']" />
                                </div>
                                <div class="rounded-[1.35rem] border border-[#d8e7d4] bg-white p-4">
                                    <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Checked By Designation</label>
                                    <TextInput v-model="form.cog.checked_by_designation" class="w-full border-[#cfe6c8] bg-white text-[#234222]" placeholder="Checked by designation" />
                                    <InputError class="mt-2" :message="form.errors['cog.checked_by_designation']" />
                                </div>
                            </div>

                            <div class="grid gap-4 xl:grid-cols-3">
                                <div class="rounded-[1.35rem] border border-[#d8e7d4] bg-white p-4">
                                    <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Line Item</label>
                                    <div class="rounded-2xl border border-[#d8e7d4] bg-[#fbfefa] px-4 py-3 text-sm text-[#4f6b4b]">
                                        <p class="font-semibold text-[#234222]">{{ selectedItem?.item_code ?? 'Pending item' }}</p>
                                        <p class="mt-1 text-xs text-[#6f8a6b]">{{ selectedItem?.description ?? 'Select an issue item to build the COG line.' }}</p>
                                    </div>
                                </div>
                                <div class="rounded-[1.35rem] border border-[#d8e7d4] bg-white p-4">
                                    <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Serial No.</label>
                                    <TextInput v-model="form.cog.serial_no" class="w-full border-[#cfe6c8] bg-white text-[#234222]" placeholder="Serial no." />
                                    <InputError class="mt-2" :message="form.errors['cog.serial_no']" />
                                </div>
                                <div class="rounded-[1.35rem] border border-[#d8e7d4] bg-white p-4">
                                    <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Line Remarks</label>
                                    <TextInput v-model="form.cog.item_remarks" class="w-full border-[#cfe6c8] bg-white text-[#234222]" placeholder="Line item remarks" />
                                    <InputError class="mt-2" :message="form.errors['cog.item_remarks']" />
                                </div>
                            </div>

                            <div class="grid gap-4 xl:grid-cols-4">
                                <div class="rounded-[1.35rem] border border-[#d8e7d4] bg-white p-4">
                                    <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Measurement (Cu. Metre)</label>
                                    <TextInput v-model="form.cog.measurement_cu_metre" type="number" step="0.001" class="w-full border-[#cfe6c8] bg-white text-[#234222]" placeholder="0.000" />
                                    <InputError class="mt-2" :message="form.errors['cog.measurement_cu_metre']" />
                                </div>
                                <div class="rounded-[1.35rem] border border-[#d8e7d4] bg-white p-4">
                                    <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Gross Wt. (Kg)</label>
                                    <TextInput v-model="form.cog.gross_weight_kg" type="number" step="0.001" class="w-full border-[#cfe6c8] bg-white text-[#234222]" placeholder="0.000" />
                                    <InputError class="mt-2" :message="form.errors['cog.gross_weight_kg']" />
                                </div>
                                <div class="rounded-[1.35rem] border border-[#d8e7d4] bg-white p-4">
                                    <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">PO No.</label>
                                    <TextInput v-model="form.cog.po_no" class="w-full border-[#cfe6c8] bg-white text-[#234222]" placeholder="PO no." />
                                    <InputError class="mt-2" :message="form.errors['cog.po_no']" />
                                </div>
                                <div class="rounded-[1.35rem] border border-[#d8e7d4] bg-white p-4">
                                    <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Ex Location</label>
                                    <TextInput v-model="form.cog.ex_location" class="w-full border-[#cfe6c8] bg-white text-[#234222]" placeholder="Source / ex location" />
                                    <InputError class="mt-2" :message="form.errors['cog.ex_location']" />
                                </div>
                            </div>

                            <div class="rounded-[1.35rem] border border-[#d8e7d4] bg-white p-4">
                                <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">COG Remarks</label>
                                <textarea
                                    v-model="form.cog.remarks"
                                    class="textarea h-28 w-full border-[#cfe6c8] bg-white text-[#234222] placeholder:text-[#7f9a7a]"
                                    rows="4"
                                    placeholder="Add consignee note, approval context, cargo note, or receiving instructions..."
                                />
                                <InputError class="mt-2" :message="form.errors['cog.remarks']" />
                            </div>
                        </div>
                    </section>

                    <section class="rounded-[1.6rem] border border-[#d8e7d4] bg-[#fbfefa] p-5">
                        <div class="mb-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-[#7f9a7a]">Remarks</p>
                            <h3 class="mt-2 text-lg font-semibold text-[#234222]">Movement notes</h3>
                        </div>

                        <textarea
                            v-model="form.remarks"
                            class="textarea h-32 w-full border-[#cfe6c8] bg-white text-[#234222] placeholder:text-[#7f9a7a]"
                            rows="4"
                            placeholder="Add handover notes, site context, transfer reason, receiving note, or any movement remark..."
                        />
                        <InputError class="mt-2" :message="form.errors.remarks" />
                    </section>

                    <div class="flex flex-wrap items-center justify-between gap-4 rounded-[1.5rem] border border-[#cfe6c8] bg-[#eef8ea] px-5 py-4">
                        <div>
                            <p class="text-sm font-semibold text-[#234222]">Ready to record this movement</p>
                            <p class="mt-1 text-sm text-[#5f7b5e]">
                                {{ showCogSection && form.generate_cog
                                    ? 'The movement will be posted, a COG will be generated, and the receiver approval email will be prepared if an email address is entered.'
                                    : 'The movement will be posted into asset tracking and reflected in the movement dashboard.' }}
                            </p>
                        </div>
                        <PrimaryButton
                            :disabled="form.processing"
                            class="w-full justify-center rounded-full border-none bg-[linear-gradient(135deg,#6fbb68_0%,#4f9f4a_100%)] px-6 py-3 text-sm font-bold uppercase tracking-[0.24em] text-white shadow-[0_18px_40px_rgba(79,159,74,0.24)] transition hover:scale-[1.02] hover:opacity-95 sm:min-w-[220px] sm:w-auto"
                        >
                            {{ showCogSection && form.generate_cog ? 'Record Movement + Create COG' : 'Record Movement' }}
                        </PrimaryButton>
                    </div>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
