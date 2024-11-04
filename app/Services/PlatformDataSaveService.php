<?php

// namespace App\;
namespace App\Services\PlatformServices;

use App\Classes\Helpers;
use App\Jobs\Platform\GlobalPostbackTriggerJob;
use App\Models\Conversions;
use App\Models\PlatformData;
use App\Models\PlatformList;
use App\Models\Data;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use DB;
use Illuminate\Support\Facades\Http;
use App\Jobs\Platform\UpdatePlatformListInfoJob;
use App\Jobs\Platform\MapPlatformLeadJob;
use App\Jobs\Platform\SaveLeadLogsJob;
use App\Jobs\Platform\SendToGoHighLevelJob;
use App\Models\Emails;
use App\Models\PlatformPings;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Http\Client\Pool;

class PlatformDataSaveService
{

    //append $payloa so it can be accessed globally
    private static $payload = [];
    private static $saved_data = [];
    private static $additionalRequestPayload = [];
    private static $responses = [];
    private static $platform_list;
    private static $request_data = [];
    private static $leadLog = [];
    private static $retriedLead = false;

    public static function save($payload, $phone_number, $retried = false)
    {

            //reset static variables first
            static::$payload = [];
            static::$saved_data = [];
            static::$additionalRequestPayload = [];
            static::$responses = [];
            static::$leadLog = [];

            //set retried lead
            static::$retriedLead = $retried;

            //buyer attempt count
            $buyer_attempt_count = 0;

            $request = new Request($payload);

            static::$request_data["country"] = $request->country ?? "US";

            $save_point = $phone_number ? 'phone' : 'email';

            $api_mode = $payload['api_mode'] ?? null;

            unset($payload['source']);

            if (isset($payload['platform_name'])) {
                unset($payload['platform_name']);
            }
            if (isset($payload['platform_key'])) {
                unset($payload['platform_key']);
            }
            if (isset($payload['api_mode'])) {
                unset($payload['api_mode']);
            }

            //hydrate variables
            $int_response_payload = [];
            $all_conversions      = [];
            $buyers_type          = [];

            $platform_list = PlatformList::where('source', '=', $request->source)->where('status', 'Active')->first();

            if ($platform_list) {

                //check if the data already exists
                if (
                $platform_list->options['skip_duplicate']
                &&
                Conversions::where($save_point, $payload[$save_point] ?? '')->where('platform_id', $platform_list->id)->first()
                &&
                !$retried
                ) {

                    return response()->json([
                        'status' => 'success',
                        'message' => 'Duplicate'
                    ]);
                };

                $platform_list->total = $platform_list->total + 1;

            } else {

                $platform_list = new PlatformList;
                $platform_list->name = $request->platform_name ?? $request->source;
                $platform_list->tag = strtoupper(Str::random(15));
                $platform_list->cv_trigger = [
                    [
                        "name" => "Sign up",
                        "active" => true,
                        "int_id" => null,
                        "triggers" => [],
                        "save_data" => true,
                        "tracker_postback" => []
                    ]
                ];
                $platform_list->options = [
                    "skip_duplicate" => true,
                    "lead_distribution" => "private",
                ];
                $platform_list->source = $request->source;
            }


            // append $platform_list
            static::$platform_list = $platform_list;


            if ($api_mode == "instant") {
                // map payload data to datas table, but in job
                MapPlatformLeadJob::dispatch($payload)->onConnection('platform_lists')->onQueue('platform_lists');
                // self::phoneMapToDatas($payload);
                // self::emailMapToDatas($payload);
            }


            //adjust headers
            $data_headers = array_keys($payload);
            $data_headers[] = 'created_at';
            $diff = array_diff($data_headers, $platform_list->headers ?? []);
            if (!empty($diff)) {
                $platform_list->headers = array_merge($platform_list->headers ?? [], $diff);
            }

            DB::transaction(function () use ($platform_list) {

                $platform_list->save();
            }, 50);

            //save the data
            unset($payload['phone'], $payload['email']);
            $saved_data['datas']   = $payload;
            $saved_data['list_id'] = $platform_list->id;

            //hidrate payload variable
            static::$payload        = $payload;

            //original payload
            self::$leadLog["original_payload"] = $payload;

            // try {
            //     $saved_data['phone']   = $request->phone ? phone($request->phone, $request->country ?? "US")->formatE164() : null;
            // } catch (\Throwable $th) {
            //     $saved_data['phone']   = $request->phone;
            // }
            $saved_data['phone']   = $phone_number ?? Null;
            $saved_data['email']   = $request->email ?? Null;


            //hidrate saved_data variable
            static::$saved_data     = $saved_data;


            //start posting lead to partner platforms
            if ($platform_list->integrations) {

                $all_integrations = $platform_list->integrations;

                // if ($platform_list->options['lead_distribution'] == "private") {
                    usort($all_integrations, function ($a, $b) {
                        if ($a["order"] == $b["order"]) {
                            return 0;
                        }
                        return ($a["order"] < $b["order"]) ? -1 : 1;
                    });
                // }


                //check if the distribution type is highest bidder
                if ($platform_list->options['lead_distribution'] == "top_bidder") {

                    //hydrate eligible pingable buyers
                    $eligible_pingable_buyers = [];

                    $final_postable_buyers = [];

                    $fixed_price_buyers = [];

                    //find all pingable buyers
                    $pingable_buyers = self::getPingableBuyers($all_integrations);

                    //find all non-pingable active buyers
                    $non_pingable_buyers = collect($all_integrations)->where('active', true)->whereNotIn('name', collect($pingable_buyers)->pluck('name'))->toArray();

                    //if pingable buyers are found
                    if($pingable_buyers) {

                        //iterate over the pingable buyers
                        foreach ($pingable_buyers as $pingable) {

                            //check if they are eligible to be pinged at the first place
                            if(self::isBuyerEligible($pingable)) {
                                $eligible_pingable_buyers[] = $pingable;
                            }

                        }

                    }


                    //if any eligible pingable buyers are found
                    if ($eligible_pingable_buyers) {

                        //ping the buyers and get the response concurrently
                        $responses = Http::pool(fn (Pool $pool) => self::buildHttpPool($pool, $eligible_pingable_buyers));


                        //convert the responses to collection
                        $eligible_pingable_buyers = collect($eligible_pingable_buyers);


                        //now iterate over all the responses
                        foreach ($responses as $buyer => $response) {

                            $intData = $eligible_pingable_buyers->where('name', $buyer)->first();

                            //extract the response
                            $response = self::handleResponseOutput($response, $intData);

                            //save the responses
                            self::saveResponseData($intData['ping'], $response, $buyer);

                            if($response && self::isMatchedResponse($intData['ping']['triggers'] ?? [], $response)) {

                                //get the price
                                $intData["sort_price"] = floatval(Helpers::search_array_recursive($response, $intData['ping']['payout']['params'] ?? 'price') ?? 0);

                                //append skip_filter_check so we can skip the filter check later
                                $intData["skip_filter_check"] = true;

                                //append the int so we can sort later
                                $final_postable_buyers[] = $intData;

                                //save the log
                                self::$leadLog["grouped_pings"][$intData['name']]["price"]["amount"] = $intData["sort_price"];
                                self::$leadLog["grouped_pings"][$intData['name']]["price"]["mode"] = "ping";

                            };

                        }
                    }


                    //now find the buyers that don't have ping but has a fixed price
                    foreach ($non_pingable_buyers as $npKey => $npInt) {
                        if (isset($npInt['payout']) && $npInt['payout']['model'] == "fixed") {

                            $npInt["sort_price"] = floatval($npInt['payout']['amount']);
                            $fixed_price_buyers[] = $npInt;

                            //save the log
                            self::$leadLog["grouped_pings"][$npInt['name']]["price"]["amount"] = $npInt["sort_price"];
                            self::$leadLog["grouped_pings"][$npInt['name']]["price"]["mode"] = "fixed";

                            //unset from the $non_pingable_buyers so we end up with only no pingable and no fixed price buyers
                            unset($non_pingable_buyers[$npKey]);

                        }
                    }


                    //merge all sortable buyers and sort the buyers based on price
                    $all_sortable_buyers = self::sortMultiArray(array_merge($final_postable_buyers, $fixed_price_buyers));



                    //final step, append the non-pingable buyers to the end of the sorted array
                    $all_integrations = array_merge($all_sortable_buyers, $non_pingable_buyers);


                    //free memory
                    $eligible_pingable_buyers = $final_postable_buyers = $fixed_price_buyers = $all_sortable_buyers = $non_pingable_buyers = $responses = null;

                }

                foreach ($all_integrations as $integration) {
                    $buyer_attempt_count = $integration['order'] ?? 0;
                    $lead_buyer = null;
                    try {
                        if ($integration['active']) {

                            //save the log
                            self::$leadLog["direct_posts"][$integration['name']] = [
                                    "active" => true,
                                    "order"  => $buyer_attempt_count,
                                    "timestamp" => Carbon::now()->toDateTimeString()
                            ];

                            // foreach ($integration['filter'] as $filter_by => $filters) {

                            //         if (!isset($payload[$filter_by]) || (isset($payload[$filter_by]) && !in_array($payload[$filter_by], $filters))) {

                            //             //set current integration response to not eligible
                            //             $int_response_payload[$integration['name'] . "_status"] = "Ineligible";

                            //             continue 2;
                            //         }

                            // }

                            //check if it's an internal buyer
                            if (isset($integration['internal_buyer']) && self::isBoughtCPLLead($integration['name'], $saved_data['phone'], $saved_data['list_id'])) {

                                //set current integration response to not eligible
                                $int_response_payload[$integration['name'] . "_status"] = "Internal Duplicate";

                                //save the log
                                self::$leadLog["direct_posts"][$integration['name']]["internal_duplicate"] = true;

                                continue;
                            }


                            if(!isset($integration['skip_filter_check'])) {

                            //save the log
                            self::$leadLog["direct_posts"][$integration['name']]["filters"]["as"] = $integration['filter'] ?? [];
                            self::$leadLog["direct_posts"][$integration['name']]["filters"]["passed"] = true;
                            self::$leadLog["direct_posts"][$integration['name']]["caps"]["passed"] = true;

                            //check filters
                            if(!self::checkFilters($integration['filter'] ?? [], $payload)) {

                                //set current integration response to not eligible
                                $int_response_payload[$integration['name'] . "_status"] = "Ineligible";

                                //save the log
                                self::$leadLog["direct_posts"][$integration['name']]["filters"]["passed"] = false;


                                continue;

                            }

                            //check if caps are defined
                            if (self::isCapped($integration['caps'] ?? [], $platform_list->id, $integration['name'])) {

                                //set current integration response to capped
                                $int_response_payload[$integration['name'] . "_status"] = "Capped";

                                //save the log
                                self::$leadLog["direct_posts"][$integration['name']]["caps"]["passed"] = false;

                                continue;

                            }


                            //check for spam
                            if (self::isSpam($saved_data, $integration['name']) && !$retried) {
                                //set current integration response to spam
                                $int_response_payload[$integration['name'] . "_status"] = "Spam";
                                continue;
                            }

                        }


                            //hydrate
                            $int_payload = [];

                            //check if additional payload is defined, if yes then init it as the payload
                            if (array_key_exists($integration['name'], self::$additionalRequestPayload)) {
                                $int_payload = self::$additionalRequestPayload[$integration['name']];
                            }


                            if ($integration['custom_maps']) {

                                foreach ($integration['custom_maps'] as $custom_maps_key => $custom_maps_value) {
                                    $custom_keys = explode(",", $custom_maps_key);

                                    $custom_result = (strpos($custom_maps_value, '||') === false && strpos($custom_maps_value, '}}') === false) ? $custom_maps_value : self::transformData($custom_maps_value, $saved_data);


                                    foreach(array_reverse($custom_keys) as $custom_key) {
                                        $custom_result = [$custom_key => $custom_result];
                                    }

                                    $int_payload = array_replace_recursive($int_payload, $custom_result);

                                }
                            }

                            //map the fields with integration fields and make the payload to send to the integration
                            foreach ($integration['maps'] as $db_val => $int_val) {

                                if ($db_val == "phone") {

                                    $payload_val = phone($saved_data['phone'], $request->country ?? "US");

                                    if ($integration['phone_format']) {

                                        //format phone based on integration phone format option
                                        $payload_val = self::formatPhone($payload_val, $integration['phone_format']);


                                    }
                                } elseif (in_array($db_val, ['email', 'created_at'])) {
                                    $payload_val = $saved_data[$db_val] ?? null;
                                } elseif ($db_val == 'source') {
                                    $payload_val = $platform_list->source;
                                } else {
                                    $payload_val = $saved_data['datas'][$db_val] ?? null;

                                    //if null then continue
                                    if (!$payload_val) {
                                        continue;
                                    }

                                }

                                $prep_keys = explode(",", $int_val);

                                $prep_result = $payload_val;

                                foreach(array_reverse($prep_keys) as $prep_key) {
                                    // if (preg_match('/\[(\d+)\]/', $prep_key, $matches)) {
                                    //     $prep_result = [preg_replace('/\[\d+\]/', '', $prep_key) => [$matches[1] => $prep_result]];
                                    // } else {
                                    //   $prep_result = [$prep_key => $prep_result];
                                    // }

                                    $prep_result = [$prep_key => $prep_result];

                                }

                                $int_payload = array_replace_recursive($int_payload, $prep_result);

                            }

                            //check if convert mapped data is defined
                            if ($integration['convert_maps']) {

                                // foreach ($integration['convert_maps'] as $param => $data) {
                                //     $int_payload[$param] = $data[$int_payload[$param]] ?? $int_payload[$param];
                                // }

                                foreach ($integration['convert_maps'] as $convert_param => $convert_data) {
                                    $convert_keys = explode(",", $convert_param);

                                    // Traverse the array using the extracted keys and update the "content" value
                                    $temp = &$int_payload;
                                    foreach ($convert_keys as $key) {
                                        $temp = &$temp[$key];
                                    }
                                    $temp = $convert_data[$temp] ?? $temp;

                                }
                            }

                            if (!empty($int_payload)) {


                                //check if ping required for this integration
                                if (isset($integration["ping"]["required"]) && $integration["ping"]["required"] && $platform_list->options['lead_distribution'] == "private") {

                                    //ping and get the response
                                    $ping_response = self::sendCurl($integration["ping"], $int_payload, "ping", $saved_data, $integration["name"]);

                                    //check if trigger matched and response is ok
                                    if (!$ping_response) {

                                        //skip this integration
                                        continue;

                                    }

                                    $int_payload = array_replace_recursive($ping_response["ping_data"], $int_payload);

                                    $int_response_payload = array_merge($int_response_payload, $ping_response["saved_ping_data"]);

                                }


                                // Perform the HTTP request //NEW V2
                                $request_response = self::sendCurl($integration, $int_payload);



                                //perform http request with dynamic url, method, headers, and payload
                                // Perform the HTTP request
                                // $response = Http::withHeaders([
                                //     'Content-Type' => 'application/json',
                                // ]);

                                // if($integration['auth']) {
                                //     if ($integration['auth']['type'] == "bearer") {

                                //         //bearar token
                                //         $response = $response->withToken($integration['auth']['value']);

                                //     } elseif ($integration['auth']['type'] == "basic") {

                                //         //basic auth
                                //         $response = $response->withBasicAuth($integration['auth']['key'], $integration['auth']['value']);

                                //     } else {

                                //         //key value auth
                                //         $response = $response->withHeaders([
                                //             $integration['auth']['key'] => $integration['auth']['value']
                                //         ]);

                                //     }

                                // }

                                // if (isset($integration['curl']['payload'])) {

                                //     $payload_params = http_build_query($int_payload);

                                //     $response = $response->{$integration['curl']['method']}($integration['curl']['url'] . "?" . $payload_params);

                                // } else {

                                //     $response = $response->{$integration['curl']['method']}($integration['curl']['url'], $int_payload);

                                // }

                                // // Check for any errors or exceptions
                                // if ($response->failed()) {

                                //     //send the error to sentry
                                //     \Sentry\captureMessage($response->body());
                                // }

                                // //response data
                                // try {

                                //     //check xml
                                //     $request_response = json_decode(json_encode(simplexml_load_string($response->body())), TRUE);

                                // } catch (\Throwable $th) {

                                //     $request_response = $response->json();

                                // }



                                if ($integration['save_data'] && $request_response) {


                                    foreach ($integration['save_data'] as $value) {

                                        //check if value is wrapped inside {}
                                        if (strpos($value, '{') !== false) {

                                            $nested_item_key_val = Helpers::getNestedKeyValue($request_response, $value);

                                            if (!empty($nested_item_key_val['key']) && !empty($nested_item_key_val['value'])) {

                                                $int_response_payload[$integration['name'] . "_" . $nested_item_key_val['key']] = $nested_item_key_val['value'];

                                            }
                                        } else {

                                        $int_response_payload[$integration['name'] . "_" . $value] = Helpers::search_array_recursive($request_response, $value);

                                        }

                                    }
                                } else {

                                    $int_response_payload[$integration['name'] . "_status"] = "Invalid Response From API";

                                }
                            }
                        } else {

                            //save the log as buyer is inactive
                            self::$leadLog["direct_posts"][$integration['name']] = [
                                "active" => false,
                                "order"  => $buyer_attempt_count,
                                "timestamp" => Carbon::now()->toDateTimeString()
                            ];

                            //go to next buyer
                            continue;

                        }
                    } catch (\Throwable $th) {

                        //send the error to sentry
                        \Sentry\captureMessage($th->getMessage());

                        //append the error as plain text as a column
                        $int_response_payload[$integration['name'] . "_status"] = $th->getMessage();

                        //save to log
                        self::$leadLog["direct_posts"][$integration['name']]["result"] = $th->getMessage();

                    }

                    // break the loop if conversion is matched
                    // if ($platform_list->options['lead_distribution'] == "private") {

                        $triggers = collect($platform_list->cv_trigger)->where('name', $integration['name'])->first()['triggers'] ?? [];

                        foreach ($triggers as $key => $conversions) {
                            if (($int_response_payload[$integration['name'] . "_" . $key] ?? "x") != $conversions) {
                                continue 2;
                            }
                            $lead_buyer = $integration['name'];
                        }

                        //successful conversion
                        if ($lead_buyer) {

                            //save the log
                            self::$leadLog["direct_posts"][$integration['name']]["result"] = "Sold";

                            $int_response_payload["lead_buyer"] = $lead_buyer;
                            break;
                        }
                    // }
                }
            }


            //check if additional responses are defined, if yes then append it to the response
            if (!empty(self::$responses)) {
                $int_response_payload = array_merge($int_response_payload, self::$responses);
            }

            if ($int_response_payload) {

                //add the headers from integration responses
                $data_headers = array_keys($int_response_payload);
                $diff = array_diff($data_headers, $platform_list->headers ?? []);
                if (!empty($diff)) {
                    $platform_list->headers = array_merge($platform_list->headers ?? [], $diff);
                }

                //wrap the save in a transaction
                DB::transaction(function () use ($platform_list) {

                    $platform_list->update();
                }, 50);

                //merge the integration response payload with the saved data
                $saved_data['datas'] = array_merge($saved_data['datas'], $int_response_payload);
            }


            //if the lead is sold, let's add the payout and revenues
            if (($int_response_payload["lead_buyer"] ?? false) && !empty($triggers)) {
                //append the payout and revenue to the saved data
                //get revenue and payouts

                $revenue_data = self::getPayoutRevenue($platform_list->integrations, $platform_list->cv_trigger, $saved_data, $int_response_payload["lead_buyer"], self::$platform_list->options['dynamic_margin'] ?? []);

                $saved_data = array_merge($saved_data, $revenue_data);

                //if internal buyer, then set the as internal_buyer
                if (self::isInternalBuyer($int_response_payload["lead_buyer"], $platform_list->integrations)) {
                    $saved_data['is_internal'] = true;
                }

            }


            $new_saved_data = PlatformDatas::create($saved_data);
            $new_saved_data = $new_saved_data->toArray();

            //postback
            if ($platform_list->cv_trigger) {
                foreach ($platform_list->cv_trigger as $conversions) {
                    if ($conversions['active'] && self::isActiveIntegration($conversions['name'], $platform_list->integrations ?? [])) {

                        //triggered
                        $triggered = self::isTriggered($conversions, $new_saved_data);

                        if ($triggered) {

                            //cv triggered, do action
                            if ($conversions['save_data']) {

                                //save data in conversion table
                                $conversion = new Conversions();
                                $conversion->platform_id = $platform_list->id;
                                $conversion->name = $conversions['name'];
                                $conversion->email = $new_saved_data['email'] ?? null;
                                $conversion->phone = $new_saved_data['phone'] ?? null;
                                $conversion->cv_source = $platform_list->source;
                                $conversion->save();

                                //total conversions
                                $all_conversions[] = $conversion->name;
                                $this_buyers_type = self::getBuyerType($conversions['name'], $platform_list->integrations ?? []);
                                if ($this_buyers_type) {
                                    $buyers_type[] = $this_buyers_type;
                                }

                            }

                            if ($conversions['tracker_postback']) {

                                //get all postbacks
                                foreach ($conversions['tracker_postback'] as $postback) {

                                    $empty_dynamic = false;

                                    try {

                                        //postback Url
                                        $postback_url = preg_replace_callback('/{([^{}]+)}/', function ($matches) use ($new_saved_data, &$empty_dynamic) {

                                            $parameter = $matches[1];

                                            $logic = explode(":", $parameter);

                                            if (count($logic) > 1) {

                                                $parameter = $logic[0];
                                                $logic = $logic[1];

                                            } else {

                                                $logic = null;

                                            }

                                            if ($parameter && empty($new_saved_data['datas'][$parameter]) && $parameter != "monetize") {
                                                $empty_dynamic = true;
                                            }

                                            if ($logic && $parameter == "monetize") {

                                                //we have a setting to get the value of monetize
                                                //get the value of monetize
                                                if($logic == "revenue") {
                                                    $parameter = $new_saved_data['revenue'] ?? 0;
                                                } elseif($logic == "payout") {
                                                    $parameter = $new_saved_data['payout'] ?? 0;
                                                } elseif($logic == "affiliate_payout") {
                                                    $parameter = ($new_saved_data['revenue'] ?? 0) - ($new_saved_data['payout'] ?? 0);
                                                }

                                            } elseif (isset($new_saved_data['datas'][$parameter])) {

                                                $parameter = $new_saved_data['datas'][$parameter];

                                                //if logic contains a "%", then calculate the percentage
                                                if ($logic && strpos($logic, '%') !== false) {

                                                    $parameter = $parameter * (intval($logic) / 100);

                                                }

                                            } else {

                                                    $parameter = null;
                                            }

                                            return $parameter;

                                        }, $postback);

                                        //do not trigger postback if the dynamic value is null
                                        if ($empty_dynamic) {
                                            continue;
                                        }

                                        //postback fired log
                                        self::$leadLog["direct_posts"][$conversions['name']]["postback_triggers"][] = [
                                            "url" => $postback_url,
                                            "timestamp" => Carbon::now()->toDateTimeString()
                                        ];

                                        //send postback request
                                        file_get_contents($postback_url);

                                    } catch (\Throwable $th) {

                                        //send the error to sentry
                                        \Sentry\captureMessage($th->getMessage());
                                    }
                                }
                            }
                        }
                    }
                }
            }


            //return response
            $return = [
                'status' => 'success',
                'message' => 'Data saved'
            ];

            if ($all_conversions) {
                $return['conversions'] = $all_conversions;
            }
            if ($buyers_type) {
                $return['buyers_type'] = $buyers_type;
            }

            //global postback
            if (!empty($platform_list->options["global_postback"])) {

                //call the global postback service
                $postback_logs = GlobalPostbackServices::trigger($platform_list->options["global_postback"], $new_saved_data);

                //save the log
                self::$leadLog["global_postback"] = $postback_logs;

            }

            //global postback - site level
            GlobalPostbackTriggerJob::dispatch([
                "payload" => $new_saved_data,
                "platform_list" => $platform_list
            ]);

            //dispatch lead logs processor job
            if (!$retried) {

                SaveLeadLogsJob::dispatch(self::$leadLog, $platform_list->id, $new_saved_data['id']);

            } else {

                SaveLeadLogsJob::dispatchSync(self::$leadLog, $platform_list->id, $new_saved_data['id']);

            }


            //check if go high level is enabled and push the lead if enabled
            if ($platform_list->options["gohighlevel"]["active"] ?? false) {

                //push to go high level
                SendToGoHighLevelJob::dispatch(["payload" => $new_saved_data, "list_tag" => $platform_list->tag, "list_name" => $platform_list->name, "gohighlevel" => $platform_list->options["gohighlevel"], "lead_buyers" => $all_conversions]);

            }

            if ($retried) {

                //send lead id back
                $return['lead_id'] = $new_saved_data['id'];

            }

            return $return;
    }

