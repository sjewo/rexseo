<?php
/**
 * RexSEO - URLRewriter Addon
 *
 * @link https://github.com/gn2netwerk/rexseo
 *
 * @author dh[at]gn2-netwerk[dot]de Dave Holloway
 * @author code[at]rexdev[dot]de jdlx
 *
 * Based on url_rewrite Addon by
 * @author markus.staab[at]redaxo[dot]de Markus Staab
 *
 * @package redaxo 4.3.x/4.4.x/4.5.x
 * @version 1.6.3
 */



/**
 * Connect to GITHUB API v3
 **/
class rexseo_github_base
{
  public $error;
  public $access_method;
  public $repo_owner;
  public $repo_name;
  public $html_baseurl;
  public $api_baseurl;
  public $api_sections;
  public $cache_life = 3600;
  public $api_response;
  public static $query_log = array();


  /* constructor */
  public function __construct($repo_owner=false, $repo_name=false)
  {
    global $REX;

    $this->access_method = ini_get('allow_url_fopen')    ? 'fopen' : false;
    $this->access_method = function_exists('curl_init')  ? 'curl'  : $this->access_method;
    $this->access_method = class_exists('rexseo_socket') ? 'socket': $this->access_method;

    $this->error = $this->access_method==false ? 'no access method available' : false;

    $this->repo_owner = !$repo_owner ? $this->registerError('no repo owner provided',E_USER_ERROR) : $repo_owner;
    $this->repo_name  = !$repo_name  ? $this->registerError('no repo name provided' ,E_USER_ERROR) : $repo_name;

    $this->api_baseurl = 'https://api.github.com/repos/'.$this->repo_owner.'/'.$this->repo_name.'/';
    $this->api_sections = array('downloads','commits','issues','tags');

    $this->html_baseurl = 'https://github.com/'.$this->repo_owner.'/'.$this->repo_name.'/';
  }


  protected function getApiResponse($url)
  {
    $response = self::getCachedResponse($url);

    if($response!==false){
      $this->api_response = json_decode($response);
      self::$query_log[] = array('url' => $url, 'from_cache' => true);
      return;
    }
    self::$query_log[] = array('url' => $url, 'from_cache' => false);

    switch($this->access_method)
    {
      case'curl':
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $response = curl_exec($ch);
        curl_close ($ch);
      break;

      case'fopen':
        $response = file_get_contents($url, 'r');
      break;

      case'socket':
        $socket = rexseo_socket::factoryUrl($url);
        $response = $socket->doGet();
        if($response->isOk()) {
          $response = $response->getBody();
        }else{
          $this->error = 'socket response broken';
          return;
        }
      break;
    }

    $this->api_response = json_decode($response);
    self::writeResponseCache($url,$response);
  }


  protected function writeResponseCache($url,$response)
  {
    global $REX;
    $cachefile = $REX['INCLUDE_PATH'].'/generated/files/'.self::getCacheFileName($url);
    return rex_put_file_contents($cachefile,$response);
  }


  protected function getCachedResponse($url)
  {
    global $REX;
    $cachefile = $REX['INCLUDE_PATH'].'/generated/files/'.self::getCacheFileName($url);
    if(!file_exists($cachefile)){
      return false;
    }
    $stats = stat($cachefile);
    if($stats[9] > (time() - $this->cache_life)){
      return rex_get_file_contents($cachefile);
    }else{
      return false;
    }
  }


  protected function getCacheFileName($url)
  {
    return str_replace(array('https://','/'),array('','_'),$url).'.json';
  }


  protected function registerError($err=false,$err_type=false)
  {
      $err = 'CLASS GITHUB_CONNECT: '.$err.'.';
      $this->error .= $err.'<br />'.PHP_EOL;
      if($err_type)
        trigger_error($err, $err_type);
  }


  // COMMON PUBLIC METHODS
  //////////////////////////////////////////////////////////////////////////////


  public function setQuery($query, $raw_query = false)
  {
    if($raw_query == false) {
      $this->getApiResponse($this->api_baseurl.$query);
    }else{
      $this->getApiResponse($query);
    }
  }


  public function setParam($param, $value)
  {
    $this->$param = $value;
  }


  public function getResponse()
  {
    return $this->api_response;
  }

}

?>
