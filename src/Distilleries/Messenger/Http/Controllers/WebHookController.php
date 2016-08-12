<?php
/**
 * Created by PhpStorm.
 * User: mfrancois
 * Date: 31/07/2016
 * Time: 19:09
 */

namespace Distilleries\Messenger\Http\Controllers;

use Distilleries\Messenger\Contracts\MessengerReceiverContract;
use Illuminate\Routing\Controller;
use Log;
use Illuminate\Http\Request;

class WebHookController extends Controller
{
    public function getValidHook(Request $request)
    {
        $hub          = $request->input('hub_mode');
        $verify_token = $request->input('hub_verify_token');

        if ($hub === 'subscribe' && urldecode($verify_token) === config('messenger.validation_token')) {
            return response($request->get('hub_challenge'));
        } else {
            return abort(403);
        }
    }


    public function postMessage(Request $request, MessengerReceiverContract $messenger)
    {
        $object = $request->input('object');

        if (empty($object)) {
            return abort(403);
        }

        if ($object == 'page') {
            $entry = $request->input('entry');
            $entry = json_decode(json_encode($entry), false);

            if (empty($entry) || !is_array($entry)) {
                return abort(403);
            }

            $result = "";

            foreach ($entry as $pageEntry) {

                if (!is_array($pageEntry->messaging)) {
                    continue;
                }

                foreach ($pageEntry->messaging as $messagingEvent) {

                    if (!empty($messagingEvent->optin)) {
                        $result .= $messenger->receivedAuthentication($messagingEvent);
                    } else {
                        if (!empty($messagingEvent->message)) {
                            $result .= $messenger->receivedMessage($messagingEvent);
                        } else {
                            if (!empty($messagingEvent->delivery)) {
                                $result .= $messenger->receivedDeliveryConfirmation($messagingEvent);
                            } else {
                                if (!empty($messagingEvent->postback)) {
                                    $result .= $messenger->receivedPostback($messagingEvent);
                                } else {
                                    $result .= $messenger->defaultHookUndefinedAction($messagingEvent);
                                }
                            }
                        }
                    }
                }
            }
        }

       return response($result);
    }

}