    public static function phoneMapToDatas($payload)
    {
        $updateAbleColumn = ['name', 'first_name', 'last_name', 'gender', 'city', 'state', 'zipcode', 'address', 'country', 'province', 'time_zone', 'age', 'ip_address', 'dob', 'device'];

        if(isset($payload['phone'])){
            $lead = Data::where('phone', $payload['phone'])->first();
            $updateData = [];
            if($lead){
                foreach($payload as $key => $value){
                    $lowaerKey = strtolower($key);
                    if( empty($lead->zipcode) || is_null($lead->zipcode) ){
                        if( $lowaerKey == 'zip_code' || $lowaerKey == 'zipcode'){
                            $updateData['zipcode'] = $value;
                        }
                    }
                    if( empty($lead->address) || is_null($lead->address) ){
                        if( $lowaerKey == 'address' && isset($payload['address']) ){
                            $updateData['address'] = $value;
                        }
                    }
                    if(in_array(strtolower($key), $updateAbleColumn)){
                        if( empty($lead->{$key}) || is_null($lead->{$key}) ){
                            $updateData[$key] = $value;
                        }
                    }
                }

                if( count($updateData) > 0 ){
                    $lead->update($updateData);

                    //datalist
                    $datalist = $lead->list;
                    $missingItems = [];

                    if ( $datalist && !is_null($datalist->data) && is_array($datalist->data) ) {
                        foreach ($updateData as $header => $value) {
                            if (!in_array($header, $datalist->data)) {
                                $missingItems[] = $header;
                            }
                        }

                        if (count($missingItems) > 0) {

                            $datalist->data = array_merge($datalist->data, $missingItems);
                            $datalist->save();

                        }
                    }


                }
            }
        }
    }

