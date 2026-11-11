<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSystemSettingRequest;
use App\Services\SystemSettingService;
use App\Services\SystemSettingAssetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminSystemSettingController extends Controller
{
    public function __construct(
        private readonly SystemSettingService $settings,
        private readonly SystemSettingAssetService $assets,
    ) {}

    public function index(Request $request): View
    {
        $groups = $this->settings->groups();
        $selectedGroup = (string) $request->query('group', array_key_first($groups));
        abort_unless(array_key_exists($selectedGroup, $groups), 404);

        return view('admin.settings.index', [
            'groups' => $groups,
            'selectedGroup' => $selectedGroup,
            'definitions' => $this->settings->definitionsForGroup($selectedGroup),
            'values' => $this->settings->all(),
            'assetUrls' => collect($this->settings->definitionsForGroup($selectedGroup))
                ->filter(fn (array $definition): bool => $definition['type'] === 'image')
                ->mapWithKeys(fn (array $definition, string $key): array => [
                    $key => $this->assets->publicUrl($key, $this->settings->get($key)),
                ])
                ->all(),
        ]);
    }

    public function update(UpdateSystemSettingRequest $request, string $group): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();
        $removeKeys = array_keys(array_filter($validated['remove_assets'] ?? []));

        $this->assets->updateGroup(
            $request->user(),
            $group,
            $validated['settings'],
            $request->file('assets', []),
            $removeKeys,
            $request,
        );

        $message = 'Đã cập nhật nhóm cài đặt '.$this->settings->groups()[$group]['label'].'.';

        if ($request->expectsJson()) {
            $assetUrls = collect($this->settings->definitionsForGroup($group))
                ->filter(fn (array $definition): bool => $definition['type'] === 'image')
                ->mapWithKeys(fn (array $definition, string $key): array => [
                    $key => $this->assets->publicUrl($key, $this->settings->get($key)),
                ])
                ->all();

            return response()->json([
                'message' => $message,
                'asset_urls' => $assetUrls,
            ]);
        }

        return redirect()
            ->route('admin.settings.index', ['group' => $group])
            ->with('success', $message);
    }
}
