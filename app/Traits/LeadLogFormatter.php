<?php

namespace App\Traits;

trait LeadLogFormatter
{
    public function convertLogJsonToCSVColumn($logData)
    {
        $logData = json_decode($logData, true);


            /* if ($line['affm_lead_id'] == "HYXQLRVWLO") {
                dd($logData);
            } */



            foreach (($logData['grouped_pings'] ?? []) as $buyer => $buyer_data) {

                /* if ($buyer !== "tortexpert2_cpa") {
                    continue;
                } */
                //inject


                if (!empty($buyer_data['active'])) {


                    if (!empty($buyer_data['result']) && $buyer_data['result'] == "Sold") {

                        $line[$buyer . "_outcome"] = "Sold";

                    } else {

                        if (!empty($buyer_data['response'])) {

                            $apiOutcome = null;

                            //check if msg, message, error, default_response, result, status exists in order, then get the value
                            if (isset($buyer_data['response']['errors'])) {
                                $apiOutcome = Helpers::search_array_recursive($buyer_data['response'], "error");
                            } elseif (isset($buyer_data['response']['error'])) {
                                $apiOutcome = Helpers::search_array_recursive($buyer_data['response'], "error");
                            } elseif (isset($buyer_data['response']['message'])) {
                                $apiOutcome = Helpers::search_array_recursive($buyer_data['response'], "message");
                            } elseif (isset($buyer_data['response']['msg'])) {
                                $apiOutcome = Helpers::search_array_recursive($buyer_data['response'], "msg");
                            } elseif (isset($buyer_data['response']['default_response'])) {
                                $apiOutcome = Helpers::search_array_recursive($buyer_data['response'], "default_response");
                            } elseif (isset($buyer_data['response']['result'])) {
                                $apiOutcome = Helpers::search_array_recursive($buyer_data['response'], "result");
                            } elseif (isset($buyer_data['response']['status'])) {
                                $apiOutcome = Helpers::search_array_recursive($buyer_data['response'], "status");
                            } else {

                                //check if the word duplicate exist in the response
                                $searchTerm = strtolower(json_encode($buyer_data['response']));

                                if (strpos($searchTerm, 'duplicate') !== false || strpos($searchTerm, 'exist') !== false) {
                                    $apiOutcome = "Duplicate";
                                } elseif (strpos($searchTerm, 'spam') !== false) {
                                    $apiOutcome = "Spam";
                                } elseif (strpos($searchTerm, 'invalid') !== false || strpos($searchTerm, 'not valid') !== false) {
                                    $apiOutcome = "Invalid lead data sent";
                                } else {
                                    $apiOutcome = "Rejected From API";
                                }
                            }

                            $line[$buyer . "_outcome"] = $apiOutcome;

                        }

                       if (isset($buyer_data['filters']) && !$buyer_data['filters']['passed']) {


                           $line[$buyer . "_outcome"] = "Filtered";

                           foreach ($buyer_data['filters']['as'] as $fieldKey => $fieldValue) {

                                $filterValues = [];

                                $filterValues[$fieldKey] = $fieldValue;

                                $isPassed = PlatformDataSaveService::checkFilters($filterValues, $logData['original_payload']);

                                $line[$buyer . "_filter_" . $fieldKey] = $isPassed ? "Passed" : "Failed";

                           }

                       } elseif (isset($buyer_data['filters']) && $buyer_data['filters']['passed']) {

                        foreach ($buyer_data['filters']['as'] as $fieldKey => $fieldValue) {

                            $filterValues = [];

                            $filterValues[$fieldKey] = $fieldValue;

                            $isPassed = PlatformDataSaveService::checkFilters($filterValues, $logData['original_payload']);

                            $line[$buyer . "_filter_" . $fieldKey] = $isPassed ? "Passed" : "Failed";

                       }

                        if (isset($buyer_data['spam']) && $buyer_data['spam'] == true) {
                            $line[$buyer . "_outcome"] = "Spam";
                        } elseif (isset($buyer_data['internal_duplicate']) && $buyer_data['internal_duplicate'] == true) {
                            $line[$buyer . "_outcome"] = "Internal Duplicate";
                        } elseif(isset($buyer_data['caps']) && !$buyer_data['caps']['passed']) {

                            $line[$buyer . "_outcome"] = "Capped";

                        } else {

                            // dd($buyer_data);

                        }


                       } else {

                        if (isset($buyer_data['spam']) && $buyer_data['spam'] == true) {
                            $line[$buyer . "_outcome"] = "Spam";
                        } elseif (isset($buyer_data['internal_duplicate']) && $buyer_data['internal_duplicate'] == true) {
                            $line[$buyer . "_outcome"] = "Internal Duplicate";
                        } elseif(isset($buyer_data['caps']) && !$buyer_data['caps']['passed']) {

                            $line[$buyer . "_outcome"] = "Capped";

                        } else {

                            // dd($buyer_data);

                        }

                       }

                    }

                } else {

                    $line[$buyer . "_outcome"] = "Inactive";

                }


            }


            foreach (($logData['direct_posts'] ?? []) as $buyer => $buyer_data) {

                /* if ($buyer !== "tortexpert2_cpa") {
                    continue;
                } */
                //inject


                if (!empty($buyer_data['active'])) {


                    if (!empty($buyer_data['result']) && $buyer_data['result'] == "Sold") {

                        $line[$buyer . "_outcome"] = "Sold";

                    } else {

                        if (!empty($buyer_data['response'])) {

                            $apiOutcome = null;

                            //check if msg, message, error, default_response, result, status exists in order, then get the value
                            if (isset($buyer_data['response']['errors'])) {
                                $apiOutcome = Helpers::search_array_recursive($buyer_data['response'], "error");
                            } elseif (isset($buyer_data['response']['error'])) {
                                $apiOutcome = Helpers::search_array_recursive($buyer_data['response'], "error");
                            } elseif (isset($buyer_data['response']['message'])) {
                                $apiOutcome = Helpers::search_array_recursive($buyer_data['response'], "message");
                            } elseif (isset($buyer_data['response']['msg'])) {
                                $apiOutcome = Helpers::search_array_recursive($buyer_data['response'], "msg");
                            } elseif (isset($buyer_data['response']['default_response'])) {
                                $apiOutcome = Helpers::search_array_recursive($buyer_data['response'], "default_response");
                            } elseif (isset($buyer_data['response']['result'])) {
                                $apiOutcome = Helpers::search_array_recursive($buyer_data['response'], "result");
                            } elseif (isset($buyer_data['response']['status'])) {
                                $apiOutcome = Helpers::search_array_recursive($buyer_data['response'], "status");
                            } else {

                                //check if the word duplicate exist in the response
                                $searchTerm = strtolower(json_encode($buyer_data['response']));

                                if (strpos($searchTerm, 'duplicate') !== false || strpos($searchTerm, 'exist') !== false) {
                                    $apiOutcome = "Duplicate";
                                } elseif (strpos($searchTerm, 'spam') !== false) {
                                    $apiOutcome = "Spam";
                                } elseif (strpos($searchTerm, 'invalid') !== false || strpos($searchTerm, 'not valid') !== false) {
                                    $apiOutcome = "Invalid lead data sent";
                                } else {
                                    $apiOutcome = "Rejected From API";
                                }
                            }

                            $line[$buyer . "_outcome"] = $apiOutcome;

                        }

                       if (isset($buyer_data['filters']) && !$buyer_data['filters']['passed']) {


                           $line[$buyer . "_outcome"] = "Filtered";

                           foreach ($buyer_data['filters']['as'] as $fieldKey => $fieldValue) {

                                $filterValues = [];

                                $filterValues[$fieldKey] = $fieldValue;

                                $isPassed = PlatformDataSaveService::checkFilters($filterValues, $logData['original_payload']);

                                $line[$buyer . "_filter_" . $fieldKey] = $isPassed ? "Passed" : "Failed";

                           }

                       } elseif (isset($buyer_data['filters']) && $buyer_data['filters']['passed']) {

                        foreach ($buyer_data['filters']['as'] as $fieldKey => $fieldValue) {

                            $filterValues = [];

                            $filterValues[$fieldKey] = $fieldValue;

                            $isPassed = PlatformDataSaveService::checkFilters($filterValues, $logData['original_payload']);

                            $line[$buyer . "_filter_" . $fieldKey] = $isPassed ? "Passed" : "Failed";

                       }

                        if (isset($buyer_data['spam']) && $buyer_data['spam'] == true) {
                            $line[$buyer . "_outcome"] = "Spam";
                        } elseif (isset($buyer_data['internal_duplicate']) && $buyer_data['internal_duplicate'] == true) {
                            $line[$buyer . "_outcome"] = "Internal Duplicate";
                        } elseif(isset($buyer_data['caps']) && !$buyer_data['caps']['passed']) {

                            $line[$buyer . "_outcome"] = "Capped";

                        } else {

                            // dd($buyer_data);

                        }


                       } else {

                        if (isset($buyer_data['spam']) && $buyer_data['spam'] == true) {
                            $line[$buyer . "_outcome"] = "Spam";
                        } elseif (isset($buyer_data['internal_duplicate']) && $buyer_data['internal_duplicate'] == true) {
                            $line[$buyer . "_outcome"] = "Internal Duplicate";
                        } elseif(isset($buyer_data['caps']) && !$buyer_data['caps']['passed']) {

                            $line[$buyer . "_outcome"] = "Capped";

                        } else {

                            // dd($buyer_data);

                        }

                       }

                    }

                } else {

                    $line[$buyer . "_outcome"] = "Inactive";

                }


            }
            /* if($line['affm_lead_id'] == "HYXQLRVWLO") {
                dd($line);
                dump($buyer);
                dd($buyer_data);
            } */

            //unset log data
            unset($line['log_data']);


            // dd($line);


            $newCSVData[] = $line;
    }
}