    public static function emailMapToDatas($payload)
    {
        $updateAbleColumn = ['name', 'first_name', 'last_name', 'gender', 'city', 'state', 'zipcode', 'address', 'country', 'province', 'time_zone', 'age', 'ip_address', 'dob', 'device'];

        if(isset($payload['email'])){
            $lead = Emails::where('email', $payload['email'])->first();
            $updateData = [];
            if($lead){
                foreach($payload as $key => $value){
                    $lowaerKey = strtolower($key);
                    if( empty($lead->zipcode) || is_null($lead->zipcode) ){
                        if( strtolower($key) == 'zip_code' || strtolower($key) == 'zipcode'){
                            $updateData['zipcode'] = $value;
                        }
                    }
                    if( empty($lead->address) || is_null($lead->address) ){
                        if( $lowaerKey == 'address' && isset($payload['address']) ){
                            $updateData['address'] = $value;
                        }
                    }
                    if(in_array(strtolower($key), $updateAbleColumn)){
                        if( empty($lead->{$key}) || is_null($lead->{$key}) ){
                            $updateData[$key] = $value;
                        }
                    }
                }

                if( count($updateData) > 0 ){
                    $lead->update($updateData);


                    //datalist
                    $datalist = $lead->list;
                    $missingItems = [];

                    if ( $datalist && !is_null($datalist->data) && is_array($datalist->data) ) {
                        foreach ($updateData as $header => $value) {
                            if (!in_array($header, $datalist->data)) {
                                $missingItems[] = $header;
                            }
                        }

                        if (count($missingItems) > 0) {

                            $datalist->data = array_merge($datalist->data, $missingItems);
                            $datalist->save();

                        }
                    }


                }
            }
        }
    }

