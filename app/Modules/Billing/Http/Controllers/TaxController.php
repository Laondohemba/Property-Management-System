<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Actions\SaveTax;
use App\Modules\Billing\DTOs\TaxData;
use App\Modules\Billing\DTOs\TaxListMetaData;
use App\Modules\Billing\Enums\TaxCalculation;
use App\Modules\Billing\Http\Requests\TaxRequest;
use App\Modules\Billing\Models\Tax;
use App\Modules\Identity\Enums\Permission;
use App\Support\Http\Bff;
use App\Support\Http\DTOs\OptionData;
use App\Support\PropertyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * /bff/settings/taxes (spec §2.8): taxes and fees of the active property.
 */
class TaxController extends Controller implements HasMiddleware
{
    public function __construct(private readonly PropertyContext $context) {}

    public static function middleware(): array
    {
        return [new Middleware('can:'.Permission::SettingsTaxes->value)];
    }

    /**
     * Every tax of the property in calculation order (a short list, not paginated).
     */
    public function index(): JsonResponse
    {
        $property = $this->context->require();

        $taxes = Tax::forProperty($property)
            ->with(Tax::stampRelations())
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Bff::respond(
            $taxes->map(fn (Tax $tax): TaxData => TaxData::fromModel($tax))->all(),
            new TaxListMetaData($property->currency, OptionData::fromEnum(TaxCalculation::class)),
        );
    }

    public function store(TaxRequest $request, SaveTax $save): JsonResponse
    {
        $tax = $save->handle($this->actor($request), $this->context->require(), $request->validated());

        return Bff::respond(TaxData::fromModel($tax->load(Tax::stampRelations())), status: 201);
    }

    public function update(TaxRequest $request, Tax $tax, SaveTax $save): JsonResponse
    {
        $this->context->ensureOwns($tax);

        $tax = $save->handle($this->actor($request), $this->context->require(), $request->validated(), $tax);

        return Bff::respond(TaxData::fromModel($tax->load(Tax::stampRelations())));
    }
}
