<div class="payment_details_div @if( $payment_line['method'] !== 'card' ) {{ 'hide' }} @endif" data-type="card" >
	<div class="col-md-4">
		<div class="form-group">
			{!! Form::label("card_number_$row_index", __('lang_v1.card_no')) !!}
			{!! Form::text("payment[$row_index][card_number]", $payment_line['card_number'], ['class' => 'form-control', 'placeholder' => __('lang_v1.card_no'), 'id' => "card_number_$row_index"]); !!}
		</div>
	</div>
	<div class="col-md-4">
		<div class="form-group">
			{!! Form::label("card_holder_name_$row_index", __('lang_v1.card_holder_name')) !!}
			{!! Form::text("payment[$row_index][card_holder_name]", $payment_line['card_holder_name'], ['class' => 'form-control', 'placeholder' => __('lang_v1.card_holder_name'), 'id' => "card_holder_name_$row_index"]); !!}
		</div>
	</div>
	<div class="col-md-4">
		<div class="form-group">
			{!! Form::label("card_transaction_number_$row_index",__('lang_v1.card_transaction_no')) !!}
			{!! Form::text("payment[$row_index][card_transaction_number]", $payment_line['card_transaction_number'], ['class' => 'form-control', 'placeholder' => __('lang_v1.card_transaction_no'), 'id' => "card_transaction_number_$row_index"]); !!}
		</div>
	</div>
	<div class="clearfix"></div>
	<div class="col-md-3">
		<div class="form-group">
			{!! Form::label("card_type_$row_index", __('lang_v1.card_type')) !!}
			{!! Form::select("payment[$row_index][card_type]", ['credit' => 'Credit Card', 'debit' => 'Debit Card','visa' => 'Visa', 'master' => 'MasterCard'], $payment_line['card_type'],['class' => 'form-control', 'id' => "card_type_$row_index" ]); !!}
		</div>
	</div>
	<div class="col-md-3">
		<div class="form-group">
			{!! Form::label("card_month_$row_index", __('lang_v1.month')) !!}
			{!! Form::text("payment[$row_index][card_month]", $payment_line['card_month'], ['class' => 'form-control', 'placeholder' => __('lang_v1.month'),
			'id' => "card_month_$row_index" ]); !!}
		</div>
	</div>
	<div class="col-md-3">
		<div class="form-group">
			{!! Form::label("card_year_$row_index", __('lang_v1.year')) !!}
			{!! Form::text("payment[$row_index][card_year]", $payment_line['card_year'], ['class' => 'form-control', 'placeholder' => __('lang_v1.year'), 'id' => "card_year_$row_index" ]); !!}
		</div>
	</div>
	<div class="col-md-3">
		<div class="form-group">
			{!! Form::label("card_security_$row_index",__('lang_v1.security_code')) !!}
			{!! Form::text("payment[$row_index][card_security]", $payment_line['card_security'], ['class' => 'form-control', 'placeholder' => __('lang_v1.security_code'), 'id' => "card_security_$row_index"]); !!}
		</div>
	</div>
	<div class="clearfix"></div>
</div>
<div class="payment_details_div @if( $payment_line['method'] !== 'cheque' ) {{ 'hide' }} @endif" data-type="cheque" >
	<div class="col-md-12">
		<div class="form-group">
			{!! Form::label("cheque_number_$row_index",__('lang_v1.cheque_no')) !!}
			{!! Form::text("payment[$row_index][cheque_number]", $payment_line['cheque_number'], ['class' => 'form-control', 'placeholder' => __('lang_v1.cheque_no'), 'id' => "cheque_number_$row_index"]); !!}
		</div>
	</div>
</div>
<div class="payment_details_div @if( $payment_line['method'] !== 'bank_transfer' ) {{ 'hide' }} @endif" data-type="bank_transfer" >
	<div class="col-md-12">
		<div class="form-group">
			{!! Form::label("bank_account_number_$row_index",__('lang_v1.bank_account_number')) !!}
			{!! Form::text( "payment[$row_index][bank_account_number]", $payment_line['bank_account_number'], ['class' => 'form-control', 'placeholder' => __('lang_v1.bank_account_number'), 'id' => "bank_account_number_$row_index"]); !!}
		</div>
	</div>
</div>


{{-- =====================================================================
     custom_pay_1 = M-Pesa STK Push (inline in payment row)
     For remaining custom payments, show generic transaction number field.
====================================================================== --}}
<div class="payment_details_div @if( $payment_line['method'] !== 'custom_pay_1' ) {{ 'hide' }} @endif" data-type="custom_pay_1">
    <div class="col-md-12">
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Customer Phone Number</label>
                    <div class="input-group">
                        <span class="input-group-addon">+254</span>
                        <input type="text"
                               class="form-control mpesa-phone"
                               placeholder="7XXXXXXXX"
                               maxlength="9">
                    </div>
                    <small class="text-muted">Customer's Safaricom number — they will receive a PIN prompt.</small>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    {{-- Hidden field to store the M-Pesa receipt/confirmation number after success --}}
                    {!! Form::label("transaction_no_1_{$row_index}", 'M-Pesa Receipt No (auto-filled):') !!}
                    {!! Form::text("payment[$row_index][transaction_no_1]", $payment_line['transaction_no'], [
                        'class' => 'form-control mpesa-receipt-field',
                        'placeholder' => 'Auto-filled after payment',
                        'id' => "transaction_no_1_{$row_index}",
                        'readonly'
                    ]); !!}
                </div>
            </div>
        </div>
        <div class="row mpesa-status-row" style="display:none;">
            <div class="col-md-12">
                <div class="alert mpesa-alert" role="alert"></div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                <button type="button" class="btn btn-success btn-block mpesa-request-btn">
                    <i class="fas fa-mobile-alt"></i> Send M-Pesa Request
                </button>
            </div>
            <div class="col-md-6">
                <button type="button" class="btn btn-default btn-block mpesa-retry-btn" style="display:none;">
                    <i class="fas fa-redo"></i> Try Again
                </button>
            </div>
        </div>
    </div>
</div>

@for ($i = 2; $i < 8; $i++)
<div class="payment_details_div @if( $payment_line['method'] !== 'custom_pay_' . $i ) {{ 'hide' }} @endif" data-type="custom_pay_{{$i}}">
    <div class="col-md-12">
        <div class="form-group">
            {!! Form::label("transaction_no_{$i}_{$row_index}", __('lang_v1.transaction_no')) !!}
            {!! Form::text("payment[$row_index][transaction_no_{$i}]", $payment_line['transaction_no'], ['class' => 'form-control', 'placeholder' => __('lang_v1.transaction_no'), 'id' => "transaction_no_{$i}_{$row_index}"]); !!}
        </div>
    </div>
</div>
@endfor