    public static function savePublic($payload, $list_data, $retried = false)
    {

            //reset static variables first
            static::$payload = [];
            static::$saved_data = [];
            static::$additionalRequestPayload = [];
            static::$responses = [];
            static::$leadLog = [];

            //set retried lead
            static::$retriedLead = $retried;

            $request = new Request(Helpers::fixUtf8($payload));

            $mode = $list_data["mode"] ?? "post";

            //hydrate variables
            static::$request_data["country"] = $request->country ?? "US";
            // append $platform_list
            $list_data['id'] = $list_data["list_id"];
            $list_data['source'] = null;
            static::$platform_list = (object) $list_data;

            $save_point = 'phone';

            //hydrate variables
            $int_response_payload = [];
            $ping_data      = [];
            $totalPingableBuyer   = 0;
            $buyer_attempt_count  = 0;
            $buyer_type = null;

            //default price and percentage
            $price = 0;
            $percentage = 0;

            //hydrate ping id
            $ping_id = strtoupper(Str::random(12));


            $test_lead = $request->lp_test == 1 ? true : false;


            // $platform_list = PlatformLists::where('id', $options["list_id"])->first();

            if ($mode != "ping" && !$test_lead && $list_data["options"]['skip_duplicate'] && Conversions::where($save_point, $payload[$save_point] ?? '')->where('platform_id', $list_data["list_id"])->first() && !$retried) {

                $return = [
                    'status' => 'error',
                    'message' => 'Duplicate',
                    'errors'  => [
                        'phone' => ['Phone number already exists.']
                    ],
                    'price'   => 0
                ];

                if ($mode == "ping") {
                   $return['ping_id'] = '';
                }

                return $return;


            };



            if (!$test_lead) {
                //no match response
                $return = [
                    'status' => 'success',
                    'message' => 'No match',
                    'errors'  => [],
                    'price'   => 0
                ];
            } else {

                $return = [
                    'status' => 'success',
                    'message' => $mode == 'ping' ? 'Ping Was Successful' : 'Matched',
                    'errors'  => [],
                    'price'   => 1000
                ];

                self::$leadLog["is_test"] = true;

            }

            self::$leadLog["affiliate_lead"] = true;

            //save the data

            unset($payload['phone'], $payload['email'], $payload['ping_id'], $payload['lp_test']);

            $saved_data['datas']   = $payload;

            //hidrate payload variable
            static::$payload        = $payload;

            //original payload
            self::$leadLog["original_payload"] = $payload;

            $payload               = null; //free memory

            $saved_data['list_id'] = $list_data["list_id"];

            $saved_data['phone']   = $request->phone ?? Null;
            $saved_data['email']   = $request->email ?? Null;


            //hidrate saved_data variable
            static::$saved_data     = $saved_data;


            if ($mode != "ping_post") {

            //filter out the integrations that is not active for this affiliate
            $active_buyers = $list_data["connection_option"]["lead_posting"]["buyers"] ?? [];

            $list_data["integrations"] = collect($list_data["integrations"])->whereIn('name', $active_buyers)->toArray();

            } else {

                //for ping post we only need the 1 targeted buyer
                $list_data["integrations"] = array_values(collect($list_data["integrations"])->where('name', $list_data['ping_info']['buyer'] ?? [])->toArray());

                if ($list_data["integrations"]) {

                    //append necessary data for ping post
                    $list_data["integrations"][0]['skip_filter_check'] = true;

                    static::$additionalRequestPayload[$list_data["integrations"][0]['name']] = $list_data['ping_info']['buyer_ping_post_data'] ?? [];

                    static::$responses = $list_data['ping_info']['ping_response'] ?? [];

                }


                $ping_data = $list_data['ping_info'] ?? [];


            }


            //start posting lead to partner platforms
            if (!empty($list_data["integrations"]) && !$test_lead) {

                //sort first
                 if ($mode != "ping_post") {
                        usort($list_data["integrations"], function ($a, $b) {
                            if ($a["order"] == $b["order"]) {
                                return 0;
                            }
                            return ($a["order"] < $b["order"]) ? -1 : 1;
                        });
                    // }


                    //check if the distribution type is highest bidder
                    if ($list_data["options"]['lead_distribution'] == "top_bidder") {

                        //hydrate eligible pingable buyers
                        $eligible_pingable_buyers = [];

                        $final_postable_buyers = [];

                        $fixed_price_buyers = [];

                        //find all pingable buyers
                        $pingable_buyers = self::getPingableBuyers($list_data["integrations"]);

                        //find all non-pingable active buyers
                        $non_pingable_buyers = collect($list_data["integrations"])->where('active', true)->whereNotIn('name', collect($pingable_buyers)->pluck('name'))->toArray();

                        //if pingable buyers are found
                        if($pingable_buyers) {

                            //iterate over the pingable buyers
                            foreach ($pingable_buyers as $pingable) {

                                //check if they are eligible to be pinged at the first place
                                if(self::isBuyerEligible($pingable)) {
                                    $eligible_pingable_buyers[] = $pingable;
                                }

                            }

                        }


                        //if any eligible pingable buyers are found
                        if ($eligible_pingable_buyers) {

                            //ping the buyers and get the response concurrently
                            $responses = Http::pool(fn (Pool $pool) => self::buildHttpPool($pool, $eligible_pingable_buyers));


                            //convert the responses to collection
                            $eligible_pingable_buyers = collect($eligible_pingable_buyers);


                            //now iterate over all the responses
                            foreach ($responses as $buyer => $response) {

                                $intData = $eligible_pingable_buyers->where('name', $buyer)->first();

                                //extract the response
                                $response = self::handleResponseOutput($response, $intData);

                                //save the responses
                                self::saveResponseData($intData['ping'], $response, $buyer);

                                if($response && self::isMatchedResponse($intData['ping']['triggers'] ?? [], $response)) {

                                    //get the price
                                    $intData["sort_price"] = floatval(Helpers::search_array_recursive($response, $intData['ping']['payout']['params'] ?? 'price') ?? 0);

                                    //append skip_filter_check so we can skip the filter check later
                                    $intData["skip_filter_check"] = true;

                                    //append the int so we can sort later
                                    $final_postable_buyers[] = $intData;

                                    //save the log
                                    self::$leadLog["grouped_pings"][$intData['name']]["price"]["amount"] = $intData["sort_price"];
                                    self::$leadLog["grouped_pings"][$intData['name']]["price"]["mode"] = "ping";

                                };

                            }
                        }


                        //now find the buyers that don't have ping but has a fixed price
                        foreach ($non_pingable_buyers as $npKey => $npInt) {
                            if (isset($npInt['payout']) && $npInt['payout']['model'] == "fixed" && self::isBuyerEligible($npInt)) {

                                //check if the buyer is eligible first

                                $npInt["sort_price"] = floatval($npInt['payout']['amount']);
                                $npInt["skip_filter_check"] = true;
                                $fixed_price_buyers[] = $npInt;

                                //save the log
                                self::$leadLog["grouped_pings"][$npInt['name']]["price"]["amount"] = $npInt["sort_price"];
                                self::$leadLog["grouped_pings"][$npInt['name']]["price"]["mode"] = "fixed";

                                //unset from the $non_pingable_buyers so we end up with only no pingable and no fixed price buyers
                                unset($non_pingable_buyers[$npKey]);

                            }
                        }


                        //merge all sortable buyers and sort the buyers based on price
                        $all_sortable_buyers = self::sortMultiArray(array_merge($final_postable_buyers, $fixed_price_buyers));


                        //count of pingable + fixed price buyers
                        $totalPingableBuyer = count($all_sortable_buyers);


                        //final step, append the non-pingable buyers to the end of the sorted array
                        $list_data["integrations"] = array_merge($all_sortable_buyers, $non_pingable_buyers);


                        //free memory
                        $eligible_pingable_buyers = $final_postable_buyers = $fixed_price_buyers = $all_sortable_buyers = $non_pingable_buyers = $responses = null;

                    }

                 }

                //if ping return response
                if ($mode == "ping" && $totalPingableBuyer > 0) {

                    //return the ping response to affiliate
                    $ping_data = self::prepareAffiliatePingPrice($list_data["integrations"][0]);

                    $ping_data['buyer'] = $list_data["integrations"][0]['name'];
                    $ping_data['accepted'] = true;
                    $ping_data['buyer_ping_post_data'] = self::$additionalRequestPayload[$ping_data['buyer']] ?? [];

                    $return = [
                        'status' => 'success',
                        'message' => 'Ping Was Successful',
                        'errors'  => [],
                        'price'   => $ping_data['affiliate_price']
                    ];


                } else {


                foreach ($list_data["integrations"] as $integration) {
                    $buyer_attempt_count = $integration['order'] ?? 0;
                    $lead_buyer = null;
                    try {
                        if ($integration['active']) {

                            //save the log
                            self::$leadLog["direct_posts"][$integration['name']] = [
                                    "active" => true,
                                    "order"  => $buyer_attempt_count,
                                    "timestamp" => Carbon::now()->toDateTimeString()
                            ];

                            // foreach ($integration['filter'] as $filter_by => $filters) {

                            //         if (!isset($saved_data['datas'][$filter_by]) || (isset($saved_data['datas'][$filter_by]) && !in_array($saved_data['datas'][$filter_by], $filters))) {

                            //             //set current integration response to not eligible
                            //             $int_response_payload[$integration['name'] . "_status"] = "Ineligible";

                            //             continue 2;
                            //         }

                            // }

                            //check if it's an internal buyer
                            if (isset($integration['internal_buyer']) && self::isBoughtCPLLead($integration['name'], $saved_data['phone'], $saved_data['list_id'])) {

                                //set current integration response to not eligible
                                $int_response_payload[$integration['name'] . "_status"] = "Internal Duplicate";

                                //save the log
                                self::$leadLog["direct_posts"][$integration['name']]["internal_duplicate"] = true;

                                continue;
                            }


                            if(!isset($integration['skip_filter_check'])) {

                            //save the log
                            self::$leadLog["direct_posts"][$integration['name']]["filters"]["as"] = $integration['filter'] ?? [];
                            self::$leadLog["direct_posts"][$integration['name']]["filters"]["passed"] = true;
                            self::$leadLog["direct_posts"][$integration['name']]["caps"]["passed"] = true;

                            //check filters
                            if(!self::checkFilters($integration['filter'] ?? [], $saved_data['datas'])) {

                                //set current integration response to not eligible
                                $int_response_payload[$integration['name'] . "_status"] = "Ineligible";

                                //save the log
                                self::$leadLog["direct_posts"][$integration['name']]["filters"]["passed"] = false;

                                continue;

                            }


                            //check if caps are defined
                            if (self::isCapped($integration['caps'] ?? [], $saved_data['list_id'], $integration['name'])) {

                                //set current integration response to capped
                                $int_response_payload[$integration['name'] . "_status"] = "Capped";

                                //save the log
                                self::$leadLog["direct_posts"][$integration['name']]["caps"]["passed"] = false;

                                continue;

                             }


                            //check for spam
                            if (self::isSpam($saved_data, $integration['name']) && !$retried) {
                                //set current integration response to spam
                                $int_response_payload[$integration['name'] . "_status"] = "Spam";
                                continue;
                            }


                            }


                            //hydrate
                            $int_payload = [];

                            //check if additional payload is defined, if yes then init it as the payload
                            if (array_key_exists($integration['name'], self::$additionalRequestPayload)) {
                                $int_payload = self::$additionalRequestPayload[$integration['name']];
                            }


                            if ($integration['custom_maps']) {

                                foreach ($integration['custom_maps'] as $custom_maps_key => $custom_maps_value) {
                                    $custom_keys = explode(",", $custom_maps_key);

                                    $custom_result = $saved_data["datas"][$custom_maps_key] ?? $custom_maps_value;

                                    //check if transform is field defined
                                    if ((strpos($custom_maps_value, '||') !== false || strpos($custom_maps_value, '}}') !== false)) {

                                        $custom_result = self::transformData($custom_maps_value, $saved_data);

                                    }

                                    foreach(array_reverse($custom_keys) as $custom_key) {
                                        $custom_result = [$custom_key => $custom_result];
                                    }

                                    $int_payload = array_replace_recursive($int_payload, $custom_result);

                                }
                            }

                            //map the fields with integration fields and make the payload to send to the integration
                            foreach ($integration['maps'] as $db_val => $int_val) {

                                if ($db_val == "phone") {

                                    $payload_val = phone($saved_data['phone'], $request->country ?? "US");

                                    if ($integration['phone_format']) {
                                        $payload_val = self::formatPhone($payload_val, $integration['phone_format']);
                                    }
                                } elseif (in_array($db_val, ['email', 'created_at'])) {
                                    $payload_val = $saved_data[$db_val] ?? null;
                                } else {
                                    $payload_val = $saved_data['datas'][$db_val] ?? null;

                                    //if null then continue
                                    if (!$payload_val) {
                                        continue;
                                    }

                                }

                                $prep_keys = explode(",", $int_val);

                                $prep_result = $payload_val;

                                foreach(array_reverse($prep_keys) as $prep_key) {
                                    // if (preg_match('/\[(\d+)\]/', $prep_key, $matches)) {
                                    //     $prep_result = [preg_replace('/\[\d+\]/', '', $prep_key) => [$matches[1] => $prep_result]];
                                    // } else {
                                    //   $prep_result = [$prep_key => $prep_result];
                                    // }

                                    $prep_result = [$prep_key => $prep_result];

                                }

                                $int_payload = array_replace_recursive($int_payload, $prep_result);

                            }

                            //check if convert mapped data is defined
                            if ($integration['convert_maps']) {

                                // foreach ($integration['convert_maps'] as $param => $data) {
                                //     $int_payload[$param] = $data[$int_payload[$param]] ?? $int_payload[$param];
                                // }

                                foreach ($integration['convert_maps'] as $convert_param => $convert_data) {
                                    $convert_keys = explode(",", $convert_param);

                                    // Traverse the array using the extracted keys and update the "content" value
                                    $temp = &$int_payload;
                                    foreach ($convert_keys as $key) {
                                        $temp = &$temp[$key];
                                    }
                                    $temp = $convert_data[$temp] ?? $temp;

                                }
                            }

                            // dd($int_payload);


                            if (!empty($int_payload)) {

                                //perform http request with dynamic url, method, headers, and payload


                                //check if ping required for this integration
                                if (isset($integration["ping"]["required"]) && $integration["ping"]["required"] && $list_data["options"]['lead_distribution'] == "private" && $mode == "post") {

                                    //ping and get the response
                                    $ping_response = self::sendCurl($integration["ping"], $int_payload, "ping", $saved_data, $integration["name"]);


                                    //check if trigger matched and response is ok
                                    if (!$ping_response) {

                                        //skip this integration
                                        continue;

                                    }

                                    $int_payload = array_replace_recursive($ping_response["ping_data"], $int_payload);

                                    $int_response_payload = array_merge($int_response_payload, $ping_response["saved_ping_data"]);

                                }


                                // Perform the HTTP request
                                $request_response = self::sendCurl($integration, $int_payload);

                                if ($integration['save_data'] && $request_response) {


                                    foreach ($integration['save_data'] as $value) {

                                        //check if value is wrapped inside {}
                                        if (strpos($value, '{') !== false) {

                                            $nested_item_key_val = Helpers::getNestedKeyValue($request_response, $value);

                                            if (!empty($nested_item_key_val['key']) && !empty($nested_item_key_val['value'])) {

                                                $int_response_payload[$integration['name'] . "_" . $nested_item_key_val['key']] = $nested_item_key_val['value'];

                                            }
                                        } else {

                                        $int_response_payload[$integration['name'] . "_" . $value] = Helpers::search_array_recursive($request_response, $value);

                                        }

                                    }
                                } else {

                                    $int_response_payload[$integration['name'] . "_status"] = "Invalid Response From API";

                                }
                            }

                        } else {

                            //save the log as buyer is inactive
                            self::$leadLog["direct_posts"][$integration['name']] = [
                                "active" => false,
                                "order"  => $buyer_attempt_count,
                                "timestamp" => Carbon::now()->toDateTimeString()
                            ];

                            //go to next buyer
                            continue;

                        }
                    } catch (\Throwable $th) {

                        //send the error to sentry
                        \Sentry\captureMessage($th->getMessage());

                        //append the error as plain text as a column
                        $int_response_payload[$integration['name'] . "_status"] = $th->getMessage();

                        //save to log
                        self::$leadLog["direct_posts"][$integration['name']]["result"] = $th->getMessage();

                        //go to next buyer
                        // continue;

                        //return
                        return [
                            'status' => 'error',
                            'message' => 'Invalid request',
                            'errors'  => [],
                            'price'   => 0
                        ];

                    }



                    //check if additional responses are defined, if yes then append it to the response
                    if (!empty(self::$responses)) {
                        $int_response_payload = array_merge($int_response_payload, self::$responses);
                    }


                    //check if lead_distribution private and break the loop if conversion is matched
                    // if ($list_data["options"]['lead_distribution'] == "private") {

                        $triggers = collect($list_data["cv_trigger"])->where('name', $integration['name'])->first()['triggers'] ?? [];

                        foreach ($triggers as $key => $conversions) {
                            if (($int_response_payload[$integration['name'] . "_" . $key] ?? "x") != $conversions) {

                               //check if there is a personalized error message for affiliates from this buyer
                               $error_msg = self::getAffiliateErrorMsg($integration, $int_response_payload);

                               //if message is found, append to the return message
                               if ($error_msg && $mode !== "ping") {
                                  $return['message'] = $error_msg;
                               }

                                continue 2;
                            }
                            $lead_buyer = $integration['name'];
                        }

                        //successful conversion
                        if ($lead_buyer) {

                            //save the log
                            self::$leadLog["direct_posts"][$integration['name']]["result"] = "Sold";

                            $int_response_payload["lead_buyer"] = $lead_buyer;

                            //get the buyer type
                            $buyer_type = self::getBuyerType($lead_buyer, $list_data["integrations"]);

                            if ($mode != "ping_post") {

                                //payout settings
                                $payout_settings = $list_data["connection_option"]["lead_posting"]["payout"] ?? [];

                                if($payout_settings) {


                                if($payout_settings["model"] == "dynamic") {


                                //payout will depend on the lead buyer

                                if (isset($integration["payout"])) {

                                    if ($integration["payout"]["model"] == "fixed") {

                                        //fixed price for this buyer only
                                        $integration_price = $integration["payout"]["amount"];

                                    } else {

                                        //dynamic price for this buyer only based on the response

                                        $integration_price = intval($int_response_payload[$integration["name"] . "_" . $integration["payout"]["params"]] ?? 0);

                                    }


                                    //check if revenue by affid is defined, then overwrite the previous logics as this is leading
                                    if (!empty($integration["buyer_payout_by_affid"]) && isset($saved_data['datas']['affid'])) {

                                        //check if the setting for this affiliate's affid is defined
                                        $buyer_payout_by_affid_setting = collect($integration["buyer_payout_by_affid"])->where('affid', $saved_data['datas']['affid'])->first() ?? [];

                                        if ($buyer_payout_by_affid_setting) {

                                            $integration_price = $buyer_payout_by_affid_setting['value'] ?? 0;

                                        }

                                    }


                                    try {

                                        //split the price by percentage
                                        $percentage = $payout_settings["percentage"] ?? 0;
                                        $price = round($integration_price * ($percentage / 100), 2);

                                    } catch (\Throwable $th) {
                                        //send report to sentry
                                        \Sentry\captureMessage($th->getMessage());
                                    }

                                }


                                } else {

                                //fixed payout model for all buyer connection to this advertiser

                                $price = $payout_settings["amount"];

                                }

                            }
                            } else {

                                //ping post price
                                $price = $list_data['ping_info']['affiliate_price'] ?? 0;

                            }

                            //save affiliate price to log
                            self::$leadLog["direct_posts"][$integration['name']]["affiliate_payout"] = [
                                "price" => $price,
                                "percentage" => $percentage
                            ];

                            //matched data
                            $return = [
                                'status' => 'success',
                                'message' => 'Matched',
                                'errors'  => [],
                                'price'   => $price
                            ];

                            if (!empty($buyer_type)) {
                                $return['buyer_type'] = $buyer_type;
                            }

                            //finish the loop
                            break;
                        }
                    // }
                }

              }

            }


            if ($mode == "ping") {

                if ($test_lead) {
                 $ping_data['accepted'] = true;
                }

                $ping_data['ping_id'] = $ping_id;

                $ping_data['ping_response'] = self::$responses;

                $ping_data['ping_logs']     = self::$leadLog;

                $return['ping_id'] = $ping_id;

                //create ping
                PlatformPings::create($ping_data);

            } else {

                //check if go high level is enabled and push the lead if enabled
                if ((self::$platform_list->options["gohighlevel"]["active"] ?? false) && !$test_lead) {

                    //push to go high level
                    SendToGoHighLevelJob::dispatch(["payload" => $saved_data, "list_id" => self::$platform_list->id, "gohighlevel" => self::$platform_list->options["gohighlevel"], "lead_buyers" => $lead_buyer ?? []]);

                }

            }


            //dispatch a job to do all the update and heavy intensive process
            $update_data = ["int_response_payload" => $int_response_payload,"saved_data" => $saved_data, "list_id" => $list_data["list_id"], "advertiser_id" => $list_data["advertiser_id"], "advertiser_name" => $list_data["advertiser_name"], "price" => $price, "percentage" => $percentage, "mode" => $list_data["mode"], "ping_data" => $ping_data, "leadLog" => self::$leadLog];

            if ($retried) {

                //add retried key
                $update_data["retried"] = true;

                $new_created_lead = PlatformDatasProcesserService::savePlatformDatas($update_data);

                return [
                    'status' => 'success',
                    'message' => 'Data saved',
                    'lead_id' => $new_created_lead['id']
                ];

            }

            //if config app env production dispatch in specific connection and queue
            if (config('app.env') == 'production') {

                UpdatePlatformListInfoJob::dispatch($update_data)->onConnection('platform_lists')->onQueue('platform_lists');

            } else {

                UpdatePlatformListInfoJob::dispatch($update_data);
            }


            // dd($ping_data);

            return $return;


    }

