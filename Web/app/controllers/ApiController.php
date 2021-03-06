<?php

namespace MyDropper\controllers;

use MyDropper\models\Category;
use MyDropper\models\Store;
use MyDropper\models\TrackerStore;
use MyDropper\models\TrackerUrl;
use MyDropper\models\Url;
use MyDropper\models\User;
use Carbon\Carbon;

/**
 * Class ApiController
 * @package MyDropper\Controllers
 */
class ApiController extends BaseController
{
    /**
     * Connect USER and return token with UserInformations
     * POST /api/connect
     *
     * @param string username
     * @param string password
     */
    public function connect()
    {
        $validForm = User::checkForm($this->f3->get('POST'), array(
            'username' => 'required',
            'password' => 'required'
        ));

        if ($validForm === true) {
            $user = User::where('username', $this->f3->get('POST.username'))->where('password', $this->crypt($this->f3->get('POST.password')))->first();

            if ($user !== null || !empty($user)) {

                if($user->token_api !== null){
                    $token = $user->token_api;
                }else{
                    $token = uniqid("API_");
                }


                // Generate Token and save it
                $user                = User::find($user->id);
                $user->token_api     = $token;
                $user->has_extension = 1;
                $user->save();

                $data = [
                    'success' => true,
                    'message' => "User connected.",
                    'user'    => [
                        'id'        => $user->id,
                        'username'  => $user->username,
                        'firstname' => $user->firstname,
                        'lastname'  => $user->name,
                        'avatar'    => $user->avatar_url,
                        'token_api' => $token
                    ]
                ];
            } else {
                $data = $this->returnError('Error, the account is not in the database.');
            }
        } else {
            $data = $this->returnError('Error, you need insert a username and a password.');
        }
        $this->render(false, $data);
    }

    // ----------------------------------  REQUESTS FROM EXTENSION
    /**
     * Return all categories with stores
     * POST /api/stores
     *
     * @param int user_id
     * @param string token_id
     */
    public function getStores()
    {
        $validForm = User::checkForm($this->f3->get('POST'), array(
            'user_id'  => 'required',
            'token_id' => 'required'
        ));

        if ($validForm === true) {
            $user = User::where('id', $this->f3->get('POST.user_id'))->where('token_api', $this->f3->get('POST.token_id'))->first();

            if ($user !== null || !empty($user)) {
                $userId     = $this->f3->get('POST.user_id');
                $categories = User::find($userId)->categories()->get();
                $data       = [];

                for ($i = 0; $i < count($categories); $i++) {
                    array_push($data, [
                        'category_id'    => $categories[$i]->id,
                        'category_label' => $categories[$i]->label,
                        'stores'         => []
                    ]);

                    $stores = Store::where('category_id', $categories[$i]->id)->get();

                    for ($j = 0; $j < count($stores); $j++) {
                        if ($stores[$j]->is_shorter) {
                            $url = Url::where('store_id', '=', $stores[$j]->id)->first();
                            $data[$i]['stores'][] = [
                                'store_id'          => $stores[$j]->id,
                                'store_label'       => $stores[$j]->label,
                                'store_description' => $stores[$j]->descript,
                                'store_active'      => $stores[$j]->is_active,
                                'store_shorter'     => $stores[$j]->is_shorter,
                                'store_url_shorter' => 'http://mydropper.mathieuletyrant.com/url/'.$url->token
                            ];
                        } else {
                            $data[$i]['stores'][] = [
                                'store_id'          => $stores[$j]->id,
                                'store_label'       => $stores[$j]->label,
                                'store_description' => $stores[$j]->descript,
                                'store_active'      => $stores[$j]->is_active,
                                'store_shorter'     => $stores[$j]->is_shorter
                            ];
                        }
                    }
                }
            } else {
                $data = $this->returnError("Error, can't find an account with user_id(".$this->f3->get('POST.user_id').") and token_id(".$this->f3->get('POST.token_id').".");
            }
        } else {
            $data = $this->returnError('Error, you need insert a userId and tokenId.');
        }

        $this->render(false, $data);
    }

