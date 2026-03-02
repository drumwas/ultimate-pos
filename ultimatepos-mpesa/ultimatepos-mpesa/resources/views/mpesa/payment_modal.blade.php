{{-- -----------------------------------------------------------------------
   M-Pesa Payment Modal Partial
   -----------------------------------------------------------------------
   Include this inside UltimatePOS's existing payment modal, alongside
   the other payment method tabs (Cash, Card, etc.).

   Usage: @include('mpesa.payment_modal')

   Requires:
     - jQuery (already present in UltimatePOS)
     - The transaction ID available as $transaction->id (Blade variable)
       OR passed via a data attribute from the parent view.
   ----------------------------------------------------------------------- --}}

{{-- -----------------------------------------------
   TAB TRIGGER — add this alongside other method tabs
   ----------------------------------------------- --}}
{{--
<li class="nav-item">
    <a class="nav-link" id="mpesa-tab" data-toggle="tab" href="#mpesa" role="tab">
        <i class="fab fa-whatsapp"></i> M-Pesa
    </a>
</li>
--}}

{{-- -----------------------------------------------
   TAB PANE — add this inside .tab-content
   ----------------------------------------------- --}}
<div class="tab-pane fade" id="mpesa-payment-pane" role="tabpanel">
    <div class="row mt-3">

        <div class="col-sm-12">
            <div class="form-group">
                <label for="mpesa_phone">Customer Phone Number</label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text">+254</span>
                    </div>
                    <input
                        type="text"
                        id="mpesa_phone"
                        class="form-control"
                        placeholder="7XXXXXXXX or 1XXXXXXXX"
                        maxlength="9"
                    >
                </div>
                <small class="text-muted">Enter the M-Pesa registered number. We'll send a PIN prompt.</small>
            </div>
        </div>

        <div class="col-sm-12">
            <div class="form-group">
                <label>Amount (KES)</label>
                <input
                    type="number"
                    id="mpesa_amount"
                    class="form-control"
                    placeholder="Amount to pay via M-Pesa"
                    min="1"
                >
                <small class="text-muted" id="mpesa_balance_hint"></small>
            </div>
        </div>

        {{-- Status display area — hidden until STK is initiated --}}
        <div class="col-sm-12" id="mpesa_status_area" style="display:none;">
            <div class="alert" id="mpesa_status_alert" role="alert">
                <span id="mpesa_status_icon"></span>
                <span id="mpesa_status_message"></span>
            </div>
        </div>

        <div class="col-sm-12">
            <button
                type="button"
                id="mpesa_request_btn"
                class="btn btn-success btn-block"
            >
                <i class="fas fa-mobile-alt"></i>
                Send Payment Request
            </button>
            <button
                type="button"
                id="mpesa_cancel_btn"
                class="btn btn-outline-secondary btn-block mt-1"
                style="display:none;"
            >
                Cancel / Try Again
            </button>
        </div>

    </div>
</div>

{{-- -----------------------------------------------
   JavaScript — add before closing </body> or in a @push('javascript')
   ----------------------------------------------- --}}