    public static function sendCurl($integration, $int_payload, $mode = "post", $saved_data = [], $int_name = null) {

        //ping payload
        if ($mode == "ping") {

            if ($integration['params']) {

                $newArr = [];
                foreach ($integration['params'] as $param) {

                    $newArr = array_replace_recursive($newArr, self::buildNestedArrayOrValue($param, $int_payload));

                }

                $int_payload = $newArr;
                $newArr = null;

            }

            if ($integration['custom_params']) {

                foreach ($integration['custom_params'] as $custom_maps_key => $custom_maps_value) {
                    $custom_keys = explode(",", $custom_maps_key);

                    $custom_result = (strpos($custom_maps_value, '||') === false && strpos($custom_maps_value, '}}') === false) ? $custom_maps_value : self::transformData($custom_maps_value, $saved_data);

                    foreach(array_reverse($custom_keys) as $custom_key) {
                        $custom_result = [$custom_key => $saved_data["datas"][$custom_key] ?? $custom_result];
                    }

                    $int_payload = array_replace_recursive($int_payload, $custom_result);

                }
            }

            //save to log
            self::$leadLog["direct_posts"][$int_name]["ping"]["body"] = $int_payload;
            self::$leadLog["direct_posts"][$int_name]["ping"]["method"] = $integration['curl']['method'];

        } else {

            $int_name = $integration['name'] ?? $int_name;

            //save to log
            self::$leadLog["direct_posts"][$int_name]["body"] = $int_payload;
            self::$leadLog["direct_posts"][$int_name]["method"] = $integration['curl']['method'];

        }


        //payload settings
        $curlPayloadSettings = $integration['curl']['payload'] ?? null;


        //set content type
       $content_type = $curlPayloadSettings == "urlencoded" ? "application/x-www-form-urlencoded" : "application/json";


        // Perform the HTTP request
        $response = Http::retry(3, 1)->withHeaders([
            'Content-Type' => $content_type,
        ]);


        //if set as urlencoded then send as formdata
        if ($curlPayloadSettings == "urlencoded") {

            //send as urlencoded
            $response = $response->asForm();

        }


        if($integration['auth']) {
            if ($integration['auth']['type'] == "bearer") {

                //bearar token
                $response = $response->withToken($integration['auth']['value']);

            } elseif ($integration['auth']['type'] == "basic") {

                //basic auth
                $response = $response->withBasicAuth($integration['auth']['key'], $integration['auth']['value']);

            } else {

                //key value auth
                $key_values = [$integration['auth']['key'] => $integration['auth']['value']];

                if (!empty($integration['auth']['additional_headers'])) {
                    foreach ($integration['auth']['additional_headers'] as $h_key => $add_header) {

                        $key_values[$h_key] = $add_header;

                    }
                }

                $response = $response->withHeaders($key_values);

            }

        }

        //save headers to log
        self::$leadLog["direct_posts"][$int_name]["headers"] = $response->getOptions()['headers'] ?? [];


        if($curlPayloadSettings == "query_params") {

            $payload_params = http_build_query($int_payload);

            $response = $response->{$integration['curl']['method']}($integration['curl']['url'] . "?" . $payload_params);

        } else {

            $response = $response->{$integration['curl']['method']}($integration['curl']['url'], $int_payload);

        }


        // Check for any errors or exceptions
        if ($response->failed()) {

            //send the error to sentry
            \Sentry\captureMessage($response->body());
        }

        //response data
        try {

            //check xml
            if (!in_array("default_response", $integration['save_data'])) {

                $request_response = json_decode(json_encode(simplexml_load_string($response->body())), TRUE);

            } else {

                //raw response, remove newlines
                $request_response["default_response"] = str_replace("\n", "", $response->body());

            }

        } catch (\Throwable $th) {

            $request_response = $response->json();

        }

        if ($mode == "post") {

            //save to log
            self::$leadLog["direct_posts"][$int_name]["response"] = $request_response;

            return $request_response;

        }

        //save to log
        self::$leadLog["direct_posts"][$int_name]["ping"]["response"] = $request_response;


        //but check if the response is empty or matches the triggers defined, otherwise return false so we can skip this integration
        if(empty($request_response) || !self::isMatchedResponse($integration['triggers'] ?? [], $request_response)) {


            //save ping data to global vars
            self::saveResponseData($integration, $request_response, $int_name);

            return false;

        }


        //return with specific keys
        $return_arr = [];
        $saved_ping_data = [];

        foreach ($integration['save_data'] as $save_data_key => $save_data_value) {

            $custom_save_data_result = Helpers::search_array_recursive($request_response, $save_data_key);

            if (!empty($save_data_value)) {
                $custom_save_data_keys = explode(",", $save_data_value);

                $saved_ping_data[$int_name."_".$save_data_key] = $custom_save_data_result;

                foreach(array_reverse($custom_save_data_keys) as $custom_save_data_key) {
                    $custom_save_data_result = [$custom_save_data_key => $custom_save_data_result];
                }

                $return_arr = array_replace_recursive($return_arr, $custom_save_data_result);

            } else {

                // $return_arr = [];

                //check if the save_data_key contains the word "ping"
                $saved_ping_data[$int_name."_".(strpos($save_data_key, 'ping') == false ? "ping_" : "").$save_data_key] = $custom_save_data_result;

            }

        }

        return ["ping_data" => $return_arr, "saved_ping_data" => $saved_ping_data];

    }

    public static function formatPhone($phone, $type)
    {

        try {

            if ($type == "E164" || $type == "national") {

                $phone = $phone->format($type);

            } elseif ($type == "national_raw") {

                $phone = preg_replace('/\D/', '', $phone->format("national"));

           } elseif ($type == "national_nospace") {

                $phone = str_replace(' ', '', $phone->format("national"));

            } else if ($type == "e164_raw") {

                //remove plus
                $phone = preg_replace('/\D/', '', $phone->formatE164());

            } else if ($type == "national_dashed") {

                //format national with dash, remove () and replace space with dash
                $phone = str_replace(['(', ')', ' '], ['', '', '-'], $phone->format("national"));

            } else if ($type == "national_spaced") {

                $phone = str_replace(['(', ')', '-'], ['', '', ' '], $phone->format("national"));

            }

        } catch (\Throwable $th) {
            //throw $th;

            $phone = null;

            //send to sentry
            \Sentry\captureMessage($th->getMessage());

        }

        return $phone;

    }

    public static function buildNestedArrayOrValue($keysString, $sourceArray)
    {
        $keys = explode(',', $keysString);
        $nestedArray = [];

        $currentArray = &$nestedArray;
        foreach ($keys as $key) {
            $currentArray[$key] = [];
            $currentArray = &$currentArray[$key];
        }

        $currentArray = self::getValueFromNestedArray($keys, $sourceArray);

        return $nestedArray;
    }

    public static function getValueFromNestedArray($keys, $array)
    {
        foreach ($keys as $key) {
            if (isset($array[$key])) {
                $array = $array[$key];
            } else {
                return null;
            }
        }

        return $array;
    }

    public static function checkFilters($filters, $payload) {
        foreach ($filters as $key => $values) {

            $payloadValue = $payload[$key] ?? null;

                foreach ($values as $value) {
                    if (strpos($value, '!') === 0) {

                        // Negate the comparison

                        $filterValue = substr($value, 1);

                        $filterValue = ($filterValue === "null") ? null : (($filterValue === "true") ? true : (($filterValue === "false") ? false : $filterValue));

                        if ($payloadValue === $filterValue) {
                            return false;
                        }

                    } else {
                        // Regular comparison
                        if (!in_array($payloadValue, $values, true)) {

                            return false;

                        }
                    }
                }


        }

        return true; // All filters match
    }


