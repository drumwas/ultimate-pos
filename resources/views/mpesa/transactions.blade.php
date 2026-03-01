@extends('layouts.app')

@section('title', 'M-Pesa Transactions Report')

@section('content')

    <div class="content-wrapper">
        <section class="content-header">
            <h1>
                <i class="fas fa-mobile-alt text-success"></i>
                M-Pesa Transactions Report
            </h1>
            <ol class="breadcrumb">
                <li><a href="{{ route('home') }}"><i class="fa fa-dashboard"></i> Home</a></li>
                <li class="active">M-Pesa Transactions</li>
            </ol>
        </section>

        <section class="content">

            {{-- Summary Cards --}}
            <div class="row">
                <div class="col-md-3 col-sm-6">
                    <div class="info-box bg-aqua">
                        <span class="info-box-icon"><i class="fas fa-list"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Total Requests</span>
                            <span class="info-box-number">{{ number_format($summary['total']) }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="info-box bg-green">
                        <span class="info-box-icon"><i class="fas fa-check-circle"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Completed</span>
                            <span class="info-box-number">{{ number_format($summary['completed']) }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="info-box bg-yellow">
                        <span class="info-box-icon"><i class="fas fa-clock"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Pending</span>
                            <span class="info-box-number">{{ number_format($summary['pending']) }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="info-box bg-teal">
                        <span class="info-box-icon"><i class="fas fa-money-bill-wave"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Total Revenue (KES)</span>
                            <span class="info-box-number">{{ number_format($summary['revenue']) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Filter Form --}}
            <div class="box box-solid">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-filter"></i> Filter Transactions</h3>
                </div>
                <div class="box-body">
                    <form method="GET" action="{{ route('mpesa.transactions') }}">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Date From</label>
                                    <input type="date" name="date_from" class="form-control"
                                        value="{{ request('date_from') }}">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Date To</label>
                                    <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>Status</label>
                                    <select name="status" class="form-control">
                                        <option value="">All</option>
                                        <option value="completed" @selected(request('status') === 'completed')>Completed
                                        </option>
                                        <option value="pending" @selected(request('status') === 'pending')>Pending</option>
                                        <option value="failed" @selected(request('status') === 'failed')>Failed</option>
                                        <option value="cancelled" @selected(request('status') === 'cancelled')>Cancelled
                                        </option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>Phone</label>
                                    <input type="text" name="phone" class="form-control" placeholder="2547XXXXXXXX"
                                        value="{{ request('phone') }}">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <div>
                                        <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i>
                                            Filter</button>
                                        <a href="{{ route('mpesa.transactions') }}" class="btn btn-default"><i
                                                class="fa fa-refresh"></i> Reset</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Transactions Table --}}
            <div class="box box-solid">
                <div class="box-header with-border">
                    <h3 class="box-title">
                        <i class="fas fa-table"></i>
                        Transactions
                        <small class="text-muted">({{ $transactions->total() }} total)</small>
                    </h3>
                </div>
                <div class="box-body no-padding">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Date &amp; Time</th>
                                    <th>Phone</th>
                                    <th>Amount (KES)</th>
                                    <th>Sale Ref</th>
                                    <th>M-Pesa Receipt</th>
                                    <th>Status</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($transactions as $t)
                                    <tr>
                                        <td>{{ $t->id }}</td>
                                        <td>{{ $t->created_at->format('d M Y, H:i') }}</td>
                                        <td>{{ $t->phone }}</td>
                                        <td><strong>{{ number_format($t->amount) }}</strong></td>
                                        <td>
                                            @if($t->reference)
                                                <a href="{{ route('sell.printInvoice', $t->reference) }}" target="_blank">
                                                    Sale #{{ $t->reference }}
                                                </a>
                                            @else
                                                &mdash;
                                            @endif
                                        </td>
                                        <td>
                                            @if($t->mpesa_receipt_number)
                                                <code>{{ $t->mpesa_receipt_number }}</code>
                                            @else
                                                &mdash;
                                            @endif
                                        </td>
                                        <td>
                                            @php
                                                $badge = match ($t->status) {
                                                    'completed' => 'success',
                                                    'pending' => 'warning',
                                                    'failed' => 'danger',
                                                    'cancelled' => 'default',
                                                    default => 'info',
                                                };
                                            @endphp
                                            <span class="label label-{{ $badge }}">{{ ucfirst($t->status) }}</span>
                                        </td>
                                        <td>{{ $t->result_desc ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted">No transactions found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($transactions->hasPages())
                    <div class="box-footer clearfix">
                        {{ $transactions->links() }}
                    </div>
                @endif
            </div>

        </section>
    </div>

@endsection