<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\TermsAndConditionRequest;
use App\Models\TermsAndCondition;
use App\Support\MasterCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class TermsAndConditionController extends BaseController
{
    public function __construct()
    {
        $this->middleware('permission:view terms_and_conditions')->only(['index']);
        $this->middleware('permission:create terms_and_conditions')->only(['store']);
        $this->middleware('permission:edit terms_and_conditions')->only(['update', 'setDefault']);
        $this->middleware('permission:delete terms_and_conditions')->only(['destroy']);
    }

    public function index(): JsonResponse
    {
        $templates = MasterCache::remember('masters.terms_and_conditions', fn (): array => TermsAndCondition::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(fn (TermsAndCondition $template): array => $this->formatTemplate($template))
            ->all());

        return $this->successResponse($templates);
    }

    public function store(TermsAndConditionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['is_active'] = true;

        if (! TermsAndCondition::query()->exists()) {
            $data['is_default'] = true;
        } elseif (! isset($data['is_default'])) {
            $data['is_default'] = false;
        }

        $template = TermsAndCondition::create($data);

        if ($data['is_default'] ?? false) {
            $this->setDefaultTemplate($template);
            $template->refresh();
        }

        MasterCache::forget('masters.terms_and_conditions');

        return $this->successResponse(
            $this->formatTemplate($template),
            'Terms & conditions template created successfully.',
            201
        );
    }

    public function update(TermsAndConditionRequest $request, string $id): JsonResponse
    {
        $template = TermsAndCondition::query()->findOrFail($id);
        $data = $request->validated();

        if (($data['is_default'] ?? false) === true) {
            DB::transaction(function () use ($template, $data): void {
                TermsAndCondition::query()
                    ->where('id', '!=', $template->id)
                    ->update(['is_default' => false]);

                $template->update(array_merge($data, ['is_default' => true]));
            });

            $template->refresh();
        } else {
            unset($data['is_default']);
            $template->update($data);
        }

        MasterCache::forget('masters.terms_and_conditions');

        return $this->successResponse(
            $this->formatTemplate($template),
            'Terms & conditions template updated successfully.'
        );
    }

    public function setDefault(string $id): JsonResponse
    {
        $template = TermsAndCondition::query()->findOrFail($id);
        $this->setDefaultTemplate($template);
        $template->refresh();
        MasterCache::forget('masters.terms_and_conditions');

        return $this->successResponse(
            $this->formatTemplate($template),
            'Default terms & conditions template updated successfully.'
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $template = TermsAndCondition::query()->findOrFail($id);

        if ($template->is_default) {
            TermsAndCondition::query()
                ->where('is_active', true)
                ->where('id', '!=', $template->id)
                ->orderBy('name')
                ->first()
                ?->update(['is_default' => true]);
        }

        $template->delete();
        MasterCache::forget('masters.terms_and_conditions');

        return $this->successResponse(null, 'Terms & conditions template deleted successfully.');
    }

    private function setDefaultTemplate(TermsAndCondition $template): void
    {
        DB::transaction(function () use ($template): void {
            TermsAndCondition::query()
                ->where('id', '!=', $template->id)
                ->update(['is_default' => false]);

            $template->update(['is_default' => true, 'is_active' => true]);
        });
    }

    private function formatTemplate(TermsAndCondition $template): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'content' => $template->content,
            'is_default' => $template->is_default,
            'is_active' => $template->is_active,
            'created_at' => $template->created_at,
            'updated_at' => $template->updated_at,
        ];
    }
}
