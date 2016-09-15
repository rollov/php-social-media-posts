<?php
/**
 * Created by PhpStorm.
 * User: ror
 * Date: 30.08.16
 * Time: 10:17
 */

namespace models;

class Feed
{
    public $f3;
    public $fb_authentication;

    function __construct(&$f3) {

        $this->f3 = $f3;
    }

    function import(array $channels) {

        $messages = array();

        if (isset($channels['facebook'])) {
            $feed = json_decode($this->facebook($channels['facebook']));

            foreach ($feed->data as $data) {
                $fields = new \stdClass();
                $fields->profile_img = 'https://scontent-vie1-1.xx.fbcdn.net/v/t1.0-1/p40x40/13445524_1561994327430486_3098283100871289380_n.jpg?oh=2e768c820a731f75364bb4ea3ed7873e&oe=584345BC';
                $fields->user_name = $data->from->name;
                $fields->user_name_sys = $data->from->name;
                $fields->picture = $this->get_fb_images($data->object_id)->data->url;
                $fields->message = $this->make_links($data->message);
                $fields->timestamp = $this->convert_date($data->created_time);
                $fields->created = date('Y-m-d H:i:s', $fields->timestamp);
                array_push($messages, $fields);
            }
        }

        if (isset($channels['twitter'])) {
            $feed = json_decode($this->twitter($channels['twitter']));

            foreach ($feed as $data) {
                $fields = new \stdClass();
                $fields->profile_img = $data->user->profile_image_url;
                $fields->user_name = $data->user->screen_name;
                $fields->user_name_sys = $data->user->name;

                if (isset($data->entities->media[0]) && $data->entities->media[0]->type == 'photo')
                    $fields->picture = $data->entities->media[0]->media_url;
                $fields->message = $this->add_twitter_links($this->make_links($data->text));
                $fields->timestamp = $this->convert_date($data->created_at);
                $fields->created = date('Y-m-d H:i:s', $fields->timestamp);
                array_push($messages, $fields);
            }
        }

        // sort by date
        foreach ($messages as $key => $value) {
            $date[$key] = strtolower($value->timestamp);
        }

        array_multisort($date, SORT_DESC, $messages);

        return $messages;
    }

    public function facebook ($feed_id) {

        /* Getting a JSON Facebook Feed
           ==========================================================================
           1. Sign in as a developer at https://developers.facebook.com/
           2. Click "Create New App" at https://developers.facebook.com/apps
           3. Under Apps Settings, find the App ID and App Secret
        */
        $appID = $this->f3->get('facebook_client_id');
        $appSecret = $this->f3->get('facebook_client_secret');

        /* Configuring a JSON Facebook Feed
           ==========================================================================
           1. Find the desired feed ID at http://findmyfacebookid.com/
           2. Set the maximum number of stories to retrieve
           3. Set the seconds to wait between caching the response
        */
        $feed = $feed_id;
        $maximum = 1;
        $caching = 0;

        /* Enjoying a JSON Facebook Feed
           ==========================================================================
           Visit this URL and make sure everything is working
           Use JSONP by adding ?callback=YOUR_FUNCTION to this URL
           Tweet love or hate @jon_neal
           Permission errors? http://stackoverflow.com/questions/4917811/file-put-contents-permission-denied
        */
        $filename = 'tmp/cache/fb.feed.json';

        $this->fb_authentication = file_get_contents(
            "https://graph.facebook.com/oauth/access_token" .
            "?grant_type=client_credentials&client_id={$appID}" .
            "&client_secret={$appSecret}");

        $response = file_get_contents(
            "https://graph.facebook.com/{$feed}/feed" .
            "?{$this->fb_authentication}&limit={$maximum}");

        return $response;
    }

    public function get_fb_images ($object_id) {

        $response = file_get_contents(
            "https://graph.facebook.com/v2.7/$object_id/picture" .
            "?{$this->fb_authentication}&format=json&redirect=false");

        return json_decode($response);
    }

    public function twitter ($user_name) {

        /* Getting a JSON Twitter Feed
           ==========================================================================
           1. Sign in as a developer at https://dev.twitter.com/
           2. Click "Create a new application" at https://dev.twitter.com/apps
           3. Under Application Details, find the OAuth settings and the access token
        */
        $consumerKey = $this->f3->get('twitter_consumer_key');
        $consumerSecret = $this->f3->get('twitter_consumer_secret');
        $accessToken = $this->f3->get('twitter_access_token');
        $accessTokenSecret = $this->f3->get('twitter_access_token_secret');
        /* Configuring a JSON Twitter Feed
           ==========================================================================
           1. Find the desired twitter username
           2. Set the maximum number of tweets to retrieve
           3. Set the seconds to wait between caching the response
        */
        $username = $user_name;
        $maximum = 1;
        $caching = 0;

        /* Enjoying a JSON Twitter Feed
           ==========================================================================
           Visit this URL and make sure everything is working
           Use JSONP by adding ?callback=YOUR_FUNCTION to this URL
           Tweet love or hate @jon_neal
           Permission errors? http://stackoverflow.com/questions/4917811/file-put-contents-permission-denied
        */
        $filename = 'tmp/cache/twitter.feed.json';
        $filetime = file_exists($filename) ? filemtime($filename) : time() - 1;

        $url = 'https://api.twitter.com/1.1/statuses/user_timeline.json';
        $base = 'GET&' . rawurlencode($url) . '&' . rawurlencode("count={$maximum}&oauth_consumer_key={$consumerKey}&oauth_nonce={$filetime}&oauth_signature_method=HMAC-SHA1&oauth_timestamp={$filetime}&oauth_token={$accessToken}&oauth_version=1.0&screen_name={$username}");
        $key = rawurlencode($consumerSecret) . '&' . rawurlencode($accessTokenSecret);
        $signature = rawurlencode(base64_encode(hash_hmac('sha1', $base, $key, true)));
        $oauth_header = "oauth_consumer_key=\"{$consumerKey}\", oauth_nonce=\"{$filetime}\", oauth_signature=\"{$signature}\", oauth_signature_method=\"HMAC-SHA1\", oauth_timestamp=\"{$filetime}\", oauth_token=\"{$accessToken}\", oauth_version=\"1.0\", ";
        $curl_request = curl_init();
        curl_setopt($curl_request, CURLOPT_HTTPHEADER, array("Authorization: Oauth {$oauth_header}", 'Expect:'));
        curl_setopt($curl_request, CURLOPT_HEADER, false);
        curl_setopt($curl_request, CURLOPT_URL, $url . "?screen_name={$username}&count={$maximum}");
        curl_setopt($curl_request, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl_request, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($curl_request);
        curl_close($curl_request);

        return $response;
    }

    public function make_links($text) {

        $reg_ex_url = "/(http|https|ftp|ftps)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,3}(\/\S*)?/";

        if (preg_match($reg_ex_url, $text, $url)) {
            $text = preg_replace($reg_ex_url, "<a href=\"{$url[0]}\">{$url[0]}</a> ", $text);
        }

        return $text;
    }

    public function  add_twitter_links ($text) {

        return preg_replace('/#(\w+)/', '<a href="https://twitter.com/search/%23$1"' . $this->target . '>#$1</a>', $text);

    }

    public function convert_date ($string) {

        return strtotime($string);

    }
}