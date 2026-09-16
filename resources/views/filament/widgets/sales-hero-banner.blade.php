<x-filament-widgets::widget>
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-slate-800 to-amber-950 p-6 md:p-8 text-white shadow-xl border border-slate-700/60">
        <!-- Ambient background glow -->
        <div class="absolute -right-16 -top-16 h-64 w-64 rounded-full bg-amber-500/15 blur-3xl pointer-events-none"></div>
        <div class="absolute right-1/3 -bottom-20 h-56 w-56 rounded-full bg-blue-500/10 blur-3xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
            <!-- Left: Greeting and Consultant Profile -->
            <div class="space-y-3">
                <div class="inline-flex items-center gap-2 rounded-full bg-amber-500/20 px-3 py-1 text-xs font-semibold text-amber-300 border border-amber-500/30">
                    <span class="inline-block h-2 w-2 rounded-full bg-amber-400 animate-pulse"></span>
                    PT Kobexindo Equipment &bull; Part Sales Division
                </div>

                <div>
                    <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-white flex items-center gap-2">
                        <span>{{ $greeting }}, {{ $userName }}!</span>
                        <span class="text-2xl">💼</span>
                    </h1>
                    <p class="mt-1 text-sm text-slate-300">
                        {{ $formattedDate }} &bull; Hub Penjualan Sparepart Jungheinrich, Tyre Forklift & Ban Truk Tiron.
                    </p>
                </div>

                <!-- Live metric pill badges -->
                <div class="flex flex-wrap items-center gap-2 pt-1">
                    <div class="flex items-center gap-1.5 rounded-lg bg-slate-800/80 px-3 py-1.5 text-xs font-medium text-slate-200 border border-slate-700">
                        <x-heroicon-m-funnel class="h-4 w-4 text-amber-400" />
                        <span>Pipeline: <strong class="text-amber-300">Rp {{ number_format($activePipelineAmount, 0, ',', '.') }}</strong> ({{ $activePipelineCount }} Quote)</span>
                    </div>

                    <div class="flex items-center gap-1.5 rounded-lg bg-slate-800/80 px-3 py-1.5 text-xs font-medium text-slate-200 border border-slate-700">
                        <x-heroicon-m-trophy class="h-4 w-4 text-emerald-400" />
                        <span>PO Won: <strong class="text-emerald-300">Rp {{ number_format($wonAmount, 0, ',', '.') }}</strong></span>
                    </div>

                    @if($urgentCount > 0)
                        <div class="flex items-center gap-1.5 rounded-lg bg-rose-500/20 px-3 py-1.5 text-xs font-medium text-rose-200 border border-rose-500/40">
                            <x-heroicon-m-exclamation-triangle class="h-4 w-4 text-rose-400 animate-bounce" />
                            <span><strong>{{ $urgentCount }}</strong> Penawaran Perlu Follow-up H-3!</span>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Right: Quick Action CTAs -->
            <div class="flex flex-wrap items-center gap-3 lg:flex-col lg:items-end">
                <a href="{{ $importUrl }}" 
                   class="inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 px-4 py-2.5 text-sm font-semibold text-slate-950 shadow-lg shadow-amber-500/25 hover:from-amber-400 hover:to-amber-500 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-amber-400">
                    <x-heroicon-m-arrow-up-tray class="h-4 w-4 text-slate-950" />
                    <span>⚡ Import PDF Epicor</span>
                </a>

                <div class="flex items-center gap-2">
                    <a href="{{ $createQuoteUrl }}" 
                       class="inline-flex items-center gap-1.5 rounded-lg bg-slate-800/90 px-3 py-2 text-xs font-medium text-slate-200 border border-slate-700 hover:bg-slate-700 hover:text-white transition">
                        <x-heroicon-m-plus-circle class="h-4 w-4 text-amber-400" />
                        <span>Buat Manual</span>
                    </a>

                    <a href="{{ $customersUrl }}" 
                       class="inline-flex items-center gap-1.5 rounded-lg bg-slate-800/90 px-3 py-2 text-xs font-medium text-slate-200 border border-slate-700 hover:bg-slate-700 hover:text-white transition">
                        <x-heroicon-m-building-office-2 class="h-4 w-4 text-blue-400" />
                        <span>Customer 360</span>
                    </a>

                    <a href="{{ $poUrl }}" 
                       class="inline-flex items-center gap-1.5 rounded-lg bg-slate-800/90 px-3 py-2 text-xs font-medium text-slate-200 border border-slate-700 hover:bg-slate-700 hover:text-white transition">
                        <x-heroicon-m-truck class="h-4 w-4 text-emerald-400" />
                        <span>Lacak PO</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
