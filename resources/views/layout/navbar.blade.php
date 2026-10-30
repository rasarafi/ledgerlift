@use('App\Support\Strangler')
@use('Illuminate\Support\Facades\Auth')
<nav class="navbar navbar-inverse" role="navigation">
    <div class="container-fluid">
        <div class="navbar-header">
            <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#ip-navbar-collapse">
                <span class="sr-only">Toggle navigation</span>
                {!! __('ip.menu') !!} &nbsp; <i class="fa fa-bars"></i>
            </button>
        </div>

        <div class="collapse navbar-collapse" id="ip-navbar-collapse">
            <ul class="nav navbar-nav">
                <li><a href="{{ Strangler::url('dashboard') }}" class="hidden-md">{!! __('ip.dashboard') !!}</a>
                    <a href="{{ Strangler::url('dashboard') }}" class="visible-md-inline-block"><i class="fa fa-dashboard"></i></a>
                </li>

                <li class="dropdown">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                        <i class="fa fa-caret-down"></i> &nbsp;
                        <span class="hidden-md">{!! __('ip.clients') !!}</span>
                        <i class="visible-md-inline fa fa-users"></i>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a href="{{ Strangler::url('clients/form') }}">{!! __('ip.add_client') !!}</a></li>
                        <li><a href="{{ Strangler::url('clients/index') }}">{!! __('ip.view_clients') !!}</a></li>
                    </ul>
                </li>

                <li class="dropdown">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                        <i class="fa fa-caret-down"></i> &nbsp;
                        <span class="hidden-md">{!! __('ip.quotes') !!}</span>
                        <i class="visible-md-inline fa fa-file"></i>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a href="{{ Strangler::url('quotes/index') }}">{!! __('ip.create_quote') !!}</a></li>
                        <li><a href="{{ Strangler::url('quotes/index') }}">{!! __('ip.view_quotes') !!}</a></li>
                    </ul>
                </li>

                <li class="dropdown">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                        <i class="fa fa-caret-down"></i> &nbsp;
                        <span class="hidden-md">{!! __('ip.invoices') !!}</span>
                        <i class="visible-md-inline fa fa-file-text"></i>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a href="#" class="create-invoice">{!! __('ip.create_invoice') !!}</a></li>
                        <li><a href="{{ Strangler::url('invoices/index') }}">{!! __('ip.view_invoices') !!}</a></li>
                        <li><a href="{{ Strangler::legacy('invoices/recurring/index') }}">{!! __('ip.view_recurring_invoices') !!}</a></li>
                    </ul>
                </li>

                <li class="dropdown">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                        <i class="fa fa-caret-down"></i> &nbsp;
                        <span class="hidden-md">{!! __('ip.payments') !!}</span>
                        <i class="visible-md-inline fa fa-credit-card"></i>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a href="{{ Strangler::url('payments/form') }}">{!! __('ip.enter_payment') !!}</a></li>
                        <li><a href="{{ Strangler::url('payments/index') }}">{!! __('ip.view_payments') !!}</a></li>
                        <li><a href="{{ Strangler::legacy('payments/online_logs') }}">{!! __('ip.view_payment_logs') !!}</a></li>
                    </ul>
                </li>

                <li class="dropdown">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                        <i class="fa fa-caret-down"></i> &nbsp;
                        <span class="hidden-md">{!! __('ip.products') !!}</span>
                        <i class="visible-md-inline fa fa-database"></i>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a href="{{ Strangler::url('products/form') }}">{!! __('ip.create_product') !!}</a></li>
                        <li><a href="{{ Strangler::url('products/index') }}">{!! __('ip.view_products') !!}</a></li>
                        <li><a href="{{ Strangler::url('families/index') }}">{!! __('ip.view_product_families') !!}</a></li>
                        <li><a href="{{ Strangler::url('units/index') }}">{!! __('ip.view_product_units') !!}</a></li>
                    </ul>
                </li>

                <li class="dropdown{{ get_setting('projects_enabled') == 1 ? '' : ' hidden' }}">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                        <i class="fa fa-caret-down"></i> &nbsp;
                        <span class="hidden-md">{!! __('ip.tasks') !!}</span>
                        <i class="visible-md-inline fa fa-check-square-o"></i>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a href="{{ Strangler::url('tasks/form') }}">{!! __('ip.create_task') !!}</a></li>
                        <li><a href="{{ Strangler::url('tasks/index') }}">{!! __('ip.view_tasks') !!}</a></li>
                        <li role="separator" class="divider"></li>
                        <li><a href="{{ Strangler::url('projects/form') }}">{!! __('ip.create_project') !!}</a></li>
                        <li><a href="{{ Strangler::url('projects/index') }}">{!! __('ip.view_projects') !!}</a></li>
                    </ul>
                </li>

                <li class="dropdown">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                        <i class="fa fa-caret-down"></i> &nbsp;
                        <span class="hidden-md">{!! __('ip.reports') !!}</span>
                        <i class="visible-md-inline fa fa-bar-chart"></i>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a href="{{ Strangler::url('reports/invoice_aging') }}">{!! __('ip.invoice_aging') !!}</a></li>
                        <li><a href="{{ Strangler::url('reports/payment_history') }}">{!! __('ip.payment_history') !!}</a></li>
                        <li><a href="{{ Strangler::url('reports/sales_by_client') }}">{!! __('ip.sales_by_client') !!}</a></li>
                        <li><a href="{{ Strangler::url('reports/sales_by_year') }}">{!! __('ip.sales_by_date') !!}</a></li>
                        <li><a href="{{ Strangler::url('reports/invoices_per_client') }}">{!! __('ip.invoices_per_client') !!}</a></li>
                    </ul>
                </li>

            </ul>

            @if (isset($filterDisplay) && $filterDisplay == true)
                @include('filter.jquery_filter')
                <form class="navbar-form navbar-left" role="search" onsubmit="return false;">
                    <div class="form-group">
                        <input id="filter" type="text" class="search-query form-control"
                               placeholder="{{ $filterPlaceholder }}">
                    </div>
                </form>
            @endif

            <ul class="nav navbar-nav navbar-right">
                <li>
                    <a href="https://wiki.invoiceplane.com/" target="_blank"
                       class="tip icon" title="{!! __('ip.documentation') !!}"
                       data-placement="bottom">
                        <i class="fa fa-question-circle"></i>
                        <span class="visible-xs">&nbsp;{!! __('ip.documentation') !!}</span>
                    </a>
                </li>

                <li class="dropdown">
                    <a href="#" class="tip icon dropdown-toggle" data-toggle="dropdown"
                       title="{!! __('ip.settings') !!}"
                       data-placement="bottom">
                        <i class="fa fa-cogs"></i>
                        <span class="visible-xs">&nbsp;{!! __('ip.settings') !!}</span>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a href="{{ Strangler::url('custom_fields/index') }}">{!! __('ip.custom_fields') !!}</a></li>
                        <li><a href="{{ Strangler::url('email_templates/index') }}">{!! __('ip.email_templates') !!}</a></li>
                        <li><a href="{{ Strangler::url('invoice_groups/index') }}">{!! __('ip.invoice_groups') !!}</a></li>
                        <li><a href="{{ Strangler::legacy('invoices/archive') }}">{!! __('ip.invoice_archive') !!}</a></li>
                        <li><a href="{{ Strangler::url('payment_methods/index') }}">{!! __('ip.payment_methods') !!}</a></li>
                        <li><a href="{{ Strangler::url('tax_rates/index') }}">{!! __('ip.tax_rates') !!}</a></li>
                        <li><a href="{{ Strangler::url('users/index') }}">{!! __('ip.user_accounts') !!}</a></li>
                        <li class="divider hidden-xs hidden-sm"></li>
                        <li><a href="{{ Strangler::url('settings') }}">{!! __('ip.system_settings') !!}</a></li>
                        <li><a href="{{ Strangler::url('import') }}">{!! __('ip.import_data') !!}</a></li>
                    </ul>
                </li>
                <li>
                    <a href="{{ Strangler::url('users/form/'.Auth::id()) }}"
                       class="tip icon" data-placement="bottom"
                       title="{{ Auth::user()->user_name }}{{ Auth::user()->user_company ? ' ('.Auth::user()->user_company.')' : '' }}">
                        <i class="fa fa-user"></i>
                        <span class="visible-xs">&nbsp;{{ Auth::user()->user_name }}{{ Auth::user()->user_company ? ' ('.Auth::user()->user_company.')' : '' }}</span>
                    </a>
                </li>
                <li>
                    <a href="{{ Strangler::url('sessions/logout') }}"
                       class="tip icon logout" data-placement="bottom"
                       title="{!! __('ip.logout') !!}">
                        <i class="fa fa-power-off"></i>
                        <span class="visible-xs">&nbsp;{!! __('ip.logout') !!}</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>
