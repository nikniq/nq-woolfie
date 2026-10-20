<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCampaignResultRequest;
use App\Models\Achievement;
use App\Models\CampaignResult;
use Illuminate\Http\JsonResponse;

class CampaignResultController extends Controller
{
    public function store(StoreCampaignResultRequest $request): JsonResponse
    {
        $data = $request->validated();
        if ($user = $request->user()) {
            $data['user_id'] = $user->id;
            $data['player_name'] = $user->name;
        }
        $result = CampaignResult::updateOrCreate(['campaign' => $data['campaign']], $data);
        $earned = Achievement::awardFor($result);
        $catalogue = Achievement::catalogue();

        return response()->json([
            'data' => $result,
            'earned' => array_map(fn ($k) => ['key' => $k, 'title' => $catalogue[$k]['title'], 'icon' => $catalogue[$k]['icon'], 'description' => $catalogue[$k]['description']], $earned),
            'held' => Achievement::where('player_name', $result->player_name)->pluck('key')->values(),
        ], 201);
    }
}
