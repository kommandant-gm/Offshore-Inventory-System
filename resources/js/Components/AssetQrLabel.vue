<script setup>
defineProps({ asset: Object, qrImage: String });
</script>

<template>
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
        <p class="asset-label-tag text-[13px] font-black leading-tight text-[#173516]">{{ asset.asset_tag_no }}</p>
        <p class="mt-1 line-clamp-2 text-[9px] font-semibold leading-tight text-[#526a7d]">{{ asset.description }}</p>
        <p class="mt-2 text-[6px] uppercase tracking-[.12em] text-[#718496]">Scan for asset details</p>
      </div>
    </div>
  </article>
</template>
