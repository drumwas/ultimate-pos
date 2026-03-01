{{-- M-Pesa Payment Modal for POS --}}
<div class="modal fade" id="mpesaPaymentModal" tabindex="-1" role="dialog" aria-labelledby="mpesaPaymentModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-top: 3px solid #00A550;">
            <div class="modal-header" style="background: #f8f8f8;">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" id="mpesa_modal_close_btn">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="mpesaPaymentModalLabel">
                    <i class="fas fa-mobile-alt" style="color:#00A550;"></i> M-Pesa Payment
                </h4>
            </div>
            <div class="modal-body">
                {{-- Amount Display --}}
                <div class="form-group">
                    <label>Amount to Pay (KES)</label>
                    <input type="text" class="form-control input-lg text-center" id="mpesa_pay_amount"
                        style="font-size:24px; font-weight:bold; color:#00A550;" readonly>
                </div>

                {{-- Phone Number --}}
                <div class="form-group">
                    <label for="mpesa_pay_phone">Customer Phone Number</label>
                    <div class="input-group">
                        <span class="input-group-addon"><i class="fas fa-phone"></i> +254</span>
                        <input type="text" class="form-control input-lg" id="mpesa_pay_phone" placeholder="7XXXXXXXX"
                            maxlength="9" style="font-size:18px; letter-spacing:2px;">
                    </div>
                    <small class="text-muted">Enter the M-Pesa registered number (without 0 or +254).</small>
                </div>

                {{-- Status Area --}}
                <div id="mpesa_stk_status_area" style="display:none;">
                    <div class="alert" id="mpesa_stk_alert" role="alert">
                        <span id="mpesa_stk_icon"></span>
                        <span id="mpesa_stk_message"></span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal" id="mpesa_cancel_stk_btn">
                    Cancel
                </button>
                <button type="button" class="btn btn-success btn-lg" id="mpesa_send_stk_btn">
                    <i class="fas fa-mobile-alt"></i> Send M-Pesa Request
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function () {
        var mpesaPollTimer = null;
        var mpesaPollTimeout = null;
        var mpesaTransactionId = null;

        // -----------------------------------------------------------------------
        // Open M-Pesa modal from POS buttons
        // -----------------------------------------------------------------------
        $(document).on('click', '#pos-mpesa-mobile, #pos-mpesa-desktop', function (e) {
            e.preventDefault();
            e.stopPropagation();

            // Check if items are in the cart
            if ($('table#pos_table tbody').find('.product_row').length <= 0) {
                if (typeof toastr !== 'undefined') {
                    toastr.warning(LANG.no_products_added || 'No products added. Please add items first.');
                }
                return;
            }

            // Read the current total payable
            var totalPayable = 0;
            if ($('input#final_total_input').length) {
                totalPayable = parseFloat($('input#final_total_input').val()) || 0;
            }
            if (totalPayable <= 0) {
                // Try reading from the displayed total
                var totalText = $('#total_payable').text().replace(/[^0-9.]/g, '');
                totalPayable = parseFloat(totalText) || 0;
            }

            if (totalPayable <= 0) {
                if (typeof toastr !== 'undefined') {
                    toastr.warning('Total payable is zero. Please add items first.');
                }
                return;
            }

            // Set amount & reset form
            $('#mpesa_pay_amount').val(totalPayable.toFixed(2));
            $('#mpesa_pay_phone').val('');
            $('#mpesa_stk_status_area').hide();
            $('#mpesa_send_stk_btn').prop('disabled', false).html('<i class="fas fa-mobile-alt"></i> Send M-Pesa Request');
            $('#mpesa_cancel_stk_btn').text('Cancel');
            mpesaTransactionId = null;

            // Open M-Pesa modal
            $('#mpesaPaymentModal').modal('show');
        });

        // Focus phone input when modal opens
        $('#mpesaPaymentModal').on('shown.bs.modal', function () {
            $('#mpesa_pay_phone').focus();
        });

        // -----------------------------------------------------------------------
        // Send M-Pesa STK Push Request
        // -----------------------------------------------------------------------
        $('#mpesa_send_stk_btn').on('click', function () {
            var rawPhone = $('#mpesa_pay_phone').val().trim();
            var amount = parseFloat($('#mpesa_pay_amount').val());

            // Validate phone
            if (!rawPhone || rawPhone.length < 9) {
                mpesaShowStatus('warning', '<i class="fas fa-exclamation-triangle"></i> Please enter a valid 9-digit phone number.');
                return;
            }

            // Normalize phone: ensure 254 prefix
            var phone = rawPhone.replace(/\D/g, '');
            if (phone.startsWith('0')) {
                phone = '254' + phone.substring(1);
            } else if (!phone.startsWith('254')) {
                phone = '254' + phone;
            }

            // Disable button, show loading
            $('#mpesa_send_stk_btn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');
            mpesaShowStatus('info', '<i class="fas fa-spinner fa-spin"></i> Creating sale and sending M-Pesa request...');

            // Step 1: First finalize the POS sale with custom_pay_1
            var posForm = $('form#add_pos_sell_form').length ? $('form#add_pos_sell_form') : $('form#edit_pos_sell_form');
            if (!posForm.length) {
                mpesaShowStatus('danger', '<i class="fas fa-times-circle"></i> POS form not found.');
                $('#mpesa_send_stk_btn').prop('disabled', false).html('<i class="fas fa-mobile-alt"></i> Send M-Pesa Request');
                return;
            }

            // Set payment method to custom_pay_1 (M-Pesa)
            var $payDropdown = $('#payment_rows_div').find('select.payment_types_dropdown').first();
            if ($payDropdown.length) {
                $payDropdown.val('custom_pay_1').trigger('change');
            }

            // Set payment amount to cover the total
            var totalPayable = parseFloat($('input#final_total_input').val()) || 0;
            var $paymentAmount = $('#payment_rows_div').find('input.payment-amount').first();
            if ($paymentAmount.length && typeof __write_number === 'function') {
                __write_number($paymentAmount, totalPayable);
                $paymentAmount.trigger('change');
            }

            // Serialize form data and submit as final
            var data = posForm.serialize();
            data = data + '&status=final';
            var url = posForm.attr('action');

            $.ajax({
                method: 'POST',
                url: url,
                data: data,
                dataType: 'json',
                success: function (result) {
                    if (result.success == 1) {
                        mpesaTransactionId = result.transaction_id;
                        mpesaShowStatus('info', '<i class="fas fa-spinner fa-spin"></i> Sale created. Sending STK Push to ' + phone + '...');

                        // Step 2: Initiate STK Push
                        $.ajax({
                            url: '/mpesa/pay',
                            method: 'POST',
                            data: {
                                transaction_id: mpesaTransactionId,
                                phone: phone,
                                amount: amount,
                                _token: $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').first().val()
                            },
                            dataType: 'json',
                            success: function (stkResponse) {
                                if (stkResponse.success) {
                                    mpesaShowStatus('info', '<i class="fas fa-spinner fa-spin"></i> PIN prompt sent to customer\'s phone. Waiting for payment...');
                                    mpesaStartPolling();
                                } else {
                                    mpesaShowStatus('warning', '<i class="fas fa-exclamation-circle"></i> ' + (stkResponse.message || 'STK Push failed.') + ' The sale has been recorded. You can collect payment manually.');
                                    $('#mpesa_send_stk_btn').prop('disabled', false).html('<i class="fas fa-redo"></i> Retry STK Push');
                                    $('#mpesa_cancel_stk_btn').text('Close');
                                }
                            },
                            error: function (xhr) {
                                var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'STK Push request failed.';
                                mpesaShowStatus('warning', '<i class="fas fa-exclamation-circle"></i> ' + msg + ' Sale has been recorded.');
                                $('#mpesa_send_stk_btn').prop('disabled', false).html('<i class="fas fa-redo"></i> Retry STK Push');
                                $('#mpesa_cancel_stk_btn').text('Close');
                            }
                        });

                        // Print receipt if enabled
                        if (result.receipt && result.receipt.is_enabled) {
                            if (typeof pos_print === 'function') {
                                pos_print(result.receipt);
                            }
                        }
                    } else {
                        mpesaShowStatus('danger', '<i class="fas fa-times-circle"></i> ' + (result.msg || 'Failed to create sale.'));
                        $('#mpesa_send_stk_btn').prop('disabled', false).html('<i class="fas fa-mobile-alt"></i> Send M-Pesa Request');
                    }
                },
                error: function (xhr) {
                    var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Sale creation failed.';
                    mpesaShowStatus('danger', '<i class="fas fa-times-circle"></i> ' + msg);
                    $('#mpesa_send_stk_btn').prop('disabled', false).html('<i class="fas fa-mobile-alt"></i> Send M-Pesa Request');
                }
            });
        });

        // -----------------------------------------------------------------------
        // Poll for STK Push status
        // -----------------------------------------------------------------------
        function mpesaStartPolling() {
            mpesaStopPolling();

            mpesaPollTimer = setInterval(function () {
                if (!mpesaTransactionId) return;

                $.getJSON('/mpesa/status/' + mpesaTransactionId, function (data) {
                    if (data.status === 'completed') {
                        mpesaStopPolling();
                        mpesaShowStatus('success',
                            '<i class="fas fa-check-circle"></i> <strong>Payment received!</strong> ' +
                            'Receipt: <strong>' + (data.receipt_number || 'N/A') + '</strong> — KES ' + data.amount
                        );
                        $('#mpesa_send_stk_btn').hide();
                        $('#mpesa_cancel_stk_btn').text('Done').removeClass('btn-default').addClass('btn-success');

                        // Reset POS form after 2 seconds
                        setTimeout(function () {
                            $('#mpesaPaymentModal').modal('hide');
                            if (typeof reset_pos_form === 'function') {
                                reset_pos_form();
                            }
                        }, 3000);

                    } else if (data.status === 'failed') {
                        mpesaStopPolling();
                        mpesaShowStatus('danger',
                            '<i class="fas fa-times-circle"></i> Payment failed: ' +
                            (data.result_desc || 'Unknown error') + '. You can retry.'
                        );
                        $('#mpesa_send_stk_btn').prop('disabled', false).html('<i class="fas fa-redo"></i> Retry STK Push');

                    } else if (data.status === 'cancelled') {
                        mpesaStopPolling();
                        mpesaShowStatus('warning',
                            '<i class="fas fa-ban"></i> Customer cancelled. You can retry.'
                        );
                        $('#mpesa_send_stk_btn').prop('disabled', false).html('<i class="fas fa-redo"></i> Retry STK Push');
                    }
                    // 'pending' — keep polling
                });
            }, 3000);

            // Timeout after 90 seconds
            mpesaPollTimeout = setTimeout(function () {
                mpesaStopPolling();
                mpesaShowStatus('warning',
                    '<i class="fas fa-clock"></i> Request timed out. Check with the customer and retry.'
                );
                $('#mpesa_send_stk_btn').prop('disabled', false).html('<i class="fas fa-redo"></i> Retry STK Push');
            }, 90000);
        }

        function mpesaStopPolling() {
            if (mpesaPollTimer) clearInterval(mpesaPollTimer);
            if (mpesaPollTimeout) clearTimeout(mpesaPollTimeout);
            mpesaPollTimer = null;
            mpesaPollTimeout = null;
        }

        // -----------------------------------------------------------------------
        // Cancel button — stop polling when modal is closed
        // -----------------------------------------------------------------------
        $('#mpesaPaymentModal').on('hidden.bs.modal', function () {
            mpesaStopPolling();
        });

        // -----------------------------------------------------------------------
        // Helpers
        // -----------------------------------------------------------------------
        function mpesaShowStatus(type, html) {
            var $area = $('#mpesa_stk_status_area');
            var $alert = $('#mpesa_stk_alert');
            $area.show();
            $alert.removeClass('alert-info alert-success alert-danger alert-warning')
                .addClass('alert-' + type)
                .html(html);
        }
    });
</script>