    //check for caps
    public static function isCapped($caps, $platform_id, $buyer, $point = 'post') {

        foreach ($caps as $cap_idx => $cap_config) {
            if ($cap_config['active']) {

                //convert the time from NY to Amsterdam
                $startDate = Carbon::now('America/New_York')->startOf($cap_config['duration'] == "daily" ? "day" : "week")->tz('Europe/Amsterdam')->toDateTimeString();

                $endDate = Carbon::now('America/New_York')->tz('Europe/Amsterdam')->toDateTimeString();

                //start logic
                $totalCount = Conversions::where('platform_id', $platform_id)->where('postback_conversions.name', $buyer)->whereBetween('postback_conversions.created_at', [$startDate, $endDate]);

                if (!empty($cap_config['column'])) {

                    //join with platform datas with phone
                    foreach ($cap_config['column'] as $col => $val) {

                        if (!(isset(self::$payload[$col]) && self::$payload[$col] == $val)) {

                            //if the payload doesn't match the cap config then skip this cap
                            continue 2;

                        }

                        $totalCount = $totalCount->join('platform_datas', 'platform_datas.phone', '=', 'postback_conversions.phone')->where('platform_datas.list_id', $platform_id);

                        //search in json column "datas" with the $col as key whereJsonContains
                        $totalCount = $totalCount->where('platform_datas.datas->' . $col, $val)->distinct('postback_conversions.id');

                    }
                }

                $totalCount = $totalCount->count();

                if ($totalCount >= $cap_config['amount']) {

                    //save to log
                    $log_save_point = $point == 'post' ? "direct_posts" : "grouped_pings";
                    self::$leadLog[$log_save_point][$buyer]["caps"]['as'][$cap_idx] = $cap_config;
                    self::$leadLog[$log_save_point][$buyer]["caps"]['as'][$cap_idx]["current_total"] = $totalCount;

                    return true;

                }

            }
        }


        return false; // No Caps
    }



    //check if integration is active
    public static function isActiveIntegration($name, $integrations)
    {
        try {

            //check in $integrations array with the name
            foreach($integrations as $integration) {

                if($integration['name'] == $name && $integration['active']) {

                    return true;

                }

            }

        } catch (\Throwable $th) {

            //send to sentry
            \Sentry\captureMessage($th->getMessage());

        }

        return false;

    }


    //check if integration is active
    public static function getBuyerType($name, $integrations)
    {

        $type = null;
        try {

            //check in $integrations array with the name
            foreach($integrations as $integration) {

                if($integration['name'] == $name && !empty($integration['buyer_type'])) {

                    $type = $integration['buyer_type'];

                    break;

                }

            }

        } catch (\Throwable $th) {

            //send to sentry
            \Sentry\captureMessage($th->getMessage());

        }

        return $type;

    }


    /**
     * Summary of getPayoutRevenue
     * @param mixed $integration_data
     * @param mixed $conversion
     * @param mixed $saved_data
     * @param mixed $buyer
     * @return array
     */
    public static function getPayoutRevenue($integration_data, $conversion, $saved_data, $buyer, $dynamic_margin, $margin = 15, $affiliate_price = 0) {

        $revenue = 0;
        $payout = 0;
        $affid = !empty($saved_data['datas']['affid']) ? $saved_data['datas']['affid'] : null;

        try {

            $integration_data = collect($integration_data);

            $payout_settings = $integration_data->where('name', $buyer)->first()['payout'] ?? [];

            $buyer_payout_by_affid_setting = $integration_data->where('name', $buyer)->first()['buyer_payout_by_affid'] ?? [];

            if ($payout_settings) {

                //revenue
                if (!empty($payout_settings['model'])) {

                    $revenue = $payout_settings['model'] == "fixed" ? $payout_settings['amount'] : ($saved_data['datas'][$buyer."_".$payout_settings['params'] ?? 'price'] ?? 0);

                }

                //default margin
                // $margin = $payout_settings['default_margin'] ?? $margin;

                //revenue margin dynamic
                if (!empty($dynamic_margin) && $affid) {

                    $margin = isset($dynamic_margin[$affid]) ? (100 - $dynamic_margin[$affid]) : $margin;

                }


            } else {

                //get the revenue from the conversion trigger postback data
                $payout_params = collect($conversion)->where('name', $buyer)->first()['tracker_postback'][0] ?? [];

                if($payout_params) {

                    $payout_params = Request::create($payout_params);

                    $revenue = is_numeric($payout_params->payout) ? $payout_params->payout : (preg_match('/\{(.*?)\}/', $payout_params->payout, $params_idx) ? ($saved_data['datas'][$params_idx[1]] ?? 0) : 0);


                }

            }

            //check if revenue by affid is defined, then overwrite the previous logics as this is leading
            if ($buyer_payout_by_affid_setting && $affid) {

                $buyer_payout_by_affid_setting = collect($buyer_payout_by_affid_setting)->where('affid', $affid)->first() ?? [];

                if ($buyer_payout_by_affid_setting) {

                    $revenue = $buyer_payout_by_affid_setting['value'] ?? 0;

                }

            }


        } catch (\Throwable $th) {

            //throw $th;

            //send the error to sentry
            \Sentry\captureMessage($th->getMessage());

        }


        //calculate the payout
        if ($revenue > 0) {

            $payout = $revenue * ($margin / 100);

            //if the margin is 100 then find out the actual payout from the affiliate price
            if ($margin == 100 && $affiliate_price > 0) {
                $payout = $revenue - $affiliate_price;
            }

        }

        return [
            'revenue' => $revenue,
            'payout' => $payout
        ];

    }



    //check if the conversion is triggerd
    public static function isTriggered($conversions, $saved_data) {

        //init the saved data
        $saved_data = $saved_data['datas'] ?? [];

        if (count($conversions['triggers']) > 0 && $saved_data) {

            foreach ($conversions['triggers'] as $key => $value) {

                if (!isset($saved_data[$conversions['name'] . "_" . $key]) || $saved_data[$conversions['name'] . "_" . $key] != $value) {

                    //no match, return false immediately
                    return false;

                }

            }

        } else {

            //no triggers, return false immediately
            return false;

        }


        //all triggers match, return true
        return true;
    }


    /**
     * Summary of getPingableBuyers
     * @param mixed $integrations
     * @return array
     */
    public static function getPingableBuyers($integrations) {

        $pingable_buyers = [];

        foreach ($integrations as $integration) {

            if ($integration['active'] && isset($integration['ping']) && $integration['ping']['required']) {

                $pingable_buyers[] = $integration;

            }

            //save to logs
            self::$leadLog["grouped_pings"][$integration['name']]["active"] = $integration['active'];
            self::$leadLog["grouped_pings"][$integration['name']]["ping_required"] = $integration['ping']['required'] ?? false;

        }

        return $pingable_buyers;

    }



    /**
     * isBuyerEligible
     * @param mixed $integration
     * @return bool
     */
    public static function isBuyerEligible($integration) {

        //check if it's an internal buyer
        if (isset($integration['internal_buyer']) && self::isBoughtCPLLead($integration['name'], self::$saved_data['phone'], self::$saved_data['list_id'])) {

            //set current integration response to not eligible
            static::$responses[$integration['name'] . "_status"] = "Internal Duplicate";

            //save to logs
            self::$leadLog["grouped_pings"][$integration['name']]["internal_duplicate"] = true;

            return false;
        }

        //save the log
        self::$leadLog["grouped_pings"][$integration['name']]["filters"]["as"] = $integration['filter'] ?? [];
        self::$leadLog["grouped_pings"][$integration['name']]["filters"]["passed"] = true;


        //check filters
        if(!self::checkFilters($integration['filter'] ?? [], self::$payload)) {

            //set current integration response to not eligible
            static::$responses[$integration['name'] . "_status"] = "Ineligible";

            //save to logs
            self::$leadLog["grouped_pings"][$integration['name']]["filters"]["passed"] = false;

            return false;

        }

        //save to logs
        self::$leadLog["grouped_pings"][$integration['name']]["caps"]["passed"] = true;


        //check if caps are defined
        if (self::isCapped($integration['caps'] ?? [], self::$platform_list->id, $integration['name'], 'ping')) {

            //set current integration response to capped
            static::$responses[$integration['name'] . "_status"] = "Capped";

            //save to logs
            self::$leadLog["grouped_pings"][$integration['name']]["caps"]["passed"] = false;

            return false;

        }

        //check for spam
        if (self::isSpam(self::$saved_data, $integration['name'], 'ping') && !self::$retriedLead) {

            //set current integration response to spam
            static::$responses[$integration['name'] . "_status"] = "Spam";

            return false;
        }


        return true;

    }


    /**
     * Build HTTP pool for concurrent requests
     * @param mixed $pool
     * @param mixed $eligible_buyers
     * @return array
     */
    public static function buildHttpPool($pool, $eligible_buyers) {

        $requests = [];

        foreach ($eligible_buyers as $integration) {

            $requests[] = self::buildPayloadRequest($integration, $pool->as($integration['name']));

        }

        return $requests;

    }


    /**
     * Build payload and request client
     * @param mixed $integration
     * @param mixed $httpClient
     * @param mixed $mode
     * @return mixed
     */
    public static function buildPayloadRequest($integration, $httpClient, $mode = "ping", $test_payload = null) {

        //check if the payload is defined from invocation
        if ($test_payload) {
            //append to global var
            static::$saved_data = $test_payload;
        }

        //hydrate
        $int_payload = [];

        $curlPayloadSettings = null;

        //for post mode or general

        //start mapping
        if ($integration['custom_maps']) {

            foreach ($integration['custom_maps'] as $custom_maps_key => $custom_maps_value) {
                $custom_keys = explode(",", $custom_maps_key);

                $custom_result = (strpos($custom_maps_value, '||') === false && strpos($custom_maps_value, '}}') === false) ? $custom_maps_value : self::transformData($custom_maps_value, self::$saved_data);

                foreach(array_reverse($custom_keys) as $custom_key) {
                    $custom_result = [$custom_key => $custom_result];
                }

                $int_payload = array_replace_recursive($int_payload, $custom_result);

            }
        }

        //map the fields with integration fields
        foreach ($integration['maps'] as $db_val => $int_val) {

            if ($db_val == "phone") {

                $payload_val = phone(self::$saved_data['phone'], static::$request_data["country"] ?? "US");

                if ($integration['phone_format']) {

                    //format phone based on integration phone format option
                    $payload_val = self::formatPhone($payload_val, $integration['phone_format']);


                }
            } elseif (in_array($db_val, ['email', 'created_at'])) {
                $payload_val = self::$saved_data[$db_val] ?? null;
            } elseif ($db_val == 'source') {
                $payload_val = self::$platform_list->source;
            } else {
                $payload_val = self::$saved_data['datas'][$db_val] ?? null;

                //if null then continue
                if (!$payload_val) {
                    continue;
                }

            }

            $prep_keys = explode(",", $int_val);

            $prep_result = $payload_val;

            foreach(array_reverse($prep_keys) as $prep_key) {

                $prep_result = [$prep_key => $prep_result];

            }

            $int_payload = array_replace_recursive($int_payload, $prep_result);

        }


        //check if convert mapped data is defined
        if ($integration['convert_maps']) {

            foreach ($integration['convert_maps'] as $convert_param => $convert_data) {
                $convert_keys = explode(",", $convert_param);

                // Traverse the array using the extracted keys and update the "content" value
                $temp = &$int_payload;
                foreach ($convert_keys as $key) {
                    $temp = &$temp[$key];
                }
                $temp = $convert_data[$temp] ?? $temp;

            }
        }


        //if ping mode, prepare the payload for ping only
        if ($mode == "ping") {

            if ($integration['ping']['params']) {

                $newArr = [];
                foreach ($integration['ping']['params'] as $param) {

                    $newArr = array_replace_recursive($newArr, self::buildNestedArrayOrValue($param, $int_payload));

                }

                $int_payload = $newArr;
                $newArr = null;

            }

            if ($integration['ping']['custom_params']) {

                foreach ($integration['ping']['custom_params'] as $custom_maps_key => $custom_maps_value) {
                    $custom_keys = explode(",", $custom_maps_key);

                    $custom_result = (strpos($custom_maps_value, '||') === false && strpos($custom_maps_value, '}}') === false) ? $custom_maps_value : self::transformData($custom_maps_value, self::$saved_data);

                    foreach(array_reverse($custom_keys) as $custom_key) {
                        $custom_result = [$custom_key => self::$saved_data["datas"][$custom_key] ?? $custom_result];
                    }

                    $int_payload = array_replace_recursive($int_payload, $custom_result);

                }
            }



            //hydrate the curl info (only for ping)
            $curlUrl = $integration['ping']['curl']['url'] ?? null;
            $curlMethod = $integration['ping']['curl']['method'] ?? "post";
            $curlPayloadSettings = $integration['ping']['curl']['payload'] ?? null;


        } else {

            //hydrate the curl info for general
            $curlUrl = $integration['curl']['url'] ?? null;
            $curlMethod = $integration['curl']['method'] ?? "post";
            $curlPayloadSettings = $integration['curl']['payload'] ?? null;

        }


      //payload post string preview
    //   dd(json_encode($int_payload));


       $content_type = $curlPayloadSettings == "urlencoded" ? "application/x-www-form-urlencoded" : "application/json";

        //build HTTP request
        $httpClient = $httpClient->retry(3, 1)->withHeaders([
          'Content-Type' => $content_type,
        ]);


        //if set as urlencoded then send as formdata
        if ($curlPayloadSettings == "urlencoded") {

            //send as urlencoded
            $httpClient = $httpClient->asForm();

        }

        if($integration['auth']) {
            if ($integration['auth']['type'] == "bearer") {

                //bearar token
                $httpClient = $httpClient->withToken($integration['auth']['value']);

            } elseif ($integration['auth']['type'] == "basic") {

                //basic auth
                $httpClient = $httpClient->withBasicAuth($integration['auth']['key'], $integration['auth']['value']);

            } else {

                //key value auth
                $key_values = [$integration['auth']['key'] => $integration['auth']['value']];

                if (!empty($integration['auth']['additional_headers'])) {
                    foreach ($integration['auth']['additional_headers'] as $h_key => $add_header) {

                        $key_values[$h_key] = $add_header;

                    }
                }

                $httpClient = $httpClient->withHeaders($key_values);

            }

        }


        //save to logs
        self::$leadLog["grouped_pings"][$integration['name']]["body"] = $int_payload;
        self::$leadLog["grouped_pings"][$integration['name']]["headers"] = $httpClient->getOptions()['headers'] ?? [];
        self::$leadLog["grouped_pings"][$integration['name']]["method"] = $curlMethod;
        self::$leadLog["grouped_pings"][$integration['name']]["timestamp"] = Carbon::now()->toDateTimeString();


        if($curlPayloadSettings == "query_params") {

            $payload_params = http_build_query($int_payload);

            $httpClient = $httpClient->{$curlMethod}($curlUrl . "?" . $payload_params);

        } else {

            $httpClient = $httpClient->{$curlMethod}($curlUrl, $int_payload);

        }


        return $httpClient;

    }


