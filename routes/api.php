<?php

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
 */

/* Route::middleware('auth:api')->get('/user', function (Request $request) {
return $request->user();
}); */

Route::post('login', 'UserController@authenticate');
Route::post('sign-up', 'UserController@register');
Route::post('forget-password', 'UserController@forgetPassword');
Route::post('social-media-register', 'UserController@socialMediaRegister');
Route::post('set-password', 'UserController@setPassword');



Route::get('login/{social}', 'UserController@socialLogin');
Route::get('login/{social}/callback', 'UserController@handleProviderCallback');


Route::group(['middleware' => ['jwt.verify']], function () {
    Route::post('update-profile', 'UserController@updateProfile');
    Route::post('change-password', 'UserController@changePassword');
    Route::get('user', 'UserController@getAuthenticatedUser');

    Route::get('arts', 'ArtController@index');
    Route::post('add-art', 'ArtController@addArt');
    Route::post('edit-art', 'ArtController@editArt');
    Route::get('art/{id}', 'ArtController@detailArt');
    Route::delete('art/{id}', 'ArtController@deleteArt');


    Route::post('send', 'ContactController@sendRequest');
    Route::post('respond', 'ContactController@acknowledgeRequest');
    
    
});
