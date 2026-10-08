<?php

namespace App\Http\Controllers\Api\Setting;

use App\Helpers\SettingHandler;
use App\Helpers\Utility;
use App\Http\Controllers\Api\Setting\Requests\CreateOrUpdateGlobalPostbackRequest;
use App\Http\Controllers\Api\Setting\Resources\GlobalPostbackResource;
use App\Http\Controllers\Api\Setting\Resources\SingleGlobalPostbackResource;
use App\Http\Controllers\Controller;
use App\Models\GlobalPostback;
use App\Traits\CommonTrait;
use Illuminate\Http\Request;

class GlobalPostbackController extends Controller
{
    use CommonTrait;

    /**
     * Retrieves a list of GlobalPoatback based on the request parameters.
     *
     * @param Request $request
     * @return Response
     */
    public function globalPostbacks(Request $request)
    {
        $limit = $request->input('limit', 10);
        $orderBy = $request->input('order_by');
        $orderIn = $request->input('order_in');
        $status = array_search($request->input('status'), Utility::$userStatus);

        // user role is affiliate and load relationship affiliate
        $affiliates = GlobalPostback::query()

            ->when(!empty($request->search_txt), function ($query) use ($request) {

                $searchTxt = "%{$request->search_txt}%";

                return $query->where(function ($query) use ($searchTxt) {
                    $query->where('name', 'like', $searchTxt)
                        ->orWhere('url', 'like', $searchTxt);
                });

            })
            ->when($status !== false, function ($query) use ($status) {
                return $query->where('status', $status);
            })
            ->when(!empty($orderBy) && !empty($orderIn), function ($query) use ($orderBy, $orderIn) {
                return $query->orderBy($orderBy, $orderIn);
            }, function ($query) {
                return $query->orderBy('id', 'desc');
            })
            ->latest('id')
            ->paginate($limit);

        return withSuccessResourceList(GlobalPostbackResource::collection($affiliates));
    }

    /**
     * Retrieves a single GlobalPoatback based on the provided ID.
     *
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function globalPostback(Request $request, $id)
    {
        $affiliate = GlobalPostback::query()
            ->findOrFail($id);

        return withSuccess(new SingleGlobalPostbackResource($affiliate));
    }

    /**
     * Updates the GlobalPoatback based on the provided ID.
     *
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function update(CreateOrUpdateGlobalPostbackRequest $request, $id)
    {

        $existingPostBack = GlobalPostback::query()
            ->where('id', '=', $id)
            ->first();

        //get the validated data
        $validatedData = $request->validated();

        $conditionData = [];

        if( isset($validatedData['conditions']) ){
            foreach($validatedData['conditions'] as $condition){
                $conditionData[$condition['column']] = [
                    'logic' => $condition['condition'],
                    'value' =>  $condition['value']
                ];
            }
        }
        $existingPostBack->update([
            'name' => $validatedData['name'],
            'url' => $validatedData['url'],
            'status' => $validatedData['status'] == true ? 1 : 0,
            'conditions' => json_encode($conditionData),
            'postback_event' => $validatedData['postback_event'] ?? null
        ]);


        return withSuccess(new SingleGlobalPostbackResource($existingPostBack), 'Global Postback updated successfully');
    }

    /**
     * Store a new affiliate.
     *
     * @param Request $request
     * @return Response
     */
    public function create(CreateOrUpdateGlobalPostbackRequest $request)
    {
        //get the validated data
        $validatedData = $request->validated();

        $conditionData = [];

        if( isset($validatedData['conditions']) ){
            foreach($validatedData['conditions'] as $condition){
                $conditionData[$condition['column']] = [
                    'logic' => $condition['condition'],
                    'value' =>  $condition['value']
                ];
            }
        }

        $affiliate = GlobalPostback::create([
            'name' => $validatedData['name'],
            'url' => $validatedData['url'],
            'status' => $validatedData['status'] == true ? 1 : 0,
            'conditions' => json_encode($conditionData),
            'postback_event' => $validatedData['postback_event'] ?? null
        ]);

        // return with success response
         return withSuccess(new SingleGlobalPostbackResource($affiliate), 'Global Postback created successfully');
    }

    /**
     * Delete the Global Postback based on the provided ID.
     *
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function delete(Request $request, $id)
    {
        $postback = GlobalPostback::findOrFail($id);

        if(!$postback){
            return withError('Invalid Global Postback ID');
        }
        $postback->delete();

        return withSuccess('Successfully deleted the postback');
    }

    /**
     * Return the list of Postback events in a multi-dimensional array format.
     *
     * @return array
     */
    public function getPostbackEvents(){
        $events = $this->convertToMultiDimensionalArray(SettingHandler::$postBackEvents);
        return withSuccess($events);
    }

}