    /**
     * Add a tracker when user drag an store on a website
     * POST /api/trackstore
     *
     * @param string token_id
     * @param int user_id
     * @param int store_id
     * @param string on_url
     * @param string full_url
     */
    public function trackStore()
    {
        $validForm = User::checkForm($this->f3->get('POST'), array(
            'token_id' => 'required',
            'user_id'  => 'required',
            'store_id' => 'required',
            'on_url'   => 'required',
            'full_url' => 'required'
        ));

        if ($validForm === true) {
            $user = User::where('id', $this->f3->get('POST.user_id'))->where('token_api', $this->f3->get('POST.token_id'))->first();

            if ($user !== null || !empty($user)) {
                TrackerStore::create(array(
                    'user_id'  => $this->f3->get('POST.user_id'),
                    'store_id' => $this->f3->get('POST.store_id'),
                    'on_url'   => $this->f3->get('POST.on_url'),
                    'full_url' => $this->f3->get('POST.full_url')
                ));
                $data = $this->returnSuccess('Tracker added');
            } else {
                $data = $this->returnError("Error, can't find an account with user_id(".$this->f3->get('POST.user_id').") and token_id(".$this->f3->get('POST.token_id').").");
            }
        } else {
            $data = $this->returnError("Error, you need insert an tokenId(POST.token_id) and userId(POST.user_id) and storeId(POST.store_id) and onUrl(POST.on_url) and fullUrl(POST.full_url).");
        }

        $this->render(false, $data);
    }

    // --------------  MANAGE ERRORS FOR API
    /**
     * Return data for Error
     *
     * @param string $message
     *
     * @return array
     */
    private function returnError($message)
    {
        return [
            'success' => false,
            'message' => $message
        ];
    }

    /**
     * Return data for Success
     *
     * @param string $message
     *
     * @return array
     */
    private function returnSuccess($message)
    {
        return [
            'success' => true,
            'message' => $message
        ];
    }

