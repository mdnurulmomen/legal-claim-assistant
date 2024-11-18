<?php

namespace App\Http\Controllers\Api\ConferenceEvent;

use App\Helpers\Utility;
use App\Http\Controllers\Api\ConferenceEvent\Requests\CreateOrUpdateConferenceEventRequest;
use App\Http\Controllers\Api\ConferenceEvent\Resources\ConferenceEventResource;
use App\Http\Controllers\Controller;
use App\Models\ConferenceEvents;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Http\Response;

class ConferenceEventController extends Controller
{
    /**
     * Retrieves a list of Conference Event based on the request parameters.
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request)
    {
        $limit = $request->input('limit', 10);

        $conferences = ConferenceEvents::orderBy('created_at', 'desc');
        $conferences = $conferences->when( !empty($request->search_txt), function ($query) use ($request) {
            return $query->where('title', 'like', '%' . $request->search_txt . '%');
        });

        //paginate
        $conferences = $conferences->paginate(10, ['*'], 'page', $request->page ?? 1);

        // paginate($limit);

        return withSuccessResourceList(ConferenceEventResource::collection($conferences));
    }

    /**
     * Store a new Conference Event.
     *
     * @param Request $request
     * @return Response
     */
    public function create(CreateOrUpdateConferenceEventRequest $request)
    {
        $conference = ConferenceEvents::create($request->only(
            [
                'id',
                'title',
                'description',
                'thumb',
                'event_start_date',
                'event_end_date',
                'event_button_text',
                'event_link',
            ]
        ));

        // return with success response
        return withSuccess(new ConferenceEventResource($conference), 'Conference Event created successfully');
    }

    /**
     * Retrieves a single Conference Event based on the provided ID.
     *
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function show(Request $request, $id)
    {
        $conference = ConferenceEvents::findOrFail($id);

        if (!$conference) {
            return withError('Invalid Conference ID');
        }

        return withSuccess(new ConferenceEventResource($conference));
    }

    /**
     * Updates the Conference Event based on the provided ID.
     *
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function update(CreateOrUpdateConferenceEventRequest $request, $id)
    {
        $conference = ConferenceEvents::findOrFail($id);

        if (!$conference) {
            return withError('Invalid Conference ID');
        }
            // update affiliate
        $conference->update($request->only([
            'title',
            'description',
            'thumb',
            'event_start_date',
            'event_end_date',
            'event_button_text',
            'event_link',
        ]));


        return withSuccess(new ConferenceEventResource($conference), 'Conference Event updated successfully');
    }

    /**
     * delete a single Conference Event based on the provided ID.
     *
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function delete(Request $request, $id)
    {
        $conference = ConferenceEvents::findOrFail($id);

        if (!$conference) {
            return withError('Invalid Conference ID');
        }
        $conference->delete();

        return withSuccess(message: 'Conference deleted successfully');
    }
}
