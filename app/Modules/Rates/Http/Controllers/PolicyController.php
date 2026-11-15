<?php

declare(strict_types=1);

namespace App\Modules\Rates\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Rates\Actions\SavePolicy;
use App\Modules\Rates\DTOs\CancellationPolicyData;
use App\Modules\Rates\DTOs\DepositPolicyData;
use App\Modules\Rates\DTOs\PoliciesData;
use App\Modules\Rates\DTOs\PoliciesMetaData;
use App\Modules\Rates\Enums\BalanceDue;
use App\Modules\Rates\Enums\DepositDue;
use App\Modules\Rates\Enums\DepositType;
use App\Modules\Rates\Enums\PenaltyType;
use App\Modules\Rates\Http\Requests\CancellationPolicyRequest;
use App\Modules\Rates\Http\Requests\DepositPolicyRequest;
use App\Modules\Rates\Models\CancellationPolicy;
use App\Modules\Rates\Models\DepositPolicy;
use App\Modules\Rates\Services\PolicyWording;
use App\Support\Http\Bff;
use App\Support\Http\DTOs\OptionData;
use App\Support\PropertyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * /bff/settings/policies, /cancellation-policies, /deposit-policies (RTE-07).
 */
class PolicyController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly PropertyContext $context,
        private readonly PolicyWording $wording,
    ) {}

    public static function middleware(): array
    {
        return [new Middleware('can:'.Permission::SettingsPolicies->value)];
    }

    public function index(): JsonResponse
    {
        $property = $this->context->require();

        $cancellation = CancellationPolicy::forProperty($property)->with(CancellationPolicy::stampRelations())
            ->withCount('ratePlans')->orderByDesc('is_active')->orderBy('name')->get();
        $deposit = DepositPolicy::forProperty($property)->with(DepositPolicy::stampRelations())
            ->withCount('ratePlans')->orderByDesc('is_active')->orderBy('name')->get();

        return Bff::respond(
            new PoliciesData(
                $cancellation->map(fn (CancellationPolicy $policy): CancellationPolicyData => $this->cancellationData($policy))->all(),
                $deposit->map(fn (DepositPolicy $policy): DepositPolicyData => $this->depositData($policy))->all(),
            ),
            new PoliciesMetaData(
                $property->currency,
                OptionData::fromEnum(PenaltyType::class),
                OptionData::fromEnum(DepositType::class),
                OptionData::fromEnum(DepositDue::class),
                OptionData::fromEnum(BalanceDue::class),
            ),
        );
    }

    public function storeCancellation(CancellationPolicyRequest $request, SavePolicy $save): JsonResponse
    {
        $policy = $save->cancellation($this->actor($request), $this->context->require(), $request->normalized());

        return Bff::respond($this->cancellationData($policy), status: 201);
    }

    public function updateCancellation(CancellationPolicyRequest $request, CancellationPolicy $cancellationPolicy, SavePolicy $save): JsonResponse
    {
        $this->context->ensureOwns($cancellationPolicy);

        return Bff::respond($this->cancellationData(
            $save->cancellation($this->actor($request), $this->context->require(), $request->normalized(), $cancellationPolicy),
        ));
    }

    public function storeDeposit(DepositPolicyRequest $request, SavePolicy $save): JsonResponse
    {
        $policy = $save->deposit($this->actor($request), $this->context->require(), $request->normalized());

        return Bff::respond($this->depositData($policy), status: 201);
    }

    public function updateDeposit(DepositPolicyRequest $request, DepositPolicy $depositPolicy, SavePolicy $save): JsonResponse
    {
        $this->context->ensureOwns($depositPolicy);

        return Bff::respond($this->depositData(
            $save->deposit($this->actor($request), $this->context->require(), $request->normalized(), $depositPolicy),
        ));
    }

    private function cancellationData(CancellationPolicy $policy): CancellationPolicyData
    {
        $policy->loadMissing(CancellationPolicy::stampRelations());

        return CancellationPolicyData::fromModel($policy, $this->wording->cancellation($policy, $this->context->require()->currency));
    }

    private function depositData(DepositPolicy $policy): DepositPolicyData
    {
        $policy->loadMissing(DepositPolicy::stampRelations());

        return DepositPolicyData::fromModel($policy, $this->wording->deposit($policy, $this->context->require()->currency));
    }
}
