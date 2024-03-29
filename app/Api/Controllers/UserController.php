<?php
namespace App\Api\Controllers;

use App\Api\Requests\ChangePasswordRequest;
use App\Api\Requests\RegisterRequest;
use App\Api\Requests\LoginRequest;
use App\Api\Requests\ForgetPasswordRequest;
use App\Api\Requests\NearByUserRequest;
use App\Api\Requests\SetPasswordRequest;
use App\Api\Requests\SocialRegisterRequest;
use App\Api\Requests\UpdateRegisterRequest;
use App\Api\Requests\loginWithAppleRequest;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Connection;
use App\Models\UserStatus;
use App\Models\Role;
use App\Notifications\ForgetPasswordNotification;
use App\Notifications\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Laravel\Socialite\Facades\Socialite;
use App\Models\LinkedSocialAccount;
use DB;
use Auth;

/**
 * @resource Auth
 */
class UserController extends Controller
{
    /**
     * Login
     * @response {
     *  "status_code" : "200",
     *  "data" : "$user",
     *  "token" : "$token"
     * }
     */
    public function authenticate(LoginRequest $request)
    {
        $credentials = $request->only('email', 'password');
        try {
            if (!$token = JWTAuth::attempt($credentials)) {
                return response()->json([
                    'status_code'   => 400,
                    'message'       => 'Incorrect email address or password'], 400);
            }
            $user = \Auth::user();
            $update_data = $request->only(['email', 'password', 'device_token', 'device']);
            $user->device_token = $update_data['device_token'];
            $user->device = $update_data['device'];
            $user->save();
            return response()->json([
                'status_code' => '200',
                'data'        => $user,
                'token'       => $token,
            ]);
        } catch (JWTException $e) {
            return response()->json(['status_code' => 500, 'message' => 'Could not create token'], 500);
        }
    }
    /**
     * Register
     * @response {
     *  "status_code" : "200",
     *  "data" : "$user",
     *  "token" : "$token"
     * }
     */
    public function register(RegisterRequest $request)
    {
        $insert_data = $request->only(['name', 'email', 'password', 'phone', 'dob', 'country', 'profile_pic', 'address', 'latitude', 'longitude', 'gender', 'device_token', 'device']);

        if (isset($insert_data['phone']) && !empty($insert_data['phone'])) {
            $insert_data['phone'] = substr($insert_data['phone'], -10);
        }
        if (isset($insert_data['dob'])) {
            $insert_data['dob'] = date('Y-m-d', strtotime($insert_data['dob']));
        }

        $insert_data['password'] = Hash::make($insert_data['password']);
        $user                    = User::create($insert_data);
        $user = User::find($user->id);
        $user->roles()->sync($request->role_id);
        $token = JWTAuth::fromUser($user);

        return response()->json(['status_code' => 200, 'data' => $user, 'token' => $token], 200);
    }
    /**
     * Forget Password
     */
    public function forgetPassword(ForgetPasswordRequest $request)
    {
        $user = User::where('email', $request->get('email'))->first();
        if (!$user) {
            return response()->json([
                'status_code' => 400,
                'message'     => 'Entered email address not found.',
            ], 400);
        } else {
            $user->update(['remember_token' => str_random(10)]);
            $user['password'] = str_random(6);
            $hash_password    = Hash::make($user['password']);
            $user->notify(new ForgetPasswordNotification($user));
            $password_update = User::where('id', $user->id)->update(['password' => $hash_password]);
            return response()->json([
                'status_code' => '200',
                'message'     => 'Please check your email address to reset password.',
            ]);
        }
    }
    /**
     * Get Authenticated User
     */
    public function getAuthenticatedUser(Request $request)
    {
        $user = \Auth::user();
        $radius = $request->get('radius', 100);
        $users = User::nearBy(['longitude'=> $user->longitude, 'latitude'=>
            $user->latitude], $radius)
        ->has('video')->get();

        return response()->json([
            'status_code'  => 200,
            'data'         => ['users' => $user,'nearby_users' => $users->count()]
        ], 200);
    }
    /**
     * Update Profile
     * @response {
     *  "status_code" : "200",
     *  "data" : "$user",
     *  "token" : "$token"
     * }
     */
    public function updateProfile(Request $request)
    {
        $user = \Auth::user();
        if ($user) {
            $fields = ['name', 'phone', 'dob', 'country', 'profile_pic', 'address', 'latitude', 'longitude', 'gender', 'device_token', 'device' , 'about', 'cover_img', 'role_id'];
           /*
            $validatedData = $request->validate([
                'name' => 'sometimes|required',
                'phone' => 'sometimes|required',
                'dob' => 'sometimes|required',
            ]);*/

            $validated = [];

            foreach ($fields as $key => $field) {
                switch ($field) {
                    case 'dob':
                    $validated[$field] = 'sometimes|required|nullable|before:today';
                    break;
                    case 'phone':
                    $validated[$field] = 'sometimes|required|numeric|digits:10';
                    break;
                    case 'role_id':
                    $validated[$field] = 'sometimes';
                    break;

                    default:
                    $validated[$field] = 'sometimes|required';
                    break;
                }
            }

            $request->validate($validated, [
                'phone.digits' => 'Please enter valid mobile number'
            ]);

            foreach ($fields as $key => $field) {
                if ($request->exists($field)) {
                    switch ($field) {
                        case 'dob':
                        $user->$field = date('Y-m-d', strtotime($request->dob));
                        break;
                        case 'role_id':
                        $user->roles()->sync($request->role_id);
                        break;

                        default:
                        $user->$field = $request->$field;
                        break;
                    }
                }
            }

            $user->save();
            return response()->json([
                'status_code'   => 200,
                'data'          => $user,
                'message'       => 'Profile details updated successfully.'
            ]);

        } else {
            return response()->json([
                'status_code' => 401,
                'message'     => 'Invalid user.',
            ], 401);
        }
    }
    /**
     * Change Password
     * @response {
     *  "status_code" : "200",
     *  "data" : "$user",
     *  "token" : "$token"
     * }
     */
    public function changePassword(ChangePasswordRequest $request)
    {
        $user = \Auth::user();
        if ($user) {
            if (Hash::check($request->old_password, $user->password)) {
                $user->password = Hash::make($request->password);
                $user->save();
                return response()->json(['status_code' => 200, 'message' => 'Password has been changed successfully.'], 200);
            } else {
                return response()->json(['status_code' => 400, 'message' => 'Entered current password is incorrect.'], 400);
            }

        } else {
            return response()->json([
                'status_code' => 401,
                'message'     => 'Invalid user.',
            ], 401);
        }
    }

