@extends('layouts.frontend')

@section('title', 'Modules — Buildarya Platform')
@section('description', 'Explore Buildarya seven modular construction tools.')

@section('content')
    <section class="py-16 bg-gradient-to-b from-teal-50/50 via-slate-50 to-white text-center border-b border-slate-200/80">
        <div class="max-w-4xl mx-auto px-4 space-y-4">
            <span class="text-xs uppercase tracking-widest font-extrabold text-teal-700 bg-teal-50 border border-teal-200 px-3.5 py-1 rounded-full inline-block">
                SYSTEM MODULES
            </span>
            <h1 class="font-display text-3xl sm:text-5xl font-extrabold text-slate-900">Integrated Modules For Contractors</h1>
            <p class="text-xs sm:text-sm text-slate-600 font-medium max-w-2xl mx-auto">Activate the exact modules your business needs today.</p>
        </div>
    </section>

    <section class="py-16 bg-slate-50">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                @php
                    $modulesData = [
                        ['name' => 'Sites & Users', 'tag' => 'Security', 'desc' => 'Set up sites, assign engineers, and configure role access.'],
                        ['name' => 'Expense & Cash Log', 'tag' => 'Finance', 'desc' => 'Daily petty cash logging with receipt photo attachments.'],
                        ['name' => 'Material Procurement', 'tag' => 'Purchasing', 'desc' => 'Issue POs and track vendor delivery arrivals on site.'],
                        ['name' => 'Stock Control', 'tag' => 'Inventory', 'desc' => 'Track stock balances for steel, cement, and aggregates.'],
                        ['name' => 'Site Billing', 'tag' => 'Invoicing', 'desc' => 'Generate sequential client bills and maintain ledgers.'],
                        ['name' => 'Machinery & Equipment', 'tag' => 'Assets', 'desc' => 'Track JCB/crane usage hours, fuel, and rental costs.'],
                    ];
                @endphp
                @foreach($modulesData as $m)
                    <div class="bg-white p-7 rounded-3xl border border-slate-200 shadow-soft hover:shadow-card transition-all">
                        <span class="text-[10px] uppercase font-extrabold text-teal-700 bg-teal-50 px-2.5 py-0.5 rounded-md inline-block mb-3">{{ $m['tag'] }}</span>
                        <h3 class="font-display text-lg font-bold text-slate-900 mb-2">{{ $m['name'] }}</h3>
                        <p class="text-xs text-slate-600 font-medium leading-relaxed">{{ $m['desc'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endsection
