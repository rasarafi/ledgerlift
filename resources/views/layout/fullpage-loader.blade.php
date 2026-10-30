<div id="fullpage-loader" style="display: none">
    <div class="loader-content">
        <i id="loader-icon" class="fa fa-cog fa-spin"></i>
        <div id="loader-error" class="loader-error-message" style="display: none">
            <div class="alert alert-danger">
                <img id="loader-error-icon" src="{{ url('assets/core/img/loader-error.svg') }}"
                     alt="{{ __('ip.loading_error') }}">
                <strong><i class="fa fa-exclamation-triangle"></i> {{ __('ip.loading_error') }}</strong>
                <div class="loader-error-actions">
                    <a href="https://wiki.invoiceplane.com/{{ __('ip.cldr') }}/1.0/general/faq"
                       class="btn btn-primary btn-sm" target="_blank" rel="noopener noreferrer">
                        <i class="fa fa-support"></i> {{ __('ip.loading_error_help') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
    <div class="text-right">
        <button type="button" class="fullpage-loader-close btn btn-link tip" aria-label="{{ __('ip.close') }}"
                title="{{ __('ip.close') }}" data-placement="left">
            <span aria-hidden="true"><i class="fa fa-close"></i></span>
        </button>
    </div>
</div>
