@extends('layouts.app')

@section('title', 'M-Pesa Transactions')

@section('content')

    <!-- Content Header -->
    <section class="content-header">
        <h1>M-Pesa Transactions
            <small>View all M-Pesa payment transactions</small>
        </h1>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-12">
                @component('components.widget', ['class' => 'box-primary'])
                @slot('tool')
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Status:</label>
                            <select class="form-control" id="mpesa_status_filter">
                                <option value="">All</option>
                                <option value="pending">Pending</option>
                                <option value="completed">Completed</option>
                                <option value="failed">Failed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Date Range:</label>
                            <input type="text" class="form-control" id="mpesa_date_range" placeholder="Select date range"
                                readonly>
                        </div>
                    </div>
                </div>
                @endslot

                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="mpesa_transactions_table" width="100%">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Phone</th>
                                <th>Amount</th>
                                <th>Reference</th>
                                <th>Receipt #</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                    </table>
                </div>
                @endcomponent
            </div>
        </div>
    </section>

@endsection

@section('javascript')
    <script>
        $(document).ready(function () {
            // Initialize daterangepicker if available
            if (typeof $.fn.daterangepicker !== 'undefined') {
                $('#mpesa_date_range').daterangepicker({
                    autoUpdateInput: false,
                    locale: { cancelLabel: 'Clear', format: 'YYYY-MM-DD' },
                    ranges: {
                        'Today': [moment(), moment()],
                        'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                        'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                        'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                        'This Month': [moment().startOf('month'), moment().endOf('month')],
                        'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
                    }
                });
                $('#mpesa_date_range').on('apply.daterangepicker', function (ev, picker) {
                    $(this).val(picker.startDate.format('YYYY-MM-DD') + ' ~ ' + picker.endDate.format('YYYY-MM-DD'));
                    mpesa_table.ajax.reload();
                });
                $('#mpesa_date_range').on('cancel.daterangepicker', function () {
                    $(this).val('');
                    mpesa_table.ajax.reload();
                });
            }

            // Initialize DataTable
            var mpesa_table = $('#mpesa_transactions_table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '/reports/mpesa-transactions',
                    data: function (d) {
                        d.status_filter = $('#mpesa_status_filter').val();
                        d.date_range = $('#mpesa_date_range').val();
                    }
                },
                columns: [
                    { data: 'id', name: 'id' },
                    { data: 'type', name: 'type' },
                    { data: 'status', name: 'status' },
                    { data: 'phone', name: 'phone' },
                    { data: 'amount', name: 'amount' },
                    { data: 'reference', name: 'reference' },
                    { data: 'mpesa_receipt_number', name: 'mpesa_receipt_number' },
                    { data: 'created_at', name: 'created_at' }
                ],
                order: [[0, 'desc']]
            });

            // Reload on filter change
            $('#mpesa_status_filter').on('change', function () {
                mpesa_table.ajax.reload();
            });
        });
    </script>
@endsection