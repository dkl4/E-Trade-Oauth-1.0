2024-03-28

# Notes on getting E*Trade API to work in PHP environment without using OAuth 1.0 library

## First, try to get things to work via Postman.com

This video shows how to use Postman to connect to E*Trade API:

  https://www.youtube.com/watch?v=1u9wCHYoygQ

The above has a lot of copy/paste/decode, so it's better to automate this in Postman.  This video is useful on showing how to automate a (Flickr) flow in OAuth 1.0 with Postman:

  https://www.youtube.com/watch?v=3gXPjj5iEAA

### Here is some Get Request 'Tests' (tab) code that is useful for Postman automation:

```javascript
const collection = require('postman-collection');
const params = collection.QueryParam.parse(pm.response.text());
console.log(params);
pm.environment.set("oauthTokenGetRequest",       params[0].value);
pm.environment.set("oauthTokenSecretGetRequest", params[1].value);
pm.environment.set("oauthTokenGetRequestDecode",       decodeURIComponent(params[0].value));
pm.environment.set("oauthTokenSecretGetRequestDecode", decodeURIComponent(params[1].value));
```

### Here is some Get Access 'Tests' (tab) code that is useful in Postman:

```javascript
const collection = require('postman-collection');
const params = collection.QueryParam.parse(pm.response.text());
console.log(params);
pm.environment.set("oauthTokenGetAccess",       params[0].value);
pm.environment.set("oauthTokenSecretGetAccess", params[1].value);
pm.environment.set("oauthTokenGetAccessDecode",       decodeURIComponent(params[0].value));
pm.environment.set("oauthTokenSecretGetAccessDecode", decodeURIComponent(params[1].value));
```

The above two code snippets will set up the temporary  oauthTokenGetRequest and  oauthTokenSecretGetRequest variables you will need to get  the "permanent" (24 hour - until midnight NY)  oauthTokenGetAccess and  oauthTokenSecretGetAccess variables.

Also use Postman environment variables to track things like the "very permanent" consumer_key and consumer_secret which you get from the ETrade website.  Also track the callback_url and 5-char verifier (from logging into ETrade) as Postman variables.

The login URL used to get the 5-char verifier can be obtained from Postman with a GET request like this:

```
https://us.etrade.com/e/t/etws/authorize?key=YOUR-VERY-PERM-KEY&token={{oauthTokenGetRequest}}
```

Where  ```{{oauthTokenGetRequest}}``` is a Postman environment variable obtained by the above code snippet in the Get Request.

To obtain the ETrade authorization URL, you can open the Console in Postman (lower left), hit SEND for the 'authorize' URL, and copy and paste the resulting URL into your browser.  Accept the ETrade terms and then copy the 5-char verifier into your Postman environment variables.

Subsequent Postman GET requests seemed to work fine after setting Postman environment variables (24-hour token and tokenSecret done via above snippets). You really don't need the 5-char verifier nor callback variables for subsequent requests.

For PUT and POST requests, in Postman I set the Body (POST data) to raw XML.  You may be able to use JSON POST data with a '?format=json' query parameter attached to the request, but it seems like XML is better supported by ETrade.


## Automating with PHP

I tried using various PHP OAuth 1.0 Pecl extensions and classes, but I either had trouble or didn't seem worth the effort.

I also like to reduce dependencies, so I looked for a more 'simple pure PHP approach.'

Vincy has a good, simple OAuth 1.0a set of PHP files here:

  https://phppot.com/php/login-with-twitter-using-oauth1-0a-protocol-via-api-in-php/

I modified her 'TwitterOauthService.php' for E*Trade.

Her code worked pretty well for simple E*Trade GET requests, but failed when:

* GET request included a query param like '?instType=BROKERAGE' (GET Account Balances)

* POST/PUT requests which needed XML data

The first problem seemed to be a problem with the OAuth signature.  The signature results from hashing a $baseString against the $signKey.

The baseString is a pretty long combination of the HTTP method along with all the OAuth and other (query etc.) parameters in a key-sorted way.

The ```$signKey``` is simply:
```
  $signKey = $reallyPermanentConsumerSecret . rawurlencode($tokenSecret_24Hour);
```

Note the ```$tokenSecret_24Hour``` may have a value of '' (null) during the GetRequest - Authorize by hand at etrade.com - Get Access phases.

To troubleshoot issues in the signature (really issues in the $baseString), the following tools are useful:

https://web.archive.org/web/20160430150356/http://oauth.googlecode.com:80/svn/code/javascript/example/signature.html  (Javascript OAuth)

(newer version of SVN repo here?? : https://github.com/johan/oauth-js/blob/master/example/signature.html)

This link is useful to test OAuth parameters:

https://lti.tools/oauth/


The signature.html page allows you to plug in various OAuth 1.0 parameters (timestamp, nonce, token etc.) and see the resulting, "correct" $baseString and signature.  At least, I trust this Javascript baseString and signature more than
what my code was yielding.


To get proper formation of the ```$baseString``` with query parameters, I found this code worked:

https://docs.oracle.com/en/cloud/saas/netsuite/ns-online-help/section_1542835721.html#subsect_1542835740

Note that code loads the $params array with array values, which seems odd:

```php
$params['oauth_version'] = array($version); 
…
foreach ($params as $key => $valueArray){ 
  //all values must sorted in alphabetical order 
  sort($valueArray); 
…
```

The second problem of sending XML POST/PUT data to E*Trade API can be fixed with adding xml to the Content-Type:

```php
        $headers = array(
          'Content-Type: application/xml',
          "Authorization: OAuth " . $oauthHeader
        );
```


## OTHER RESOURCES

  See this (old) script for Twitter OAuth 1.0 :
  
    https://collaboradev.com/2011/04/01/twitter-oauth-php-tutorial/
    
  That post is meant for Twitter, and was written in 2011, but it still basically works.
  A couple changes were made for E*Trade OAuth 1.0a (namely GET for E*Trade), but 
  otherwise the 2011 Twitter code works.

  ### Other resources:
    https://community.postman.com/t/is-there-a-way-to-trace-or-simulate-oauth-signature-creation/49138/2
    https://lti.tools/oauth/
    
    https://hannah.wf/twitter-oauth-simple-curl-requests-for-your-own-data/
    https://pastebin.com/C6J5vq9k

https://stackoverflow.com/questions/65293367/php-url-query-parameters-in-etsy-oauth-1-0-not-working






