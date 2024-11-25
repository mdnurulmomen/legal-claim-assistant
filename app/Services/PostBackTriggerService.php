<?php

namespace App\Services;

use App\Models\GlobalPostback;
use Illuminate\Support\Facades\Http;

class PostBackTriggerService {

    public function trigger($data)
    {
        //get the postbacks
        $postbacks = GlobalPostback::where('postback_event', 'on_retainer_added')->get();

        if( $postbacks->isEmpty() ){
            return;
        }

        $postback_url = null;

        foreach ($postbacks as $postback) {

            $skip_postback = false;

            try {

                //check for conditions
                if ($postback->conditions) {

                    $conditions = json_decode($postback->conditions, true);

                    foreach ($conditions as $field => $condition) {
                          $logic = $condition['logic'] ?? "==";
                          $logic_value = $condition['value'] ?? null;

                          //get the target value
                          $target_value = null;


                          //if it's not dynamic field name
                          if ((substr($field, 0, 1) === "{" && substr($field, -1) === "}")) {

                            //dynamic field name
                            $field = str_replace("{", "", $field);
                            $field = str_replace("}", "", $field);
                            //get the value of the field
                            if ($field == "buyer_type" && isset($data['payload']['datas']['lead_buyer'])) {
                                //get the buyer info
                                $buyer = $this->buyerInfo($data['platform_list']['integrations'], $data['payload']['datas']['lead_buyer']);
                                if (!empty($buyer['buyer_type'])) {
                                    $target_value = $buyer['buyer_type'];
                                }
                            }


                          } else {
                                //if revenue, payout or affiliate_payout is the target value
                                if (in_array($field, ['revenue', 'payout', 'affiliate_payout', 'phone', 'email', 'created_at'])) {

                                    $target_value = $data['payload'][$field] ?? 0;

                                    if ($field == "affiliate_payout") {
                                            $target_value = ($data['payload']['revenue'] ?? 0) - ($data['payload']['payout'] ?? 0);
                                    }

                                } elseif (isset($data['payload']['datas'][$field])) {

                                    $target_value = $data['payload']['datas'][$field];

                                }
                          }

                          info(json_encode($data['payload']['datas']));

                          info("target value: " . $target_value);
                          info("logic: " . $logic);
                          info("logic value: " . $logic_value);

                          //check if passed
                          $check = $this->calculate($target_value, $logic, $logic_value);
                          if (! $check) {
                            $skip_postback = true;
                            break;
                          }
                    }

                }

                // info("postback url: " . $target_value);
                // info("skip postback: " . $skip_postback ? "true" : "false");

                //postback Url
                if ($postback->url && !$skip_postback) {
                    $postback_url = preg_replace_callback('/{([^{}]+)}/', function ($matches) use (&$skip_postback, $data, $postback) {

                        $parameter = $matches[1];

                        $logic = explode(":", $parameter);

                        if (count($logic) > 1) {

                            $parameter = $logic[0];
                            $logic = $logic[1];
                        } else {

                            $logic = null;
                        }

                        if ($parameter && (empty($data['payload'][$parameter]) && empty($data['payload']['datas'][$parameter])) && ($parameter != "monetize") && ($parameter != "lead_buyer")) {

                            $skip_postback = true;
                        }

                        if ($logic && $parameter == "monetize") {

                            //we have a setting to get the value of monetize
                            //get the value of monetize
                            if($logic == "revenue") {
                                $parameter = $data['payload']['revenue'] ?? 0;
                            } elseif($logic == "payout") {
                                $parameter = $data['payload']['payout'] ?? 0;
                            } elseif($logic == "affiliate_payout") {
                                $parameter = ($data['payload']['revenue'] ?? 0) - ($data['payload']['payout'] ?? 0);
                            }

                        } elseif (isset($new_saved_data['datas'][$parameter])) {

                            $parameter = $data['payload']['datas'][$parameter];

                            //if logic contains a "%", then calculate the percentage
                            if ($logic && strpos($logic, '%') !== false) {

                                $parameter = $parameter * (intval($logic) / 100);
                            }

                        } else {

                                $parameter = null;
                        }

                        return $parameter;

                    }, $postback->url);
                }

                if ($skip_postback) {
                    continue;
                }

                info($postback_url);
                // file_get_contents($postback_url);
                $res = Http::get($postback_url);
                // if(! $res->ok()) {
                //     \Sentry\captureMessage($res->body());
                // }

            } catch (\Throwable $th) {
                // throw $th;
                \Sentry\captureMessage($th->getMessage());
            }
        }

        //return
        return true;
    }

