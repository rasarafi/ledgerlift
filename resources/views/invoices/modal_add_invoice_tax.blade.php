<script>
    $(function () {

        // Select2 for all select inputs
        $(".simple-select").select2();

        $('#invoice_tax_submit').click(function () {
            tax_rate_id = $('#tax_rate_id').val();
            if ('0' == tax_rate_id) return;
            show_loader(); // Show spinner
            $.post("{{ url('invoices/ajax/save_invoice_tax_rate') }}", {
                    invoice_id: @json($invoiceId),
                    tax_rate_id: tax_rate_id,
                    include_item_tax: $('#include_item_tax').val()
                },
                function (data) {
                    var response = json_parse(data, {{ (int) config('app.debug') }});
                    if (response.success === 1) {
                        window.location = "{{ url('invoices/view') }}/" + @json($invoiceId);
                    }
                    // close_loader(); No error returned (show go to wiki if not success after 10s)  Todo: else // The validation was not successful
                });
        });
    });
</script>

<div id="add-invoice-tax" class="modal modal-lg" role="dialog" aria-labelledby="add-invoice-tax" aria-hidden="true">
    <form class="modal-content">
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal"><i class="fa fa-close"></i></button>
            <h4 class="panel-title">{!! __('ip.add_invoice_tax') !!}</h4>
        </div>
        <div class="modal-body">

            <div class="form-group">
                <label for="tax_rate_id">{!! __('ip.invoice_tax_rate') !!}: </label>
                <select name="tax_rate_id" id="tax_rate_id" class="form-control simple-select">
                    <option value="0">{!! __('ip.none') !!}</option>
                    @foreach ($taxRates as $taxRate)
                        <option value="{{ $taxRate->tax_rate_id }}">
                            {!! format_amount($taxRate->tax_rate_percent).'% - '.e($taxRate->tax_rate_name) !!}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label for="include_item_tax">{!! __('ip.tax_rate_placement') !!}</label>
                <select name="include_item_tax" id="include_item_tax" class="form-control simple-select">
                    <option value="0">{!! __('ip.apply_before_item_tax') !!}</option>
                    <option value="1">{!! __('ip.apply_after_item_tax') !!}</option>
                </select>
            </div>

        </div>

        <div class="modal-footer">
            <div class="btn-group">
                <button class="btn btn-success" id="invoice_tax_submit" type="button">
                    <i class="fa fa-check"></i> {!! __('ip.submit') !!}
                </button>
                <button class="btn btn-danger" type="button" data-dismiss="modal">
                    <i class="fa fa-times"></i> {!! __('ip.cancel') !!}
                </button>
            </div>
        </div>

    </form>

</div>