<script>
(function () {
    // -----------------------------------------------------------------------
    // Configuration — update these if your UltimatePOS passes them differently
    // -----------------------------------------------------------------------
    const TRANSACTION_ID = {{ $transaction->id ?? 0 }};   // set by Blade
    const STATUS_URL     = "{{ route('mpesa.status', ':id') }}".replace(':id', TRANSACTION_ID);
    const INITIATE_URL   = "{{ route('mpesa.pay') }}";
    const CSRF           = "{{ csrf_token() }}";

    // -----------------------------------------------------------------------
    let pollTimer   = null;
    let pollTimeout = null;

    // -----------------------------------------------------------------------
    // Pre-fill the amount when the tab is opened
    // -----------------------------------------------------------------------
    $('#mpesa-tab').on('shown.bs.tab', function () {
        // Try to read the outstanding balance from UltimatePOS's existing logic
        const due = parseFloat($('#amount_due').val() || $('#final_total').val() || 0);
        if (due > 0) {
            $('#mpesa_amount').val(Math.ceil(due));
            $('#mpesa_balance_hint').text('Balance due: KES ' + due.toFixed(2));
        }
    });

    // -----------------------------------------------------------------------
    // Request payment button
    // -----------------------------------------------------------------------
    $('#mpesa_request_btn').on('click', function () {
        const rawPhone = $('#mpesa_phone').val().trim();
        const amount   = parseFloat($('#mpesa_amount').val());

        if (!rawPhone) {
            showStatus('warning', '<i class="fas fa-exclamation-triangle"></i> Please enter the customer\'s phone number.');
            return;
        }
        if (!amount || amount < 1) {
            showStatus('warning', '<i class="fas fa-exclamation-triangle"></i> Please enter a valid amount.');
            return;
        }

        // Normalize: strip leading 0, add 254 prefix
        let phone = rawPhone.replace(/\D/g, '');
        if (phone.startsWith('0')) {
            phone = '254' + phone.substring(1);
        } else if (!phone.startsWith('254')) {
            phone = '254' + phone;
        }

        setLoading(true);
        showStatus('info', '<i class="fas fa-spinner fa-spin"></i> Sending payment request to ' + rawPhone + '...');

        $.ajax({
            url:         INITIATE_URL,
            method:      'POST',
            data:        { transaction_id: TRANSACTION_ID, phone: phone, amount: amount, _token: CSRF },
            dataType:    'json',
            success:     function (response) {
                if (response.success) {
                    showStatus('info', '<i class="fas fa-spinner fa-spin"></i> Prompt sent! Waiting for customer PIN...');
                    startPolling();
                } else {
                    setLoading(false);
                    showStatus('danger', '<i class="fas fa-times-circle"></i> ' + response.message);
                }
            },
            error:       function (xhr) {
                setLoading(false);
                const msg = xhr.responseJSON?.message || 'Request failed. Please try again.';
                showStatus('danger', '<i class="fas fa-times-circle"></i> ' + msg);
            },
        });
    });

    // -----------------------------------------------------------------------
    // Cancel / retry button
    // -----------------------------------------------------------------------
    $('#mpesa_cancel_btn').on('click', function () {
        stopPolling();
        resetForm();
    });

    // -----------------------------------------------------------------------
    // Polling — check status every 3 seconds
    // -----------------------------------------------------------------------
    function startPolling() {
        stopPolling(); // clear any existing timers

        pollTimer = setInterval(function () {
            $.getJSON(STATUS_URL, function (data) {

                if (data.status === 'completed') {
                    stopPolling();
                    showStatus('success',
                        '<i class="fas fa-check-circle"></i> Payment received! ' +
                        'Receipt: <strong>' + (data.receipt_number || 'N/A') + '</strong> — ' +
                        'KES ' + data.amount
                    );
                    setLoading(false);
                    onPaymentComplete(data);

                } else if (data.status === 'failed') {
                    stopPolling();
                    setLoading(false);
                    showStatus('danger',
                        '<i class="fas fa-times-circle"></i> Payment failed: ' +
                        (data.result_desc || 'Unknown error') + '. Please try again.'
                    );
                    $('#mpesa_cancel_btn').show();

                } else if (data.status === 'cancelled') {
                    stopPolling();
                    setLoading(false);
                    showStatus('warning',
                        '<i class="fas fa-ban"></i> Customer cancelled the payment. You can try again.'
                    );
                    $('#mpesa_cancel_btn').show();
                }
                // 'pending' — keep polling
            });
        }, 3000);

        // Hard stop after 90 seconds (STK Push expires in ~60s + buffer)
        pollTimeout = setTimeout(function () {
            stopPolling();
            setLoading(false);
            showStatus('warning',
                '<i class="fas fa-clock"></i> Request timed out. Please check with the customer and try again.'
            );
            $('#mpesa_cancel_btn').show();
        }, 90000);
    }

    function stopPolling() {
        if (pollTimer)   clearInterval(pollTimer);
        if (pollTimeout) clearTimeout(pollTimeout);
        pollTimer   = null;
        pollTimeout = null;
    }

    // -----------------------------------------------------------------------
    // Called after a successful payment — close modal, refresh sale totals
    // -----------------------------------------------------------------------
    function onPaymentComplete(data) {
        // Give the cashier 2 seconds to see the success message, then close
        setTimeout(function () {
            // UltimatePOS typically uses these to reload payment info
            if (typeof get_payment_due === 'function') {
                get_payment_due();
            }
            if (typeof loadPaymentDetails === 'function') {
                loadPaymentDetails(TRANSACTION_ID);
            }
            // Close the modal if the sale is fully paid
            // (the listener will have already updated payment_status server-side)
            $.ajax({
                url:     STATUS_URL,
                success: function (status) {
                    if (status.status === 'completed') {
                        // Reload the page or trigger UltimatePOS's own post-payment refresh
                        window.location.reload();
                    }
                }
            });
        }, 2000);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------
    function showStatus(type, html) {
        const $area  = $('#mpesa_status_area');
        const $alert = $('#mpesa_status_alert');
        $area.show();
        $alert.removeClass('alert-info alert-success alert-danger alert-warning')
              .addClass('alert-' + type)
              .html(html);
    }

    function setLoading(loading) {
        const $btn = $('#mpesa_request_btn');
        $btn.prop('disabled', loading);
        $('#mpesa_cancel_btn').toggle(loading);
    }

    function resetForm() {
        $('#mpesa_status_area').hide();
        $('#mpesa_cancel_btn').hide();
        $('#mpesa_request_btn').prop('disabled', false);
    }

})();
</script>
