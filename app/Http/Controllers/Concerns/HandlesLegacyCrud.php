<?php

namespace App\Http\Controllers\Concerns;

use App\Http\Requests\LegacyFormRequest;
use App\Http\Responses\LegacyRedirect;
use App\Support\LegacyPager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;

/**
 * index, form and delete of a reference-data module, as MY_Model, Response_Model and the
 * module controllers do them. A controller names its module (the route prefix and the
 * `<module>.index` / `<module>.form` views), its model and its request.
 *
 * Properties the using controller declares:
 *
 * @property string $module
 * @property class-string<Model> $model
 * @property class-string<LegacyFormRequest> $formRequest
 * @property string|null $orderBy Column the list is sorted by; null leaves it in table order.
 * @property array{column: string, requires: list<string>, message: string}|null $uniqueGuard
 *                                                                                            The duplicate check of a new record: the unique column, the fields that must be non-empty
 *                                                                                            for it to run, and the lang key of its message. Null: no check, and no `is_update` field.
 */
trait HandlesLegacyCrud
{
    /** Mdl_x::paginate() with `default_list_limit`. */
    public function index(string $offset = '0'): View
    {
        $query = $this->query();
        $total = (clone $query)->count();
        $pager = new LegacyPager((int) $offset, LegacyPager::defaultPerPage(), $total);

        return view($this->viewName('index'), [
            'rows' => $query->offset($pager->offset)->limit($pager->perPage)->get(),
            'pager' => $pager,
        ] + $this->indexData());
    }

    public function form(Request $request, ?string $id = null): View|RedirectResponse
    {
        if ($request->post('btn_cancel')) {
            return LegacyRedirect::to($request, url($this->module));
        }

        if (($duplicate = $this->duplicateMessage($request)) !== null) {
            return LegacyRedirect::to($request, url($this->module.'/form'))->with('alert_error', $duplicate);
        }

        $form = $request->post();
        $errors = null;

        // CI form_validation only has rules to run on a POST.
        if ($request->isMethod('POST')) {
            $validator = app($this->formRequest)->legacyValidator();

            if ($validator->passes()) {
                $message = $this->save($request, $id);

                return LegacyRedirect::to($request, url($this->module))->with('alert_success', $message);
            }

            $errors = $validator->errors();
        }

        if ($id && ! $request->post('btn_submit')) {
            // MY_Model::prep_form() only loads the record when nothing was posted.
            $row = ($form === [] ? $this->query()->where($this->keyName(), $id)->first() : null) ?? abort(404);

            $form = $row->getAttributes() + ($this->uniqueGuard !== null ? ['is_update' => true] : []);
        } elseif (! $id) {
            $form = $this->newFormValues($form);
        }

        return view($this->viewName('form'), ['form' => $form] + $this->formData())->withErrors($errors ?? []);
    }

    /**
     * `<module>.<name>`. A concatenation is not a view-string to PHPStan, which only knows the
     * names that appear as literals; these resolve per controller.
     *
     * @return view-string
     */
    private function viewName(string $name): string
    {
        /** @var view-string $view */
        $view = sprintf('%s.%s', $this->module, $name);

        return $view;
    }

    /** Response_Model::delete() */
    public function delete(Request $request, string $id): RedirectResponse
    {
        $this->model::query()->where($this->keyName(), $id)->delete();

        return LegacyRedirect::to($request, url($this->module))
            ->with('alert_success', __('ip.record_successfully_deleted'));
    }

    /** @return Builder<*> */
    protected function query(): Builder
    {
        $query = $this->model::query();

        return $this->orderBy === null ? $query : $query->orderBy($this->orderBy);
    }

    /** @return array<string, mixed> */
    protected function indexData(): array
    {
        return [];
    }

    /**
     * What the form lists besides the record, such as the options of its select boxes.
     *
     * @return array<string, mixed>
     */
    protected function formData(): array
    {
        return [];
    }

    /**
     * What a new record's form shows after the posted values: legacy sets defaults here for
     * a form it re-renders after a failed save too.
     *
     * @param  array<string, mixed>  $form
     * @return array<string, mixed>
     */
    protected function newFormValues(array $form): array
    {
        return $form;
    }

    /**
     * The persisted columns after the controller's own changes.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalised(array $data): array
    {
        return $data;
    }

    /**
     * Response_Model::save(): an update of a missing id changes nothing and still says so.
     */
    private function save(Request $request, ?string $id): string
    {
        // MY_Model::db_array(): only the posted keys that appear in validation_rules().
        $data = $this->normalised(Arr::only($request->post(), array_keys(app($this->formRequest)->rules())));

        if ($id) {
            $this->model::query()->where($this->keyName(), $id)->update($data);

            return __('ip.record_successfully_updated');
        }

        $this->model::query()->insert($data);

        return __('ip.record_successfully_created');
    }

    /**
     * Families, units and payment methods refuse a new record whose name exists. The `is_update`
     * field marks an edit; PHP's loose comparisons are legacy's (an absent field counts as 0).
     */
    private function duplicateMessage(Request $request): ?string
    {
        $guard = $this->uniqueGuard;

        if ($guard === null || $request->post('is_update') != 0) {
            return null;
        }

        foreach ($guard['requires'] as $field) {
            if ($request->post($field) == '') {
                return null;
            }
        }

        $exists = $this->model::query()->where($guard['column'], $request->post($guard['column']))->exists();

        return $exists ? (string) __('ip.'.$guard['message']) : null;
    }

    private function keyName(): string
    {
        return (new $this->model)->getKeyName();
    }
}
