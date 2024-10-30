<?php
/*
Plugin Name: BlogolB
Plugin URI: 
Description: This plugin pings blogolb.net servers everytime you makes a new Blog Post so that blogolb.net can index your content easily.
Version: 1.0
Author: 
Author URI: 
*/

// Pre-2.6 compatibility
if ( ! defined( 'WP_CONTENT_URL' ) )
      define( 'WP_CONTENT_URL', get_option( 'siteurl' ) . '/wp-content' );
if ( ! defined( 'WP_CONTENT_DIR' ) )
      define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
if ( ! defined( 'WP_PLUGIN_URL' ) )
      define( 'WP_PLUGIN_URL', WP_CONTENT_URL. '/plugins' );
if ( ! defined( 'WP_PLUGIN_DIR' ) )
      define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );
	  
	  
add_action('save_post', 'publish_post_syndicate');

function publish_post_syndicate($post_ID) {
	$postData = get_post($post_ID, ARRAY_A);
	$data = 'ID='.$postData["ID"];
	$data .= '&post_title='.$postData["post_title"];
	$data .= '&post_content='.$postData["post_content"];
	$data .= '&post_permalink='.get_permalink($post_ID);
	$data .= '&site_URL='.get_bloginfo('url');
	
	if ('revision' == $postData["post_type"]) {		
		try {
			$post_results = httpPost('blogolb.net','80', '/wp-content/plugins/blogolb-server/blogolb-server.php?ping=1&update=1', $data);
		} catch(Exception $e) {
		}
	} else {
		try {
			$post_results = httpPost('blogolb.net','80', '/wp-content/plugins/blogolb-server/blogolb-server.php?ping=1&update=0', $data);
		} catch(Exception $e) {
		}
	}
}

//
// Post provided content to an http server and optionally
// convert chunk encoded results.  Returns false on errors,
// result of post on success.  This example only handles http,
// not https.
//
function httpPost($ip=null,$port=80,$uri=null,$content=null) {
    if (empty($ip))         { return false; }
    if (!is_numeric($port)) { return false; }
    if (empty($uri))        { return false; }
    if (empty($content))    { return false; }
    // generate headers in array.
    $t   = array();
    $t[] = 'POST ' . $uri . ' HTTP/1.1';
    $t[] = 'Content-Type: application/x-www-form-urlencoded';
    $t[] = 'Host: ' . $ip . ':' . $port;
    $t[] = 'Content-Length: ' . strlen($content);
    $t[] = 'Connection: close';
    $t   = implode("\r\n",$t) . "\r\n\r\n" . $content;
    //
    // Open socket, provide error report vars and timeout of 10
    // seconds.
    //
    $fp  = @fsockopen($ip,$port,$errno,$errstr,10);
    // If we don't have a stream resource, abort.
    if (!(get_resource_type($fp) == 'stream')) { return false; }
    //
    // Send headers and content.
    //
    if (!fwrite($fp,$t)) {
        fclose($fp);
        return false;
        }
    //
    // Read all of response into $rsp and close the socket.
    //
    $rsp = '';
    while(!feof($fp)) { $rsp .= fgets($fp,8192); }
    fclose($fp);
    //
    // Call parseHttpResponse() to return the results.
    //
    return parseHttpResponse($rsp);
    }

//
// Accepts provided http content, checks for a valid http response,
// unchunks if needed, returns http content without headers on
// success, false on any errors.
//
function parseHttpResponse($content=null) {
    if (empty($content)) { return false; }
    // split into array, headers and content.
    $hunks = explode("\r\n\r\n",trim($content));
    if (!is_array($hunks) or count($hunks) < 2) {
        return false;
        }
    $header  = $hunks[count($hunks) - 2];
    $body    = $hunks[count($hunks) - 1];
    $headers = explode("\n",$header);
    unset($hunks);
    unset($header);
    if (!validateHttpResponse($headers)) { return false; }
    if (in_array('Transfer-Coding: chunked',$headers)) {
        return trim(unchunkHttpResponse($body));
        } else {
        return trim($body);
        }
    }

//
// Validate http responses by checking header.  Expects array of
// headers as argument.  Returns boolean.
//
function validateHttpResponse($headers=null) {
    if (!is_array($headers) or count($headers) < 1) { return false; }
    switch(trim(strtolower($headers[0]))) {
        case 'http/1.0 100 ok':
        case 'http/1.0 200 ok':
        case 'http/1.1 100 ok':
        case 'http/1.1 200 ok':
            return true;
        break;
        }
    return false;
    }

//
// Unchunk http content.  Returns unchunked content on success,
// false on any errors...  Borrows from code posted above by
// jbr at ya-right dot com.
//
function unchunkHttpResponse($str=null) {
    if (!is_string($str) or strlen($str) < 1) { return false; }
    $eol = "\r\n";
    $add = strlen($eol);
    $tmp = $str;
    $str = '';
    do {
        $tmp = ltrim($tmp);
        $pos = strpos($tmp, $eol);
        if ($pos === false) { return false; }
        $len = hexdec(substr($tmp,0,$pos));
        if (!is_numeric($len) or $len < 0) { return false; }
        $str .= substr($tmp, ($pos + $add), $len);
        $tmp  = substr($tmp, ($len + $pos + $add));
        $check = trim($tmp);
        } while(!empty($check));
    unset($tmp);
    return $str;
    }
?>