    // ----------------------------------  REQUESTS FROM APP FRONT
    /**
     * Check if user can access data
     *
     * @param $user_id
     * @param $token_api
     * @param bool $returnUser
     * @param int $level
     * @return bool
     */
    private function isAuth($user_id, $token_api, $returnUser = false, $level=0){
        if($user_id !== null && $token_api !== null){
            $user_id = (int)($user_id);
            $user = User::where('token_api', '=', $token_api)->where('id', '=', $user_id)->with('roles')->first();
            if($user !=null && $returnUser === true){
                if($level <= (int)($user->roles->level)){
                    return $user;
                }
            }elseif($user !=null && $returnUser === false){
                if($level <= (int)($user->roles->level)){
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Tracking PAGE
     * POST /api/categories
     *
     * @param int user_id
     * @param string token_api
     */
    public function getCategoryList()
    {
        $json = [];
        $user = $this->isAuth($this->f3->get('POST.user_id'),$this->f3->get('POST.token_api'),true);
        if($user !== false){
            $categories                         = Category::where('user_id', '=', $user->id)->get();
            $json['categoryList']     = [];

            for ($i = 0; $i < count($categories); $i++) {
                $json['categoryList'][] = [
                    "id" => $categories[$i]->id,
                    "text" => $categories[$i]->label
                ];
            }

        }else{
            $json['error'] = 'error occurred (no user)';
        }
        $this->render(false, $json);
    }

    /**
     * Tracking PAGE
     * POST /api/trackedlink
     *
     * @param int user_id
     * @param string token_api
     * @param int cat_id
     * @param date from
     * @param date to
     */
    public function getTrackedLink()
    {
        $json = [];
        $user = $this->isAuth($this->f3->get('POST.user_id'),$this->f3->get('POST.token_api'),true);
        if($user !== false){
            $catId  = $this->f3->get('POST.cat_id');
            $from   = Carbon::parse($this->f3->get('POST.from'));
            $to     = Carbon::parse($this->f3->get('POST.to'));
            $json['data'] = [];

            $stores = Store::where('user_id', '=', $user->id)->where('category_id', '=', $catId)->where('is_shorter', '=', 1)->get();
            for ($i = 0; $i < count($stores); $i++) {
                $store_id           = $stores[$i]->id;
                $store_name         = $stores[$i]->label;
                $store_created_at   = Carbon::parse($stores[$i]->created_at);

                $url    = Url::where('store_id', '=', $store_id)->first();

                $trackerUrlCount    = TrackerUrl::where('url_id', '=', $url->id)->count();
                $trackerUrl         = TrackerUrl::where('url_id', '=', $url->id)->orderBy('created_at', 'ASC')->get();

                $graphData = [];

                for ($j = 0; $j < count($trackerUrl); $j++) {
                    if (Carbon::parse($trackerUrl[$j]->created_at)->between($from, $to)) {
                        $date = Carbon::parse($trackerUrl[$j]->created_at)->format('m-d');

                        if (!isset($graphData[$date])) {
                            $graphData[$date] = 0;
                        }
                        if (isset($graphData[$date])) {
                            $graphData[$date] += 1;
                        }
                    }
                }

                $json['data'][] = [
                    'snippetName' => $store_name,
                    'nbClick'     => $trackerUrlCount,
                    'createdAt'   => $store_created_at->toDateString(),
                    'since'       => $store_created_at->diffForHumans(),
                    'graphData'   => $graphData
                ];
            }
        }else{
            $json['error'] = 'error occurred (no user)';
        }
        $this->render(false, $json);
    }

    /**
     * Tracking PAGE
     * POST /api/categoryglobal
     *
     * @param int user_id
     * @param string token_api
     * @param int cat_id
     * @param date from
     * @param date to
     */
    public function getCategoryGlobal()
    {
        $json = [];
        $user = $this->isAuth($this->f3->get('POST.user_id'),$this->f3->get('POST.token_api'),true);
        if($user !== false){
            $catId  = $this->f3->get('POST.cat_id');
            $from   = Carbon::parse($this->f3->get('POST.from'));
            $to     = Carbon::parse($this->f3->get('POST.to'));
            $json['data'] = [];

            $category = Category::find($catId);
            $json['data']['categoryName'] = $category->label;

            $stores = Store::where('user_id', '=', $user->id)->where('category_id', '=', $catId)->where('is_shorter', '=', 1)->get();

            for ($i = 0; $i < count($stores); $i++) {
                $url = Url::where('store_id', '=', $stores[$i]->id)->first();

                $urlsTracker = TrackerUrl::where('url_id', '=', $url->id)->get();

                for ($j= 0; $j < count($urlsTracker); $j++) {
                    if (Carbon::parse($urlsTracker[$j]->created_at)->between($from, $to)) {
                        $date = Carbon::parse($urlsTracker[$j]->created_at)->format('m-d');

                        if (!isset($json['data']['graphData'][$date])) {
                            $json['data']['graphData'][$date] = 0;
                        }
                        if (isset($json['data']['graphData'][$date])) {
                            $json['data']['graphData'][$date] += 1;
                        }
                    }
                }
            }
        }else{
            $json['error'] = 'error occurred (no user)';
        }
        $this->render(false, $json);
    }


    /**
     * Get async history
     * POST /api/historyasync
     *
     * @param int $user_id
     * @param string $token_api
     * @param int $pagination
     * @param int $pages
     *
     */
    public function getHistory(){
        $json = [];
        $user = $this->isAuth($this->f3->get('POST.user_id'),$this->f3->get('POST.token_api'),true);
        if($user !== false){
            $pagination = (int)($this->f3->get('POST.pagination'));
            $pages = (int)($this->f3->get('POST.pages'));
            if($pagination !== null && $pages !== null){
                $json['trackers'] = TrackerStore::where('user_id', '=', $user->id)->take($pagination)->offset($pages*$pagination)->with('stores')->orderBy('created_at', 'desc')->get();
            }else{
                $json['error'] = 'error occurred (missing params)';
            }
        }else{
            $json['error'] = 'error occurred (no user)';
        }
        $this->render(false, $json);
    }

    // ----------------------------------  REQUESTS FROM APP ADMIN
    /**
     * Admin users list
     * POST /api/admin/users
     *
     * @param string token_api
     * @param int user_id
     * @param int pagination
     * @param int pages
     *
     */
    public function getAdminUsers(){

        $tokenApi = $this->f3->get('POST.token_api');
        $userId = (int)($this->f3->get('POST.user_id'));
        $pagination = (int)($this->f3->get('POST.pagination'));
        $pages = (int)($this->f3->get('POST.pages'));

        $json = [];
        if($tokenApi){
            $user = User::where('token_api', '=', $tokenApi)->where('id', '=', $userId)->with('roles')->first();

            if($user->roles->level && $user->roles->level > 9){
                $json['users'] = User::with('roles')->take($pagination)->offset($pages*$pagination)->get();
            }else{
                $json['error'] = 'error occurred (no user)';
            }
        }else{
            $json['error'] = 'error occurred (no user)';
        }
        $this->render(false, $json);
    }
}
