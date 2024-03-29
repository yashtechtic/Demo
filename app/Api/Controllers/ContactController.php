<?php

namespace App\Api\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Connection;
use App\Api\Requests\SendRequest;
use App\Api\Requests\AcknowledgeRequest;
use App\Api\Requests\PenddingRequest;
use App\Notifications\ArtNotification;
use App\Models\User;

/**
 * @resource Contact
 */
class ContactController extends Controller
{
    /**
     * Get contacts
     */
    public function sendRequest(SendRequest $request)
    {
        $user_id = \Auth::id();
        $user = \Auth::user();
        $connected = Connection::where('sender_id' , $user_id)
                        ->where('receiver_id', $request->get('receiver_id'))
                        ->count();
        $flag = $request->get('flag');

        if ($flag == 'follow') {
            if($connected == 0){
                $connection = Connection::create([
                    'sender_id' => $user_id,
                    'receiver_id' => $request->get('receiver_id'),
                    'status' => 'pendding'
                ]);
                
                $userNotification =User::find($request->get('receiver_id'));

                $details = [
                            'user_id' => $userNotification->id,
                            'sender_id' => $user_id,
                            'title' => 'Sent Request',
                            'msg' => $user->name .' sent you request.',
                        ];

                $userNotification->notify(new ArtNotification($details));

                return response()->json([
                    'status_code' => 200,
                    'data'        => $connection,
                    'message'     => 'Request sent successfully.'
                ], 200);
            }else{
                return response()->json([
                    'status_code' => 400,
                     'message'     => 'Already sent Request.'
                ], 400);
            }
        }
        elseif ($flag == 'unfollow') {

            $connected = Connection::where('sender_id' , $user_id)
                            ->where('status', 'accepted')
                            ->where('receiver_id', $request->get('receiver_id'))->first();

            // $connected_reverce = Connection::where('sender_id' , $request->get('receiver_id'))
            //                 ->where('status', 'accepted')
            //                 ->where('receiver_id', $user_id)->first();

            if (!empty($connected)) {
                $connected->forceDelete();
                // if(!empty($connected_reverce)){
                //     $connected_reverce->forceDelete();
                // }
                return response()->json([
                    'status_code' => 200,
                     'message'     => 'unfollow successfully.'
                ], 200);
            }else{
                return response()->json([
                    'status_code' => 400,
                     'message'     => 'no request found'
                ], 400);
            }
        }
    }

    public function acknowledgeRequest(AcknowledgeRequest $request)
    {
        $user_id = \Auth::id();
        $user = \Auth::user();
        $id = $request->get('request_id');
        if( ($request->get('status') == 'accepted') || ($request->get('status') == 'rejected') ){
            $connection = Connection::where('receiver_id' , $user_id)
                    ->where('status','pendding')
                    ->find($id);
            if (!empty($connection)) {
                $connection->status = $request->get('status');
                $connection->save();
                $connection = Connection::with('followerUser')->find($request->get('request_id'));
                if ( $request->get('status') == 'accepted' ) {

                    $details = [
                        'user_id' => $connection->sender_id,
                        'sender_id' => $user_id,
                        'title' => 'Request Approved',
                        'msg' => $user->name .' accepted your request.',
                    ];
                    // Connection::create([
                    //     'sender_id' => $user_id,
                    //     'receiver_id' =>  $connection->sender_id,
                    //     'status' => 'accepted'
                    // ]);
                    $userNotification =User::find($connection->sender_id);
                    $userNotification->notify(new ArtNotification($details));

                }
                return response()->json([
                    'status_code' => 200,
                    'data'        => $connection,
                ]);
            } else {
                return response()->json([
                    'status_code' => 400,
                     'message'     => 'Not found pending request.'
                ], 400);
            }
        } else if ($request->get('status') == 'cancel') {
            $connection = Connection::find($request->get('request_id'));
            if (!empty($connection)) {
                $connection->forceDelete();
                return response()->json([
                    'status_code' => 200,
                    'message'     => 'Request Canceled',
                ]);
            }else{
                return response()->json([
                    'status_code' => 400,
                     'message'     => 'Request Not found.'
                ], 400);
            }
        }
    }

    public function penddingRequest(PenddingRequest $request)
    {
        $user_id = \Auth::id();
        $flag =$request->get('flag');
        if ($flag == 'sent'){
            $connection = Connection::where('sender_id' , $user_id)
                        ->with('followingUser')
                        ->where('status','pendding')
                        ->get();

        }else if ($flag == 'recevied'){
            $connection = Connection::where('receiver_id' , $user_id)
                        ->with('followerUser')
                        ->where('status','pendding')
                        ->get();
        }
        if(!empty($connection)){
            return response()->json([
                'status_code' => 200,
                'data'        => $connection,
            ], 200);

        }

        return response()->json([
            'status_code' => 400,
             'message'     => 'Not found any request.'
        ], 400);




    }
    /**
     * Update Contact
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Delete Contact
     * @response {
     *  "status_code" : "200",
     *  "message" : "Contact successfully deleted."
     * }
     */
    public function destroy($id)
    {
        \Auth::user()->contacts()->detach($id);
        return response()->json(['status_code' => 200, 'message' => 'Contact successfully deleted.'], 200);
    }

    /**
     * Get Phone Contacts
     * @response {
     *  "status_code" : "200",
     *  "data" : "$contact",
     *  "message" : "Contact successfully listed."
     * }
     */
    public function getPhoneContacts(GetPhoneContactsRequest $request)
    {
        $user            = \Auth::user();
        $contact_numbers = explode(",", $request->get('contact_numbers'));
        $contact_numbers = array_map(function ($number) {
            return substr($number, -10);
        }, $contact_numbers);

        $user    = \Auth::user();
        $contact = \Auth::user()->whereIn('phone', $contact_numbers)->where('id', '!=', $user->id)->get();

        return response()->json([
            'status_code' => 200,
            'data'        => $contact,
            //'message'     => 'Contact successfully added.',
        ], 200);
    }
}
