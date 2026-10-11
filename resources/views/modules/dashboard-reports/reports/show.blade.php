@extends('layouts.app')

@section('title', $title)

@section('breadcrumbs')
    <x-breadcrumbs :items="['Reports' => route('reports.show', 'current-stock'), $title => null]" />
@endsection

@section('content')
    <livewire:dashboard-reports.report-table :report="$report" />
@endsection
