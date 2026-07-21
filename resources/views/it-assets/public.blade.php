<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $asset->asset_tag_no }} - Dayang Inventory</title>
    @vite('resources/css/app.css')
</head>
<body class="min-h-screen bg-[#f3f6f9] font-sans text-[#132f47] antialiased">
    @php
        $status = str($asset->current_status->value)->replace('_', ' ')->title();
        $condition = $asset->current_condition ? str($asset->current_condition->value)->replace('_', ' ')->title() : null;
        $fields = [
            ['Asset tag', $asset->asset_tag_no],
            ['Category', $asset->category?->name],
            ['Description', $asset->description],
            ['Brand', $asset->brand],
            ['Model', $asset->model],
            ['Serial number', $asset->serial_no],
            ['Operating system', $asset->operating_system],
            ['Purchase year', $asset->purchase_year ?: $asset->year],
            ['Current location', $asset->currentLocation?->name],
            ['Assigned to', $asset->currentAssignment?->assigned_to_name ?: 'Unassigned'],
            ['Department', $asset->currentAssignment?->department],
            ['Ownership', $asset->ownership],
        ];
    @endphp
    <main class="mx-auto w-full max-w-5xl px-4 py-6 sm:px-6 sm:py-10">
        <article class="overflow-hidden rounded-[2rem] bg-white shadow-[0_20px_60px_rgba(6,35,68,.12)] ring-1 ring-[#dce6ee]">
            <header class="relative overflow-hidden bg-[#062344] px-6 py-9 text-white sm:px-10 sm:py-12">
                <div class="absolute -right-14 -top-20 h-56 w-56 rounded-full border border-[#4f9f4a]/30"></div>
                <div class="absolute -right-2 -top-14 h-40 w-40 rounded-full border border-[#4f9f4a]/30"></div>
                <div class="relative">
                    <div class="mb-8 flex items-center gap-3">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-[#4f9f4a] text-2xl font-black">D</div>
                        <div><p class="text-lg font-bold leading-tight">Dayang Inventory</p><p class="text-[10px] uppercase tracking-[.22em] text-[#b9d5ea]">Management System</p></div>
                    </div>
                    <div class="h-1 w-14 rounded-full bg-[#4f9f4a]"></div>
                    <p class="mt-5 text-xs font-bold uppercase tracking-[.24em] text-[#7fd277]">Public asset record</p>
                    <h1 class="mt-3 break-words text-3xl font-black leading-tight sm:text-5xl">{{ $asset->asset_tag_no }}</h1>
                    <p class="mt-3 max-w-2xl text-base text-[#c7d9e8] sm:text-lg">{{ $asset->model ?: $asset->description }}</p>
                </div>
            </header>

            <div class="space-y-6 p-5 sm:p-10">
                <section class="rounded-2xl border border-[#dce6ee] bg-[#f8fafc] p-5">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div><p class="text-xs font-bold uppercase tracking-[.16em] text-[#718496]">Asset verification</p><h2 class="mt-1 text-lg font-bold text-[#132f47]">Registered IT equipment</h2><p class="mt-1 text-sm text-[#657b8d]">This record is provided by Dayang Inventory Management System.</p></div>
                        <div class="flex flex-wrap gap-2"><span class="rounded-full bg-[#eaf6e7] px-4 py-2 text-sm font-bold text-[#2f7d32]">{{ $status }}</span>@if($condition)<span class="rounded-full bg-[#eaf1f7] px-4 py-2 text-sm font-bold text-[#315a78]">{{ $condition }}</span>@endif</div>
                    </div>
                </section>

                <section class="rounded-2xl border border-[#dce6ee] p-5 sm:p-7">
                    <div class="mb-6 flex items-center gap-3"><div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#eaf6e7] font-black text-[#2f7d32]">i</div><div><h2 class="font-bold text-[#132f47]">Asset information</h2><p class="text-sm text-[#718496]">Read-only identification and equipment details.</p></div></div>
                    <dl class="grid gap-5 sm:grid-cols-2">
                        @foreach($fields as [$label, $value])
                            <div class="rounded-xl border border-[#dce6ee] bg-white px-4 py-3.5">
                                <dt class="text-xs font-bold uppercase tracking-[.12em] text-[#718496]">{{ $label }}</dt>
                                <dd class="mt-1.5 break-words text-base font-semibold text-[#132f47]">{{ filled($value) ? $value : 'Not specified' }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </section>

                <aside class="rounded-2xl border border-[#f2d58b] bg-[#fff9e9] p-5 text-sm text-[#7a5a19]">
                    <p class="font-bold">Asset identification notice</p>
                    <p class="mt-1 leading-6">If this equipment is lost, misplaced, or requires support, please contact the Dayang IT department and provide the asset tag shown above.</p>
                </aside>
            </div>
        </article>
        <p class="py-6 text-center text-xs text-[#718496]">Public record updated {{ $asset->updated_at->format('d M Y') }} · No login required</p>
    </main>
</body>
</html>
