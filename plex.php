<?php
$plex = new Plex();
$plexpass = ( $_GET['v'] === 'plexpass') ? true : false;

echo $plex->get_latest($plexpass);

class Plex {
	public $username = "testuser";
	public $password = "testpass";
	
	public function get_latest( $plexpass=false ) {
		$cache = ( $plexpass === true ) ? 'latestplexpass.json': 'latest.json';
		if( file_exists( $cache ) ) {
			$json = file_get_contents( $cache );
			$data = json_decode( $json );
			if( $data->last_checked < time()-600 || empty(  $data->version ) ) {// over 10 mins ago
				$oldjson = $json;
				$json = $this->_get_latest( $plexpass );
				$test = json_decode( $json ); // test file so we don't update if the update hasn't worked
				if( !empty( $test->version ) ) file_put_contents( $cache, $json );
				else $json = $oldjson;
			} 
		} else {
			$json = $this->_get_latest( $plexpass );
			file_put_contents( $cache, $json );
		}
		
		header("HTTP/1.1 200 OK");
		header("Content-Type: application/json; charset=utf-8");
		echo $json;
	}
	
	protected function _get_latest( $plexpass=false ) {
		
		$page_data = $this->query_plex( $plexpass );
		include_once('simple_html_dom.php');
		$html = str_get_html( $page_data );
		$version = $html->find('#pms-desktop .tab-content .sm');
		$version = $version[0]->innertext;
		$version = explode("Version ", $version);
		$version = explode(" ", $version[1]);
		$version = trim( $version[0] );
		$array = array('last_checked' => time(), 'version' => $version);
		foreach($html->find('#pms-desktop .tab-content') as $e) {
			$output = array();
			$details = $e->innertext;
			$title = $e->find('.title');
			$title = str_replace(array(" ", "."), "_", strtolower($title[0]->innertext));
			foreach( $e->find('.pop-btn') as $dl ) {
				$links = $dl->find('a');
				foreach( $links as $link ) {
					//var_dump($link->attr);
					$attr = "data-event-label";
					$type = (string)$link->$attr;
					$type = str_replace(array(" ", "."), "_", strtolower($type));
					$href = (string)$link->href;
					$array['downloads'][$title][$type] = $href;
				}
			}
		}
		foreach($html->find('#pms-nas .tab-content') as $e) {
			$output = array();
			$details = $e->innertext;
			
			$title = $e->find('.title');
			$title = str_replace(" ", "_", strtolower($title[0]->innertext));
			foreach( $e->find('.pop-btn') as $dl ) {
				$links = $dl->find('a');
				foreach( $links as $link ) {
					
					$type = (string)$link->innertext;
					$type = str_replace(array(" ", "."), "_", strtolower($type));
					$href = (string)$link->href;
					$array['downloads'][$title][$type] = $href;
				}
			}
		}
		return json_encode( $array, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	}
	
	protected function query_plex( $plexpass ) {
		
		$url = "https://plex.tv/users/sign_in";
		
		if( $plexpass === true ) {
			$username = $this->username;
			$password = $this->password;
			
			$downloads = 'https://plex.tv/downloads?channel=plexpass';

			$cookie = "cookie.txt";
			$ch = curl_init();
			curl_setopt ($ch, CURLOPT_URL, $url);
			curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
			curl_setopt ($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.6) Gecko/20070725 Firefox/2.0.0.6");
			curl_setopt ($ch, CURLOPT_TIMEOUT, 60);
			curl_setopt ($ch, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt ($ch, CURLOPT_COOKIEJAR, $cookie);
			curl_setopt ($ch, CURLOPT_COOKIEFILE, $cookie); 

			// Get login screen
			curl_setopt ($ch, CURLOPT_REFERER, $url );
			curl_setopt ($ch, CURLOPT_POST, 0);
			$result = curl_exec ($ch);

			include_once('simple_html_dom.php');
			$html = str_get_html( $result );
			$postdata = '';
			foreach($html->find('input') as $element) {
				if( $element->name == 'user[login]' ) $element->value = $username;
				if( $element->name == 'user[password]' ) $element->value = $password;
				$postdata .= $element->name.'='.$element->value.'&';
			}

			// Post login credentials
			curl_setopt ($ch, CURLOPT_REFERER, $url );
			curl_setopt ($ch, CURLOPT_POSTFIELDS, $postdata);
			curl_setopt ($ch, CURLOPT_POST, 1);
			$result = curl_exec ($ch);
			
			curl_setopt($ch, CURLOPT_URL, $downloads);
			curl_setopt($ch, CURLOPT_POST, 0);
			$data = curl_exec($ch);
			curl_close($ch);
			unlink( '/usr/share/nginx/www/plex/cookie.txt' ); // or next one wont work
		
		} else {
			$downloads = 'https://plex.tv/downloads';
			$ch = curl_init();
			curl_setopt ($ch, CURLOPT_URL, $url);
			curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
			curl_setopt ($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.6) Gecko/20070725 Firefox/2.0.0.6");
			curl_setopt ($ch, CURLOPT_TIMEOUT, 60);
			curl_setopt ($ch, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt ($ch, CURLOPT_REFERER, $url );
			curl_setopt($ch, CURLOPT_URL, $downloads);
			curl_setopt($ch, CURLOPT_POST, 0);
			$data = curl_exec($ch);
			curl_close($ch);
		}
		return $data;
	}

}