    /**
     * Social Media Register
     * @response {
     *  "status_code" : "200",
     *  "data" : "$user",
     *  "token" : "$token"
     * }
     */
    public function socialMediaRegister(SocialRegisterRequest $request)
    {
        $provider = $request->provider;
        $provider_id = $request->access_token;
        $access_token_secret = $request->access_token_secret;

        if($provider == 'twitter'){
            $userData = Socialite::driver($provider)->userFromTokenAndSecret($provider_id, $access_token_secret);
        }else{
            $userData = Socialite::driver($provider)->userFromToken($provider_id);
        }


        $social_id = $userData->getId();
        $email=$userData->getEmail();
        $name=$userData->getName();

        $linkedSocialAccount =LinkedSocialAccount::where('provider_name', $provider)
        ->where('provider_id', $social_id)->first();

        if ($linkedSocialAccount) {
            $user = $linkedSocialAccount->user;
        } else {
            $user = User::where('email', $email)->whereNotNull('email')->first();
            if (!$user) {
                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                ]);
                $user = User::find($user->id);
            }
            $user->linkedSocialAccounts()->create([
                'provider_id' => $social_id,
                'provider_name' => $provider,
            ]);
        }

        $token = JWTAuth::fromUser($user);
        return response()->json(['status_code' => 200, 'data' => $user, 'token' => $token], 200);
    }


    /**
     * Reset Password
     */
    public function setPassword(SetPasswordRequest $request)
    {
        $user = User::where(['email' => $request->get('email'), 'remember_token' => $request->get('token')])->first();
        if (!$user) {
            throw new \Exception("Sorry, please try again later.", 400);
        } else {
            $user->update(['remember_token' => '', 'password' => bcrypt($request->get('password'))]);
            $user['subject'] = 'Reset Password';
            $user['meg']     = 'Your password successfully updated.';
            $user->notify(new UserNotification($user));
            return response()->json([
                'status_code' => '200',
                'message'     => 'Your password successfully updated.',
            ]);
        }
    }
    /**
     * Near By User
     * @response {
     *  "status_code" : "200",
     *  "data" : "$user",
     * }
     */
    public function nearByUser(NearByUserRequest $request)
    {
        $post = $request->all();
        $user = \Auth::user();
        $latlng  = $request->only(['longitude', 'latitude']);
        $radius  = $request->get('radius', 1000);
        $users = User::nearBy($latlng, $radius)->has('video')->get();

        return response()->json([
            'status_code' => 200,
            'data'        => $users,
        ]);
    }

    public function getProfile(Request $request)
    {

        $user_id = \Auth::id();

        $other_user_id = $request->input('other_user_id');

        if(isset($other_user_id) && !empty($other_user_id)){
            $user_id = $other_user_id;
        }
        $block_user_ids = UserStatus::where('user_id',$user_id)->pluck('block_user_id');
        $user = User::with(['connection_status' => function($query) use ($block_user_ids){
           $query->whereNotIn('sender_id',$block_user_ids);
       }])
        ->with(['following' => function($query) use ($block_user_ids){
            $query->with(['followingUser'  => function($query) use ($block_user_ids){
               $query->whereNotIn('id',$block_user_ids);
           }])->whereNotIn('receiver_id',$block_user_ids);
        }])
        ->with(['follower'  => function($query) use ($block_user_ids){
            $query->with(['followerUser'  => function($query) use ($block_user_ids){
                $query->whereNotIn('id',$block_user_ids);
            }])
            ->whereNotIn('sender_id',$block_user_ids);
        }])
        ->find($user_id);

        $user['connections'] = $user['connection_status'];
        unset($user['connection_status']);

        $user['pendding_sent_request']  = Connection::where('sender_id' , $user_id)
        ->where('status','pendding')
        ->count();

        $user['pendding_received_request'] = Connection::where('receiver_id' , $user_id)
        ->where('status','pendding')
        ->count();

        return response()->json([
            'status_code' => 200,
            'data'        => $user,
        ], 200);
    }

    public function getProfilecount(Request $request)
    {
       $user_id = \Auth::id();

        $other_user_id = $request->input('other_user_id');

        if(isset($other_user_id) && !empty($other_user_id)){
            $user_id = $other_user_id;
        }
        $block_user_ids = UserStatus::where('user_id',$user_id)->pluck('block_user_id');
        $user = "";
        if($request['type'] == 'following'){
            $user =  User::with(['following' => function($query) use ($block_user_ids){
            $query->with(['followingUser' => function($query) use ($block_user_ids){
                   $query->whereNotIn('id',$block_user_ids);
               }])->whereNotIn('receiver_id',$block_user_ids);
            }])->find($user_id);
            foreach($user['following'] as $key => $values){

                foreach($values['followingUser'] as $key_follow => $values_following){
                    //dd($values['followingUser']['id']);
                     $block_ids = UserStatus::where('user_id',$values['followingUser']['id'])->pluck('block_user_id');
                   $data= User::withCount(['following' => function($query) use ($block_ids){
                        $query->with(['followingUser' => function($query) use ($block_ids){
                               $query->whereNotIn('id',$block_ids);
                           }])->whereNotIn('receiver_id',$block_ids);
                        }])->find($user_id);
                   
                   $user['following'][$key]['followingUser']['following_count'] =Connection::where('receiver_id',$values['followingUser']['id'])->where('status','accepted')->count();
                }
            }
        }else if($request['type'] == 'follower'){
          
            $user = User::With(['follower' => function($query) use ($block_user_ids){
                $query->with(['followerUser' => function($query) use ($block_user_ids){
                   $query->whereNotIn('id',$block_user_ids);
               }])->whereNotIn('sender_id',$block_user_ids);
            }])->find($user_id);
            foreach($user['follower'] as $key => $values){

                foreach($values['followerUser'] as $key_follower => $values_follower){
                  
                     $block_ids = UserStatus::where('user_id',$values['followerUser']['id'])->pluck('block_user_id');
                   $data= User::withCount(['follower' => function($query) use ($block_ids){
                        $query->with(['followerUser' => function($query) use ($block_ids){
                               $query->whereNotIn('id',$block_ids);
                           }])->whereNotIn('sender_id',$block_ids);
                        }])->find($user_id);
                
                    $user['follower'][$key]['followerUser']['follower_count']  = Connection::where('sender_id',$values['followerUser']['id'])->where('status','accepted')->count();
                  
                }
            }
        }else{
            return response()->json([
                'status_code' => 400,
                'message'        => "Sorry, Data not available.",
            ]); 
        }
        $user['connections'] = $user['connection_status'];
        unset($user['connection_status']);

        $user['pendding_sent_request']  = Connection::where('sender_id' , $user_id)
        ->where('status','pendding')
        ->count();

        $user['pendding_received_request'] = Connection::where('receiver_id' , $user_id)
        ->where('status','pendding')
        ->count();

        
        return response()->json([
            'status_code' => 200,
            'data'        => $user,
        ], 200);

    }

    public function allUser(NearByUserRequest $request)
    {
        $user_id = \Auth::id();
        $latlng  = $request->only(['longitude', 'latitude']);
        $radius  = $request->get('radius', 1000);

        $user = User::nearBy($latlng, $radius)->where('id', '<>', $user_id)
        ->whereHas('roles', function ($q) {
            $q->whereNotIn('name', ['Art Lover', 'Admin']);
        })->whereNotIn('id', DB::table('user_status')
        ->where('user_id', $user_id)
        ->pluck('block_user_id'))
        ->with('following.followingUser','follower.followerUser','connections')
        ->get();

        return response()->json([
            'status_code' => 200,
            'data'        => $user,
        ], 200);
    }

    public function loginWithApple(loginWithAppleRequest $request)
    {
        $request_data = $request->only(['name', 'email', 'apple_id']);
        $apple_id = $request_data['apple_id'];
        $user = User::where('apple_id', $apple_id)->first();
        //dd($user);

        if($user) {
            $user['apple_id'] = $request_data['apple_id'];
            $user->save();
            //$user->roles()->sync($request->role_id);
            //$user->role =  ($user->roles()->first()) ? $user->roles()->first()->id : '';
        }  else  {
            /*if (empty($request->type)) {
                return response()->json([
                    'status_code' => 400,
                    'message'     => 'please select any type',
                ]);
            } */
            $user = User::where('email', $request_data['email'])->first();

            if($user){
                /*$user  = User::where('email', $request_data['email'])->update([
                    'apple_id'        => $apple_id,
                ]);*/   
                $user['apple_id'] = $request_data['apple_id'];
                $user->save();
            } else {
                $user  = User::create([
                    'name'            => $request_data['name'],
                    'email'           => $request_data['email'],
                    'apple_id'        => $apple_id,
                ]);
                //$user->roles()->sync($request->role_id);
               /* $roleId = Role::where('name', $request->type)->pluck('id');
               $user->roles()->sync($roleId);*/
           }
            /*$user = User::find($user->id);
            $user->roles()->sync($request->role_id);*/

            /*$user = User::find($user->id);
            $user->roles()->sync($request->role_id);*/
            $user = User::find($user->id);
            //$user->role =  ($user->roles()->first()) ? $user->roles()->first()->id : '';
        }
        $token = JWTAuth::fromUser($user);
        return response()->json(['status_code' => 200, 'data' => $user, 'token' => $token], 200);
    }

    /*
    public function socialLogin($social)
    {
       return Socialite::driver($social)->redirect();
    }

    public function handleProviderCallback($social)
    {
       $userSocial = Socialite::driver($social)->stateless()->user();
       $user = User::where(['email' => $userSocial->getEmail()])->first();
       if($user){
           Auth::login($user);
           return redirect()->action('HomeController@index');
       }else{
           return view('auth.register',['name' => $userSocial->getName(), 'email' => $userSocial->getEmail()]);
       }
    }
    */

    public function logout(Request $request)
    {
        $logout = User::where('device_token', 'LIKE', '%' . $request->device_token . '%')->first();
        if ($logout) {
         $logout->device_token = null;
         $logout->save();
         Auth::logout();
         return response()->json([
            'status_code' => 200,
            'message'        => "User logged out successfully.",
        ]);
     } else {
       return response()->json([
        'status_code' => 400,
        'message'        => "Sorry, The user cannot be logged ",
    ]);

   }

}
}
