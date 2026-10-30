<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesLegacyCrud;
use App\Http\Requests\UnitRequest;
use App\Models\Unit;

/** modules/units/controllers/Units.php */
class UnitController extends Controller
{
    use HandlesLegacyCrud;

    protected string $module = 'units';

    /** @var class-string<Unit> */
    protected string $model = Unit::class;

    protected string $formRequest = UnitRequest::class;

    protected ?string $orderBy = 'unit_name';

    protected ?array $uniqueGuard = ['column' => 'unit_name', 'requires' => ['unit_name', 'unit_name_plrl'], 'message' => 'unit_already_exists'];
}
