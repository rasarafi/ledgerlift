<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesLegacyCrud;
use App\Http\Requests\FamilyRequest;
use App\Models\Family;

/** modules/families/controllers/Families.php */
class FamilyController extends Controller
{
    use HandlesLegacyCrud;

    protected string $module = 'families';

    /** @var class-string<Family> */
    protected string $model = Family::class;

    protected string $formRequest = FamilyRequest::class;

    protected ?string $orderBy = 'family_name';

    /** @var array{column: string, requires: list<string>, message: string}|null */
    protected ?array $uniqueGuard = ['column' => 'family_name', 'requires' => ['family_name'], 'message' => 'family_already_exists'];

    /** @return array<string, mixed> */
    protected function indexData(): array
    {
        return ['filterDisplay' => true, 'filterPlaceholder' => __('ip.filter_families'), 'filterMethod' => 'filter_families'];
    }
}