    /**
     * Handle the response output
     * @param mixed $response
     * @param mixed $integration
     * @return mixed
     */
    public static function handleResponseOutput($response, $integration) {

         // Check for any errors or exceptions
         try {
            //do it inside try catch so we can return response safely
            if ($response->failed()) {

                //send the error to sentry
                \Sentry\captureMessage($response->body());

                //append the respons of this integration as failed
                static::$responses[$integration['name'] . "_status"] = "API call failed";

                //save to logs
                self::$leadLog["grouped_pings"][$integration['name']]["response"] = $response->body();

                return false;
            }
           } catch (\Throwable $th) {
            //throw $th;

            $client = new PendingRequest();
            $retried_output = self::buildPayloadRequest($integration, $client);

            try {

                if ($retried_output->failed()) {

                    //send the error to sentry
                    \Sentry\captureMessage($response->body());

                    //append the respons of this integration as failed
                    static::$responses[$integration['name'] . "_status"] = "[Retried] API call failed";

                    //save to logs
                    self::$leadLog["grouped_pings"][$integration['name']]["response"] = $response->body();

                    return false;

                }

            } catch (\Throwable $th) {
                //throw $th;

                //send the error to sentry
                \Sentry\captureMessage($th->getMessage());

                //append the respons of this integration as failed
                static::$responses[$integration['name'] . "_status"] = "[Retried] API call failed (internal error)";

                //save to logs
                self::$leadLog["grouped_pings"][$integration['name']]["response"] = $th->getMessage();

                return false;

            }


            //success after retry
            $response = $retried_output;



           }



            //response data
            try {

                //check xml
                if (!in_array("default_response", $integration['save_data'])) {

                    $request_response = json_decode(json_encode(simplexml_load_string($response->body())), TRUE);

                } else {

                    //raw response, remove newlines
                    $request_response["default_response"] = str_replace("\n", "", $response->body());

                }

            } catch (\Throwable $th) {

                //otherwise json
                $request_response = $response->json();

            }


            //check if the response is null for the last time
            if (!$request_response) {

                //append the respons of this integration as failed
                static::$responses[$integration['name'] . "_status"] = "Invalid response: " . $response->body();

            }

            //save to logs
            self::$leadLog["grouped_pings"][$integration['name']]["response"] = $request_response;


            return $request_response;

    }


    /**
     * Check if matched
     * @param mixed $triggers
     * @param mixed $response
     * @param mixed $force_match
     * @return mixed
     */
    public static function isMatchedResponse($triggers, $response, $force_match = true) {

        if (count($triggers) > 0) {

            foreach ($triggers as $key => $value) {

                $operator = $value['operator'] ?? "=";

                $value = $value['value'] ?? $value;

                $api_value = Helpers::search_array_recursive($response, $key);

                if($operator == "=") {
                    $isMatch = $value == $api_value;
                } elseif($operator == "!=") {
                    $isMatch = $value != $api_value;
                } elseif($operator == ">") {
                    $isMatch = $value > $api_value;
                } elseif($operator == "<") {
                    $isMatch = $value < $api_value;
                } elseif($operator == ">=") {
                    $isMatch = $value >= $api_value;
                } elseif($operator == "<=") {
                    $isMatch = $value <= $api_value;
                } elseif($operator == "in") {
                    $isMatch = in_array($api_value, $value);
                } elseif($operator == "not in") {
                    $isMatch = !in_array($api_value, $value);
                } elseif($operator == "contains") {
                    $isMatch = strpos($api_value, $value) !== false;
                } elseif($operator == "not contains") {
                    $isMatch = strpos($api_value, $value) === false;
                } elseif($operator == "between_num") {
                    $isMatch = $api_value >= $value && $api_value <= $value;
                } elseif($operator == "starts with") {
                    $isMatch = strpos($api_value, $value) === 0;
                } elseif($operator == "ends with") {
                    $isMatch = $value === "" || substr($api_value, -strlen($value)) === $value;
                } elseif($operator == "regex") {
                    $isMatch = preg_match($value, $api_value);
                } elseif($operator == "not regex") {
                    $isMatch = !preg_match($value, $api_value);
                } elseif($operator == "is null") {
                    $isMatch = $api_value === null;
                } elseif($operator == "is not null") {
                    $isMatch = $api_value !== null;
                } elseif($operator == "is empty") {
                    $isMatch = $api_value === "";
                } elseif($operator == "is not empty") {
                    $isMatch = $api_value !== "";
                } elseif($operator == "is true") {
                    $isMatch = $api_value === true;
                } elseif($operator == "is false") {
                    $isMatch = $api_value === false;
                } elseif($operator == "is numeric") {
                    $isMatch = is_numeric($api_value);
                } elseif($operator == "is not numeric") {
                    $isMatch = !is_numeric($api_value);
                } elseif($operator == "is array") {
                    $isMatch = is_array($api_value);
                } elseif($operator == "is not array") {
                    $isMatch = !is_array($api_value);
                } elseif($operator == "is object") {
                    $isMatch = is_object($api_value);
                } elseif($operator == "is not object") {
                    $isMatch = !is_object($api_value);
                }

            }

            //no triggers, return false immediately
            if (!$isMatch) {
                return false;
            }


        } else {

            //send report to sentry
            \Sentry\captureMessage("No triggers defined for this integration with response " . json_encode($response));

            //all triggers match, return true
            return $force_match;

        }

        return true;


    }


    /**
     * saveResponseData
     * @param mixed $integration
     * @param mixed $request_response
     * @param mixed $int_name
     * @return array
     */
    public static function saveResponseData($integration, $request_response, $int_name) {

        //return with specific keys
        $return_arr = [];
        $saved_ping_data = [];

        if (!empty($request_response)) {

            foreach ($integration['save_data'] as $save_data_key => $save_data_value) {

                $custom_save_data_result = Helpers::search_array_recursive($request_response, $save_data_key);

                if (!empty($save_data_value)) {
                    $custom_save_data_keys = explode(",", $save_data_value);

                    $saved_ping_data[$int_name."_".$save_data_key] = $custom_save_data_result;

                    foreach(array_reverse($custom_save_data_keys) as $custom_save_data_key) {
                        $custom_save_data_result = [$custom_save_data_key => $custom_save_data_result];
                    }

                    $return_arr = array_replace_recursive($return_arr, $custom_save_data_result);

                } else {

                    // $return_arr = [];

                    //check if the save_data_key contains the word "ping"
                    $saved_ping_data[$int_name."_".(strpos($save_data_key, 'ping') == false ? "ping_" : "").$save_data_key] = $custom_save_data_result;

                }

            }

        }

        //add the data to global vars
        static::$responses = array_merge(self::$responses, $saved_ping_data);

        if (!isset(self::$additionalRequestPayload[$int_name])) {
            //if not set, init the array
            static::$additionalRequestPayload[$int_name] = [];
        }

        static::$additionalRequestPayload[$int_name] = array_replace_recursive(self::$additionalRequestPayload[$int_name], $return_arr);

        return ["ping_data" => $return_arr, "saved_ping_data" => $saved_ping_data];

    }


    /**
     * Sort array by given key and direction
     * @param mixed $array
     * @param mixed $sortBy
     * @param mixed $dir
     * @return mixed
     */
    public static function sortMultiArray($array, $sortBy = 'sort_price', $dir = 'DESC') {
        if ($dir === 'DESC') {
            usort($array, function($a, $b) use ($sortBy) {
                return $b[$sortBy] - $a[$sortBy];
            });
        } elseif ($dir === 'ASC') {
            usort($array, function($a, $b) use ($sortBy) {
                return $a[$sortBy] - $b[$sortBy];
            });
        }
        return $array;
    }


    /**
     * Build and return affiliate ping price
     * @param mixed $integration
     * @return array
     */
    public static function prepareAffiliatePingPrice($integration) {

        $data = [];

        $price = 0;

        $internal_price = $integration['sort_price'] ?? 0;

        //payout settings
        $payout_settings = self::$platform_list->connection_option["lead_posting"]["payout"] ?? [];

        if($payout_settings) {


        if($payout_settings["model"] == "dynamic") {


        //check if revenue by affid is defined, then overwrite the previous logics as this is leading
        if (!empty($integration["buyer_payout_by_affid"]) && isset(self::$payload['affid'])) {

            //check if the setting for this affiliate's affid is defined
            $buyer_payout_by_affid_setting = collect($integration["buyer_payout_by_affid"])->where('affid', self::$payload['affid'])->first() ?? [];

            if ($buyer_payout_by_affid_setting) {

                $internal_price = $buyer_payout_by_affid_setting['value'] ?? 0;

            }

        }

        //payout will depend on the lead buyer
        if (isset($integration["payout"])) {

            try {

                //split the price by percentage
                $percentage = $payout_settings["percentage"] ?? 0;
                $price = round($internal_price * ($percentage / 100), 2);

            } catch (\Throwable $th) {
                //send report to sentry
                \Sentry\captureMessage($th->getMessage());
            }

        }


        } else {

        //fixed payout model for all buyer connection to this advertiser

        $price = $payout_settings["amount"];

        }

        }

        $data = [
            'internal_buyer_price' => $internal_price,
            'affiliate_price' => $price
        ];


        return $data;
    }


