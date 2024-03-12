<?php

/*
  This script (for E*Trade OAuth 1.0a) is essentially based on this excellent post:
  
    https://collaboradev.com/2011/04/01/twitter-oauth-php-tutorial/
    
  That post is meant for Twitter, and was written in 2011, but it still basically works.
  A couple changes were made for E*Trade OAuth 1.0a (namely GET for E*Trade), but 
  otherwise the 2011 Twitter code works.

  Other resources:
    https://community.postman.com/t/is-there-a-way-to-trace-or-simulate-oauth-signature-creation/49138/2
    https://lti.tools/oauth/
    https://docs.oracle.com/en/cloud/saas/netsuite/ns-online-help/section_1542835721.html#subsect_1542835740
    https://hannah.wf/twitter-oauth-simple-curl-requests-for-your-own-data/
    https://pastebin.com/C6J5vq9k
*/

/**
 * Method for creating a base string from an array and base URI.
 * @param string $baseURI the URI of the request to twitter
 * @param array $params the OAuth associative array
 * @return string the encoded base string
**/
function buildBaseString($baseURI, $params){
  $r = array(); //temporary array
      ksort($params); //sort params alphabetically by keys
      foreach($params as $key=>$value){
          $r[] = "$key=" . rawurlencode($value); //create key=value strings
      }                
    return "GET&" . rawurlencode($baseURI) . '&' . rawurlencode(implode('&', $r)); //return complete base string
}//end buildBaseString()

/**
 * Method for creating the composite key.
 * @param string $consumerSecret the consumer secret authorized by Twitter
 * @param string $requestToken the request token from Twitter
 * @return string the composite key.
**/
function getCompositeKey($consumerSecret, $requestToken){
    return rawurlencode($consumerSecret) . '&' . rawurlencode($requestToken);
}//end getCompositeKey()

/**
 * Method for building the OAuth header.
 * @param array $oauth the oauth array.
 * @return string the authorization header.
**/
function buildAuthorizationHeader($oauth){
    $r = 'Authorization: OAuth '; //header prefix

    $values = array(); //temporary key=value array
    foreach($oauth as $key=>$value)
        $values[] = "$key=\"" . rawurlencode($value) . "\""; //encode key=value string

    $r .= implode(', ', $values); //reassemble
    return $r; //return full authorization header
}//end buildAuthorizationHeader()

/**
 * Method for sending a request to Twitter.
 * @param array $oauth the oauth array
 * @param string $baseURI the request URI
 * @return string the response from Twitter
**/
function sendRequest($oauth, $baseURI){
    $header = array( buildAuthorizationHeader($oauth), 'Expect:'); //create header array and add 'Expect:'

    $options = array(CURLOPT_HTTPHEADER => $header, //use our authorization and expect header
                           CURLOPT_HEADER => false, //don't retrieve the header back from Twitter
                           CURLOPT_URL => $baseURI, //the URI we're sending the request to
                           //CURLOPT_POST => true, // GET for Etrade this is going to be a POST - required
                           CURLOPT_RETURNTRANSFER => true, //return content as a string, don't echo out directly
                           CURLOPT_SSL_VERIFYPEER => false); //don't verify SSL certificate, just do it

    $ch = curl_init(); //get a channel
    curl_setopt_array($ch, $options); //set options
    $response = curl_exec($ch); //make the call
    curl_close($ch); //hang up

    return $response;
}//end sendRequest()


//get request token

$baseURI = 'https://api.etrade.com/oauth/request_token';
$oauthConsumerKey = 'YOUR_CONSUMER_KEY';
$consumerSecret   = 'YOUR_CONSUMER_SECRET'; //put your actual consumer secret here, it will look something like 'MCD8BKwGdgPHvAuvgvz4EQpqDAtx89grbuNMRd7Eh98'

$nonce = time();
$timestamp = time();
$oauth = array('oauth_callback' => 'oob',
              'oauth_consumer_key' => $oauthConsumerKey,
              'oauth_nonce' => $nonce,
              'oauth_signature_method' => 'HMAC-SHA1',
              'oauth_timestamp' => $timestamp,
              'oauth_version' => '1.0');




$baseString = buildBaseString($baseURI, $oauth); //build the base string
print "baseString = $baseString \n\n";

$compositeKey = getCompositeKey($consumerSecret, ''); //first request, no request token yet
print "compositeKey = $compositeKey \n\n";

$oauth_signature = base64_encode(hash_hmac('sha1', $baseString, $compositeKey, true)); //sign the base string
print "oauth_signature = $oauth_signature \n\n";

$oauth['oauth_signature'] = $oauth_signature; //add the signature to our oauth array

$response = sendRequest($oauth, $baseURI); //make the cURL call
print "response = $response \n\n";

//parse response into associative array
$responseArray = array();
$parts = explode('&', $response);
foreach($parts as $p){
    $p = explode('=', $p);
    $responseArray[$p[0]] = $p[1];    
}//end foreach

//get oauth_token from response
$oauth_token = $responseArray['oauth_token'];

//redirect for authorization
//header("Location: http://api.twitter.com/oauth/authorize?oauth_token=$oauth_token");
//header("Location: https://us.etrade.com/e/t/etws/authorize?key=$oauthConsumerKey&token=$oauth_token");

print "Go to this URL to authorize: https://us.etrade.com/e/t/etws/authorize?key=$oauthConsumerKey&token=$oauth_token \n\n";

