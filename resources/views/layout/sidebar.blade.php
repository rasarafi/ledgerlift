@use('App\Support\Strangler')
<div class="sidebar hidden-xs">
    <ul>
        <li>
            <a href="{{ Strangler::url('clients/index') }}" title="{{ __('ip.clients') }}"
               class="tip" data-placement="right">
                <i class="fa fa-users"></i>
            </a>
        </li>
        <li>
            <a href="{{ Strangler::url('quotes/index') }}" title="{{ __('ip.quotes') }}"
               class="tip" data-placement="right">
                <i class="fa fa-file"></i>
            </a>
        </li>
        <li>
            <a href="{{ Strangler::url('invoices/index') }}" title="{{ __('ip.invoices') }}"
               class="tip" data-placement="right">
                <i class="fa fa-file-text"></i>
            </a>
        </li>
        <li>
            <a href="{{ Strangler::url('payments/index') }}" title="{{ __('ip.payments') }}"
               class="tip" data-placement="right">
                <i class="fa fa-money"></i>
            </a>
        </li>
        <li>
            <a href="{{ Strangler::url('products/index') }}" title="{{ __('ip.products') }}"
               class="tip" data-placement="right">
                <i class="fa fa-database"></i>
            </a>
        </li>
        @if (get_setting('projects_enabled') == 1)
            <li>
                <a href="{{ Strangler::url('tasks/index') }}" title="{{ __('ip.tasks') }}"
                   class="tip" data-placement="right">
                    <i class="fa fa-check-square-o"></i>
                </a>
            </li>
        @endif
        <li>
            <a href="{{ Strangler::url('settings') }}" title="{{ __('ip.system_settings') }}"
               class="tip" data-placement="right">
                <i class="fa fa-cogs"></i>
            </a>
        </li>
    </ul>
</div>