    public static function getAffiliateErrorMsg($integration, $responses) {

        $return = false;

        try {
            if (!empty($integration['affiliate_error_msg'])) {

                foreach ($integration['affiliate_error_msg'] as $key => $fields) {
                    if (isset($responses[$integration['name'] ."_". $key]) && $fields['buyer_response'] === $responses[$integration['name'] ."_". $key]) {
                        $return = $fields['affiliate_response'];
                        break;
                    }
                }
            }
        } catch (\Throwable $th) {
            //send to sentry
            \Sentry\captureMessage($th->getMessage());
        }

        return $return;
    }


    /**
     * Transform given value with logic
     * @param mixed $data
     * @param mixed $payload_data
     * @return mixed
     */
    public static function transformData($data, $payload_data) {

        try {

            $data = explode(">>", $data);

            $transformPadd = count($data) > 1 ? 1 : 0;

            $transformedValue = explode("||", $data[0])[0];

            //check if it's a dynamic value
            if (substr($transformedValue, 0, 2) === '{{' && substr($transformedValue, -2) === '}}') {

                $dynamic_value = substr($transformedValue, 2, -2);

                if ($dynamic_value == "phone" || $dynamic_value == "email") {

                    $transformedValue = $payload_data[$dynamic_value];


                } elseif($dynamic_value == "current_datetime") {

                    $transformedValue = Carbon::now()->toDateTimeString();

                } else {

                    $transformedValue = $payload_data['datas'][$dynamic_value] ?? null;

                }

            }

            foreach ($data as $transforms) {

                $transforms = explode("||", $transforms);

                //if no transform logic defined, then return the current value
                if (count($transforms) < 2) {
                    break;
                }

                $operation = $transforms[1 - $transformPadd];

                if($operation == "replace") {

                    $transformedValue = str_replace($transforms[2 - $transformPadd], $transforms[3 - $transformPadd], $transformedValue);

                } elseif($operation == "replace_regex") {

                    $transformedValue = preg_replace($transforms[2 - $transformPadd], $transforms[3 - $transformPadd], $transformedValue);

                }  elseif($operation == "pluck") {

                    $transformedValue = strpos($transformedValue, $transforms[2 - $transformPadd]) !== false ? $transforms[2 - $transformPadd] : null;

                } elseif($operation == "pluck_between") {

                    $matches = [];

                    $start = $transforms[2 - $transformPadd];
                    $end = $transforms[3 - $transformPadd];

                    if ($end != '') {
                        $pattern = sprintf(
                            '/%s(.*?)%s/',
                            preg_quote($start, '/'),
                            preg_quote($end, '/')
                        );
                    } else {
                        $pattern = sprintf(
                            '/%s(.*)/',
                            preg_quote($start, '/')
                        );
                    }

                    preg_match($pattern, $transformedValue, $matches);

                    $transformedValue = $matches[1] ?? null;


                } elseif($operation == "pluck_before") {

                    $transformedValue = explode($transforms[2 - $transformPadd], $transformedValue)[0];

                } elseif($operation == "pluck_after") {

                    $transformedValue = explode($transforms[2 - $transformPadd], $transformedValue)[1];

                } elseif($operation == "pluck_between_regex") {

                    $matches = [];

                    $start = $transforms[2 - $transformPadd];
                    $end = $transforms[3 - $transformPadd];

                    if ($end != '') {
                        $pattern = sprintf(
                            '/%s(.*?)%s/',
                            preg_quote($start, '/'),
                            preg_quote($end, '/')
                        );
                    } else {
                        $pattern = sprintf(
                            '/%s(.*)/',
                            preg_quote($start, '/')
                        );
                    }

                    preg_match($pattern, $transformedValue, $matches);

                    $transformedValue = $matches[1] ?? null;

                } elseif($operation == "date_format") {

                    $transformedValue = Carbon::parse($transformedValue)->format($transforms[2 - $transformPadd]);

                } elseif($operation == "date_diff") {

                    $diffIn = $transforms[2 - $transformPadd];

                    if ($diffIn == "days") {

                        $transformedValue = Carbon::parse($transformedValue)->diffInDays();

                    } elseif ($diffIn == "months") {

                        $transformedValue = Carbon::parse($transformedValue)->diffInMonths();

                    } elseif ($diffIn == "years") {

                        $transformedValue = Carbon::parse($transformedValue)->diffInYears();

                    } elseif ($diffIn == "weeks") {

                        $transformedValue = Carbon::parse($transformedValue)->diffInWeeks();

                    } elseif ($diffIn == "hours") {

                        $transformedValue = Carbon::parse($transformedValue)->diffInHours();

                    }

                } elseif($operation == "if_elseif") {

                    preg_match_all('/\[(.*?)\]/', $transforms[2 - $transformPadd], $conditions);

                    $conditions = explode("@@", $conditions[1][0]);

                    foreach ($conditions as $condition) {

                        $logics = explode("**", $condition);

                        if (count($logics) != 3) {

                            $transformedValue = $logics[0];

                            break;

                        }

                        if (Helpers::calculate($transformedValue, $logics[0], $logics[1])) {

                            $transformedValue = $logics[2];

                            break;

                        }

                    }

                } elseif ($operation == "remove_words") {

                        $transformedValue = str_replace($transforms[2 - $transformPadd], "", $transformedValue);

                } elseif ($operation == "remove_words_regex") {

                        $transformedValue = preg_replace($transforms[2 - $transformPadd], "", $transformedValue);

                } elseif ($operation == "remove_chars") {

                        $transformedValue = str_replace(str_split($transforms[2 - $transformPadd]), "", $transformedValue);

                } elseif ($operation == "remove_chars_regex") {

                        $transformedValue = preg_replace($transforms[2 - $transformPadd], "", $transformedValue);

                } elseif ($operation == "remove_numbers") {

                        $transformedValue = preg_replace('/[0-9]+/', '', $transformedValue);

                } elseif ($operation == "remove_special_chars") {

                        $transformedValue = preg_replace('/[^A-Za-z0-9\-]/', '', $transformedValue);

                } elseif($operation == "append") {

                    $transformedValue = $transformedValue . $transforms[2 - $transformPadd];

                } elseif($operation == "prepend") {

                    $transformedValue = $transforms[2 - $transformPadd] . $transformedValue;

                } elseif($operation == "to_upper") {

                    $transformedValue = strtoupper($transformedValue);

                } elseif($operation == "to_lower") {

                    $transformedValue = strtolower($transformedValue);

                } elseif($operation == "to_title") {

                    $transformedValue = ucwords($transformedValue);

                } elseif($operation == "to_capitalize") {

                    $transformedValue = ucfirst($transformedValue);

                } elseif($operation == "to_slug") {

                    $transformedValue = Str::slug($transformedValue);

                } elseif($operation == "to_snake") {

                    $transformedValue = Str::snake($transformedValue);

                } elseif($operation == "to_kebab") {

                    $transformedValue = Str::kebab($transformedValue);

                } elseif($operation == "to_camel") {

                    $transformedValue = Str::camel($transformedValue);

                } elseif($operation == "to_studly") {

                    $transformedValue = Str::studly($transformedValue);

                } elseif($operation == "to_json") {

                    $transformedValue = json_encode($transformedValue);

                } elseif($operation == "to_array") {

                    $transformedValue = json_decode($transformedValue, true);

                } elseif($operation == "to_base64") {

                    $transformedValue = base64_encode($transformedValue);

                } elseif($operation == "from_base64") {

                    $transformedValue = base64_decode($transformedValue);

                } elseif($operation == "to_md5") {

                    $transformedValue = md5($transformedValue);

                } elseif($operation == "to_sha1") {

                    $transformedValue = sha1($transformedValue);

                } elseif($operation == "to_sha256") {

                    $transformedValue = hash('sha256', $transformedValue);

                } elseif($operation == "to_sha512") {

                    $transformedValue = hash('sha512', $transformedValue);

                } elseif($operation == "to_crc32") {

                    $transformedValue = hash('crc32', $transformedValue);

                } elseif($operation == "to_crc32b") {

                    $transformedValue = hash('crc32b', $transformedValue);

                } elseif($operation == "to_urlencode") {

                    $transformedValue = urlencode($transformedValue);

                } elseif($operation == "to_urldecode") {

                    $transformedValue = urldecode($transformedValue);

                } elseif($operation == "to_htmlentities") {

                    $transformedValue = htmlentities($transformedValue);

                } elseif($operation == "to_htmlspecialchars") {

                    $transformedValue = htmlspecialchars($transformedValue);

                } elseif($operation == "to_html_entity_decode") {

                    $transformedValue = html_entity_decode($transformedValue);

                } elseif($operation == "to_htmlspecialchars_decode") {

                    $transformedValue = htmlspecialchars_decode($transformedValue);

                } elseif ($operation == "trim") {

                    $transformedValue = trim($transformedValue);

                }  elseif ($operation == "to_int") {

                    $transformedValue = (int)$transformedValue;

                }  elseif ($operation == "to_bool") {

                    $transformedValue = filter_var($transformedValue, FILTER_VALIDATE_BOOLEAN);

                }  elseif ($operation == "to_string") {

                    $transformedValue = (string)$transformedValue;

                }

            }


        } catch (\Throwable $th) {

            //report to sentry
            \Sentry\captureException($th);

            $transformedValue = null;

        }


        return $transformedValue;
    }

    /**
     * Check if the buyer already bought this lead as CPL in this list
     *
     * @param string $buyer The buyer name.
     * @param string $phone The phone number.
     * @param int $list_id The list ID.
     * @return bool
     */
    public static function isBoughtCPLLead($buyer, $phone, $list_id)
    {

        try {
            if ($phone) {

                $paid_cpl_buyer_of_this_lead = PlatformData::where('list_id', $list_id)->where('phone', $phone)->where('revenue', '>', 0)->where('datas->lead_buyer', $buyer)->first();

                return $paid_cpl_buyer_of_this_lead ? true : false;

            }
        } catch (\Throwable $th) {
            //throw $th;
            //send to sentry
            \Sentry\captureException($th);
        }

        return false;
    }

    //check for spam
    public static function isSpam($payload, $buyer, $point = 'post') {

        $allowed_attempts = 2;

        try {
            $lead_first_name = $payload['datas']['first_name'] ?? null;
            $lead_last_name = $payload['datas']['last_name'] ?? null;

            //check if the lead was already posted to this buyer
            $posted_leads = PlatformDatas::where('list_id', $payload['list_id'])
            ->where('phone', $payload['phone'])
            ->where('datas->first_name', $lead_first_name)
            ->where('datas->last_name', $lead_last_name)
            ->where('datas', 'like', '%"'.$buyer.'_%')
            ->where(function($query) use ($buyer) {
                $query->whereJsonDoesntContainKey('platform_datas.datas->' . $buyer . '_status');
                $query->orWhereNotIn('datas->' . $buyer . '_status', ['Ineligible', 'Spam', 'Capped', 'Internal Duplicate']);
            })
            ->count();

            $is_spam = $posted_leads >= $allowed_attempts;

            if ($is_spam) {
                //save to log
                $log_save_point = $point == 'post' ? "direct_posts" : "grouped_pings";
                self::$leadLog[$log_save_point][$buyer]["spam"] = true;
                self::$leadLog[$log_save_point][$buyer]["allowed_attempts"] = $allowed_attempts;
                self::$leadLog[$log_save_point][$buyer]["posted_leads"] = $posted_leads;
            }

            return $is_spam;
        } catch (\Throwable $th) {
            //send to sentry
            \Sentry\captureException($th);
        }
        return false;
    }

    //is internal buyer
    public static function isInternalBuyer($buyer, $integrations) {

        $internal_buyer = false;

        foreach ($integrations as $integration) {
            if ($integration['name'] == $buyer && ($integration['internal_buyer'] ?? false)) {
                $internal_buyer = true;
                break;
            }
        }

        return $internal_buyer;

    }


}
