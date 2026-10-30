<div class="headerbar-item pull-right">
    <div class="btn-group btn-group-sm">
@if (! isset($hideSubmitButton))
        <button id="btn-submit" name="btn_submit" class="btn btn-success ajax-loader" value="1">
            <i class="fa fa-check"></i> {!! __('ip.save') !!}
        </button>
@endif
@if (! isset($hideCancelButton))
        <button type="button" {!! empty($attributeCancel) ? 'onclick="window.history.back()"' : $attributeCancel !!} id="btn-cancel" name="btn_cancel" class="btn btn-danger ajax-loader" value="1">
            <i class="fa fa-times"></i> {!! __('ip.cancel') !!}
        </button>
@endif
    </div>
</div>
