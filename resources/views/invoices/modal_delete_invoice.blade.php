<div id="delete-invoice" class="modal modal-lg" role="dialog" aria-labelledby="delete-invoice" aria-hidden="true">

    <div class="modal-content">
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal"><i class="fa fa-close"></i></button>
            <h4 class="panel-title">{!! __('ip.delete_invoice') !!}</h4>
        </div>
        <div class="modal-body">

            <div class="alert alert-danger">{!! __('ip.delete_invoice_warning') !!}</div>

        </div>
        <div class="modal-footer">

            <form action="{{ url('invoices/delete/'.$invoice->invoice_id) }}"
                  method="POST">
                <input type="hidden" name="_ip_csrf" value="{{ csrf_token() }}">

                <div class="btn-group">
                    <button type="submit" class="btn btn-danger">
                        <i class="fa fa-trash-o fa-margin"></i> {!! __('ip.confirm_deletion') !!}
                    </button>
                    <a href="#" class="btn btn-default" data-dismiss="modal">
                        <i class="fa fa-times"></i> {!! __('ip.cancel') !!}
                    </a>
                </div>
            </form>

        </div>
    </div>

</div>
