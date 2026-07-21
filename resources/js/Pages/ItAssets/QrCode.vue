<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import QRCode from 'qrcode';
import { onMounted, ref } from 'vue';

const props = defineProps({ asset: Object });
const qrImage = ref('');
const copied = ref(false);

const renderQr = async () => {
  if (!props.asset.public_url) return;
  qrImage.value = await QRCode.toDataURL(props.asset.public_url, {
    width: 720,
    margin: 2,
    errorCorrectionLevel: 'H',
    color: { dark: '#173516', light: '#ffffff' },
  });
};
onMounted(renderQr);

const generate = () => router.post(route('it-assets.qr-code.store', props.asset.id));
const regenerate = () => {
  if (!window.confirm('Regenerate this QR code? The previously printed code will stop working.')) return;
  router.post(route('it-assets.qr-code.regenerate', props.asset.id));
};
const copyUrl = async () => {
  await navigator.clipboard.writeText(props.asset.public_url);
  copied.value = true;
  window.setTimeout(() => { copied.value = false; }, 1800);
};
const printLabel = () => window.print();
</script>

<template>
  <Head :title="`QR Code - ${asset.asset_tag_no}`" />
  <AuthenticatedLayout>
    <section class="space-y-6">
      <header class="no-print flex flex-wrap items-end justify-between gap-4 rounded-[2rem] border border-[#d8e7d4] bg-white p-7">
        <div><p class="text-xs font-bold uppercase tracking-[.25em] text-[#4f9f4a]">Asset identification</p><h1 class="mt-2 text-3xl font-bold text-[#234222]">Asset QR code</h1><p class="mt-2 text-sm text-[#60745d]">Generate and print a scannable public asset label.</p></div>
        <Link class="btn" :href="route('it-assets.show', asset.id)">Back to asset</Link>
      </header>

      <div v-if="!asset.public_url" class="no-print rounded-[2rem] border border-[#d8e7d4] bg-white p-10 text-center">
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-[#eef8ea] text-2xl font-bold text-[#2f7d32]">QR</div>
        <h2 class="mt-5 text-2xl font-bold text-[#234222]">No QR code generated yet</h2>
        <p class="mx-auto mt-2 max-w-lg text-sm text-[#60745d]">Generating a code creates a secure public link for this asset. The public page will not show private assignment or cost information.</p>
        <button type="button" class="btn mt-6 bg-[#4f9f4a] text-white" @click="generate">Generate QR code</button>
      </div>

      <template v-else>
        <div class="grid gap-6 xl:grid-cols-[1fr,22rem]">
          <div class="qr-print-area flex min-h-[22rem] items-center justify-center rounded-[2rem] border border-[#d8e7d4] bg-[#f4f7fa] p-8">
            <article class="asset-label grid h-[170px] w-[300px] grid-cols-[128px,1fr] overflow-hidden rounded-xl border-2 border-[#173516] bg-white shadow-xl">
              <div class="flex items-center justify-center border-r border-[#d8e7d4] p-2.5">
                <img :src="qrImage" class="h-[108px] w-[108px]" alt="Asset QR code" />
              </div>
              <div class="flex min-w-0 flex-col">
                <div class="flex items-center gap-2 bg-[#062344] px-3 py-2.5 text-white">
                  <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-[#4f9f4a] text-sm font-black">D</div>
                  <div class="min-w-0"><p class="truncate text-xs font-bold leading-tight">Dayang Inventory</p><p class="text-[7px] uppercase tracking-[.13em] text-[#b9d5ea]">IT Asset</p></div>
                </div>
                <div class="flex flex-1 flex-col justify-center px-3 py-2">
                  <p class="asset-label-tag text-[13px] font-black leading-tight text-[#173516]">{{asset.asset_tag_no}}</p>
                  <p class="mt-1 line-clamp-2 text-[9px] font-semibold leading-tight text-[#526a7d]">{{asset.description}}</p>
                  <p class="mt-2 text-[6px] uppercase tracking-[.12em] text-[#718496]">Scan for asset details</p>
                </div>
              </div>
            </article>
          </div>

          <aside class="no-print space-y-4 rounded-[2rem] border border-[#d8e7d4] bg-white p-6">
            <div><p class="text-xs font-bold uppercase tracking-wider text-[#7f9a7a]">Asset</p><p class="mt-1 font-bold text-[#234222]">{{asset.asset_tag_no}}</p><p class="text-sm text-[#60745d]">{{asset.description}}</p></div>
            <div class="rounded-xl bg-[#f4f8f2] p-4 text-sm"><p class="font-semibold text-[#234222]">Public URL</p><p class="mt-1 break-all text-xs text-[#60745d]">{{asset.public_url}}</p></div>
            <button type="button" class="btn w-full bg-[#4f9f4a] text-white" @click="printLabel">Print asset label</button>
            <a :href="qrImage" :download="`${asset.asset_tag_no.replaceAll('/','-')}-qr.png`" class="btn w-full border-[#cfe6c8] bg-white">Download QR image</a>
            <button type="button" class="btn w-full border-[#cfe6c8] bg-white" @click="copyUrl">{{copied ? 'Link copied' : 'Copy public link'}}</button>
            <button type="button" class="btn w-full border-[#edc5c0] bg-[#fff6f4] text-[#9a3f32]" @click="regenerate">Regenerate code</button>
            <p class="text-xs leading-5 text-[#7f9a7a]">Regenerating invalidates every previously printed label for this asset.</p>
          </aside>
        </div>
      </template>
    </section>
  </AuthenticatedLayout>
</template>

<style>
@media print {
  @page { size: 60mm 34mm; margin: 0; }
  body * { visibility: hidden !important; }
  .qr-print-area, .qr-print-area * { visibility: visible !important; }
  .qr-print-area { position: fixed !important; inset: 0 !important; min-height: 0 !important; border: 0 !important; background: white !important; padding: 0 !important; }
  .asset-label { width: 60mm !important; height: 34mm !important; border-radius: 2mm !important; box-shadow: none !important; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
  .asset-label-tag { overflow-wrap: anywhere; }
}
</style>
