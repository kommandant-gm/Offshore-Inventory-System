<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
const props = defineProps({ cog: Object, document: Object, canEdit: Boolean });
const canvas = ref(null); const signing = ref(false); const signature = ref('');
const start = (event) => { if (!props.canEdit || props.cog.signature) return; signing.value = true; const rect = canvas.value.getBoundingClientRect(); const context = canvas.value.getContext('2d'); context.beginPath(); context.moveTo((event.clientX - rect.left) * canvas.value.width / rect.width, (event.clientY - rect.top) * canvas.value.height / rect.height); };
const draw = (event) => { if (!signing.value) return; const rect = canvas.value.getBoundingClientRect(); const context = canvas.value.getContext('2d'); context.lineTo((event.clientX - rect.left) * canvas.value.width / rect.width, (event.clientY - rect.top) * canvas.value.height / rect.height); context.stroke(); };
const end = () => { if (signing.value) signature.value = canvas.value.toDataURL('image/png'); signing.value = false; };
const clear = () => { signature.value = ''; canvas.value?.getContext('2d').clearRect(0, 0, canvas.value.width, canvas.value.height); };
const cancellation = useForm({ reason: '' });
const cancel = () => { if (window.confirm('Confirm this draft was never fulfilled. Cancel it and release its equipment?')) cancellation.post(route('miri-cogs.cancel', props.cog.id), { preserveScroll: true }); };
const signatureForm = useForm({ signature: '' });
const sign = () => { signatureForm.signature = signature.value; signatureForm.post(route('miri-cogs.sign', props.cog.id), { preserveScroll: true }); };
</script>
<template>
<Head title="Miri Internal Issue Note" />
<AuthenticatedLayout>
<section class="space-y-5">
<header class="flex flex-wrap justify-between gap-4 rounded-3xl border bg-white p-6"><div><h1 class="text-2xl font-bold text-[#234222]">{{ cog.cog_no }}</h1><p class="mt-2 text-sm text-slate-500">{{ cog.movement_type }} · {{ cog.status }} · Document only</p></div><div class="flex gap-2"><a :href="route('miri-cogs.pdf',cog.id)" class="btn bg-[#234222] text-white">Download PDF</a><Link :href="route('miri-cogs.index')" class="btn">Back to register</Link></div></header>
<form v-if="canEdit && cog.status === 'draft' && ['Issue out','Transfer','Return to supplier'].includes(cog.movement_type)" class="rounded-xl border bg-white p-4" @submit.prevent="cancel">
<label class="block text-sm">Cancel unfulfilled draft (equipment must not have been dispatched)<input v-model="cancellation.reason" required maxlength="1000" placeholder="Reason for cancellation" class="input input-bordered mt-2 w-full" /></label>
<p v-if="cancellation.errors.reason" class="text-red-700">{{ cancellation.errors.reason }}</p>
<button class="btn mt-2" :disabled="cancellation.processing">Cancel unfulfilled note</button>
</form>
<div class="overflow-x-auto">
<article v-for="(rows,p) in document.pages" :key="p" class="issue-sheet mb-5 min-w-[1050px] bg-white p-6 text-black">
<p class="text-right text-xs">DE-F-07E</p>
<div class="border border-black p-3"><div class="flex items-start justify-between gap-4"><div class="cog-logo"><img src="/images/dayang-logo.png" alt="Dayang" /></div><div class="text-center"><p class="text-xl font-bold text-green-700">DAYANG ENTERPRISE SDN. BHD.</p><p class="text-xs font-bold">NO. SYARIKAT: 198001007721 (61505-V)</p><h2 class="text-3xl font-bold">Internal Issue Note</h2></div><p class="text-sm">No. <strong class="text-xl text-red-600">{{ cog.cog_no }}</strong></p></div>
<div class="mt-4 grid grid-cols-3 gap-8 text-xs"><div><p>CONSIGNEE: {{ cog.consignee_name || cog.receiver_name }}</p><p class="border-b border-black">{{ cog.consignee_department || ' ' }}</p><p class="mt-2">FROM: {{ cog.from_department }}</p><p class="border-b border-black">{{ cog.from_location || ' ' }}</p></div><div><p>TO: {{ cog.to_location }}</p><p class="mt-3 border-b border-black">COPY: {{ cog.copy_to }}</p></div><div><p>DATE: {{ cog.document_date }}</p><p class="mt-2">PAGE: {{ p+1 }} OF {{ document.pages.length }}</p><p class="mt-2 border-b border-black">DESTINATION: {{ cog.destination || cog.to_location }}</p></div></div></div>
<table class="issue-table"><colgroup><col v-for="width in [4,5,5,30,11,14,11,12,8]" :style="{ width: width + '%' }" /></colgroup><thead><tr><th>ITEM</th><th>QTY</th><th>UNIT</th><th>FULL DESCRIPTION</th><th>SIZE / MODEL</th><th>TAGGING NO.</th><th>SERIAL NO.</th><th>MATERIAL<br>REQUISITION NO.</th><th>REMARKS</th></tr></thead><tbody><tr v-for="(row,i) in rows" :key="i"><td v-for="key in ['item','quantity','unit','description','size_model','identifier','serial_no','mr_reference','remarks']" :key="key" :class="{ 'text-center': ['quantity','unit'].includes(key) }">{{ row[key] || '' }}</td></tr></tbody></table>
<div class="grid grid-cols-[16%,28%,28%,28%] border-x border-b border-black"><div class="border-r border-black p-2 text-xs"><strong>TOTAL QTY BY UNIT</strong><p class="mt-6 text-lg">{{ p===document.pages.length-1 ? document.totals.join(' · ') : 'Continued' }}</p></div><div class="col-span-3"><p class="border-b border-black p-1 text-center text-xs font-bold">WE HEREBY CERTIFY THIS INFORMATION TO BE TRUE AND CORRECT</p><div class="grid grid-cols-3"><div v-for="s in [{title:'Issued & Checked By:',name:'issued_by_name',designation:'issued_designation',date:'issued_date'},{title:'Verified By: HOD / SUPERVISOR',name:'verified_by_name',designation:'verified_designation',date:'verified_date'},{title:'Received By:',name:'receiver_name',designation:'receiver_designation',date:'received_date'}]" :key="s.name" class="border-r border-black p-2 text-xs last:border-r-0"><strong>{{ s.title }}</strong><div class="my-2 h-10 border-b border-black"><img v-if="s.name==='receiver_name' && cog.signature && p===document.pages.length-1" :src="cog.signature" alt="Receiver signature" class="max-h-10 max-w-full" /></div><p>NAME: {{ cog[s.name] }}</p><p>DESIGNATION: {{ cog[s.designation] }}</p><p>DATE: {{ cog[s.date] || (s.name==='receiver_name' ? cog.signed_at?.slice(0,10) : '') }}</p></div></div></div></div>
<p class="mt-2 text-[10px] font-bold">**IMPORTANT** COPY OF THIS ISSUE NOTE MUST BE RETURNED BACK UPON ACKNOWLEDGED RECEIPT TO ORIGINATOR</p><p class="mt-2 text-xs">REV.0</p>
</article></div>
<section v-if="canEdit && cog.status === 'draft' && !cog.signature" class="rounded-3xl border bg-white p-6"><h2 class="font-bold text-[#234222]">Digital receiver signature</h2><p class="mt-2 text-sm text-slate-500">Records receiver acknowledgement only, not HOD approval. Inventory quantities and locations remain unchanged.</p><p v-if="signatureForm.errors.signature" role="alert" class="mt-2 text-red-700">{{ signatureForm.errors.signature }}</p><canvas ref="canvas" width="700" height="180" class="mt-4 h-44 w-full touch-none rounded-xl border border-dashed bg-white" @pointerdown="start" @pointermove="draw" @pointerup="end" @pointerleave="end" @pointercancel="end" /><div class="mt-3 flex gap-3"><button class="btn" @click="clear">Clear</button><button class="btn bg-[#234222] text-white" :disabled="!signature || signatureForm.processing" @click="sign">Sign COG</button></div></section>
</section>
</AuthenticatedLayout>
</template>
<style scoped>
.cog-logo{width:65px;height:66px;overflow:hidden;flex-shrink:0}.cog-logo img{width:325px;max-width:none;margin-left:-21px;margin-top:-11px}
.issue-sheet{font-size:10pt}.issue-sheet .text-xs,.issue-sheet .text-sm{font-size:10pt;line-height:1.15}
.issue-table{width:100%;border-collapse:collapse;table-layout:fixed;font-size:10pt}
.issue-table td,.issue-table th{border:1px solid black;padding:0 2pt;height:14pt;line-height:14pt}
.issue-table td{font-family:monospace;white-space:nowrap}.issue-table th{font-size:10pt;height:32pt}
.issue-sheet .text-lg{font-size:11pt}
</style>
