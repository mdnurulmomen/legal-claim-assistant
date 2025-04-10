<?php

namespace App\Http\Controllers\Api\Buyer;

use App\Http\Controllers\Api\Buyer\Requests\CreateOrUpdateBuyerRequest;
use App\Http\Controllers\Api\Buyer\Resources\BuyerResource;
use App\Http\Controllers\Controller;
use App\Models\Buyer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Database\Eloquent\Builder;

class BuyerController extends Controller
{
    /**
     * Retrieve a paginated list of buyers filtered by a search text.
     *
     * @param Request $request
     * @return Response
     */
    public function buyerList(Request $request): Response
    {
        $perPage = empty($request->limit) ? 10 : $request->limit;

        $buyers = Buyer::query()
                    ->when(! empty($request->search_txt), function (Builder $query) use ($request) {
                        return $query->whereAny([
                                    'name',
                                    'email',
                                    'phone',
                                ], 'like', "%{$request->search_txt}%");
                    })
                    ->when(! empty($request->status), function($query) use ($request) {
                        return $query->where('status', $request->status);
                    })
                    ->latest('id')
                    ->paginate($perPage);

        return withSuccessResourceList(BuyerResource::collection($buyers));
    }

    /**
     * Creates a new buyer using the provided request data.
     *
     * @param CreateOrUpdateBuyerRequest $request
     * @return Response
     */
    public function storeBuyer(CreateOrUpdateBuyerRequest $request): Response
    {
        $buyer = Buyer::create($request->validated());
        return withSuccess(message: 'Buyer created successfully');
    }

    /**
     * Display the specified buyer's information.
     *
     * @param Request $request
     * @param int $buyerId
     * @return Response
     */
    public function showBuyer(Request $request, int $buyerId): Response
    {
        $buyer = Buyer::find($buyerId);
        if (empty($buyer)) {
            return withError('Buyer not found', 404);
        }

        return withSuccess(new BuyerResource($buyer));
    }

    /**
     * Updates the specified buyer's information based on the provided request data.
     *
     * @param CreateOrUpdateBuyerRequest $request
     * @param int $buyerId
     * @return Response
     */
    public function updateBuyer(CreateOrUpdateBuyerRequest $request, int $buyerId): Response
    {
        $buyer = Buyer::find($buyerId);
        if (empty($buyer)) {
            return withError('Buyer not found', 404);
        }

        $buyer->update($request->validated());
        return withSuccess(message: 'Buyer updated successfully');
    }

    /**
     * Deletes the specified buyer by ID.
     *
     * @param Request $request
     * @param int $buyerId
     * @return Response
     */
    public function deleteBuyer(Request $request, int $buyerId): Response
    {
        $buyer = Buyer::find($buyerId);
        if (empty($buyer)) {
            return withError('Buyer not found', 404);
        }

        $buyer->delete();

        return withSuccess(message: 'Buyer deleted successfully');
    }
}
