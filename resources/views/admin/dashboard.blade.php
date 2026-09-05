@extends('layouts.admin')

@section('content')
    <!-- System Telemetry & Quantitative Metrics -->
    @include('admin.partials.metrics')

    <!-- Encrypted Frequency Directory & Management Grid -->
    @include('admin.partials.channels-section')

    <!-- Registered Operatives Intelligence Roster -->
    @include('admin.partials.operatives-section')

    <!-- Global Visitor Intelligence Satellite Map -->
    @include('admin.partials.intel-section')

    <!-- Real-time Transmission & Telemetry Logs -->
    @include('admin.partials.logs-section')
@endsection