    public function buyerInfo($integrations, $buyer)
    {
        foreach ($integrations as $int) {
            if ($int['name'] == $buyer) {
                return $int;
            }
        }

        return null;
    }

    public function calculate($subject, string $operator, $target): bool
    {
        if ($operator == '==') {
            return $subject == $target;
        } elseif ($operator == '!=') {
            return $subject != $target;
        } elseif ($operator == '>=') {
            return $subject >= $target;
        } elseif ($operator == '<=') {
            return $subject <= $target;
        } elseif ($operator == '>') {
            return $subject > $target;
        } elseif ($operator == '<') {
            return $subject < $target;
        } elseif ($operator == 'contains') {
            return strpos($subject, $target) !== false;
        } elseif ($operator == 'not_contains') {
            return strpos($subject, $target) === false;
        } elseif ($operator == 'starts_with') {
            return strpos($subject, $target) === 0;
        } elseif ($operator == 'ends_with') {
            return strpos($subject, $target) === strlen($subject) - strlen($target);
        } elseif ($operator == 'between_num') {
            return $subject >= $target[0] && $subject <= $target[1];
        } elseif ($operator == 'not_between_num') {
            return $subject < $target[0] || $subject > $target[1];
        } elseif ($operator == 'between_date') {
            return strtotime($subject) >= strtotime($target[0]) && strtotime($subject) <= strtotime($target[1]);
        } elseif ($operator == 'not_between_date') {
            return strtotime($subject) < strtotime($target[0]) || strtotime($subject) > strtotime($target[1]);
        } elseif ($operator == 'more_than_date') {
            return strtotime($subject) > strtotime($target);
        } elseif ($operator == 'less_than_date') {
            return strtotime($subject) < strtotime($target);
        } elseif ($operator == 'in') {
            return in_array($subject, explode(',', $target));
        } elseif ($operator == 'not_in') {
            return !in_array($subject, explode(',', $target));
        } elseif ($operator == 'is_empty') {
            return empty($subject);
        } elseif ($operator == 'is_not_empty') {
            return !empty($subject);
        } elseif ($operator == 'is_null') {
            return is_null($subject);
        } elseif ($operator == 'is_not_null') {
            return !is_null($subject);
        } elseif ($operator == 'is_true') {
            return $subject == true;
        } elseif ($operator == 'is_false') {
            return $subject == false;
        } elseif ($operator == 'is_numeric') {
            return is_numeric($subject);
        } elseif ($operator == 'is_not_numeric') {
            return !is_numeric($subject);
        } elseif ($operator == 'is_string') {
            return is_string($subject);
        }

        //unknown operator
        //report to sentry
        \Sentry\captureMessage('Unknown operator: ' . $operator);

        return false;
    }

    /**
     * Helper function to check numeric range.
     */
    private function isBetween($subject, array $target): bool
    {
        return $subject >= $target[0] && $subject <= $target[1];
    }

    /**
     * Helper function to check date range.
     */
    private function isBetweenDate($subject, array $target): bool
    {
        $subjectTime = strtotime($subject);
        $startTime = strtotime($target[0]);
        $endTime = strtotime($target[1]);

        return $subjectTime >= $startTime && $subjectTime <= $endTime;
    }

    /**
     * Handle unknown operators by logging and returning false.
     */
    private function handleUnknownOperator(string $operator): bool
    {
        \Sentry\captureMessage('Unknown operator: ' . $operator);
        return false;
    }

}
