<?php
require_once ('./opinion.php');

class AnalyseMail {
	private $email_id;
	private $fbid;
	private $twitterid = null;
	private $linkedinid;
	private $company;
	private $stop_companies = array('hotmail', 'gmail');	
	private $body;	

	function __construct($email, $body){
		$this->email_id = $email;
		$mail_arr = explode ("@", $email);
		$domain = $mail_arr[1];
		$dom_parts = explode (".", $domain);
		$this->company = $dom_parts[0];
		$this->body = $body;
	}

	private function fetch_url_data_get($url){
		$ch = curl_init();
            	curl_setopt($ch,CURLOPT_URL,$url);
            	curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
            	$result = curl_exec($ch);
            	curl_close($ch);
		return json_decode ($result, true);
	}

	private function fetch_url_data_post($url, $fields = array()) {
            	$fields_string="";
            	$ch = curl_init();
            	foreach($fields as $key=>$value) { $fields_string .= $key.'='. urlencode($value).'&'; }
            	rtrim($fields_string,'&');
            	curl_setopt($ch,CURLOPT_URL,$url);
            	curl_setopt($ch,CURLOPT_POST,count($fields));
            	curl_setopt($ch,CURLOPT_POSTFIELDS,$fields_string);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
            	$result = curl_exec($ch);
		
            	curl_close($ch);
		return json_decode ($result, true);
        }

	public function GetSocialProfiles(){
		$accounts = array();
		$mailhash = md5($this->email_id);
		$url = 'http://www.gravatar.com/'.$mailhash.'.php';
		$str = file_get_contents($url);
		$jdata = unserialize( $str );
		if (isset ($jdata['entry'][0]['accounts'])){
			$accounts = $jdata['entry'][0]['accounts'];
			foreach ($accounts as $account){
				if (isset ($account['domain'])){
					$domain = $account['domain'];
					if ($domain == "facebook.com"){
						$this->fbid = $account['username'];
					}
					if ($domain == "twitter.com"){
						$this->twitterid = $account['username'];
					}
					if ($domain == "linkedin.com"){
						$this->linkedinid = $account['username'];
					}
				}
			}
		}
		else{
			return false;
		}
		//print $this->fbid." ".$this->twitterid." ".$this->linkedinid."\n";
		//$this->GetTwitterDetails();
		return true;
	}

	private function GetTwitterDetails(){
		$ret_arr = array();
		if ($this->twitterid){
			$twid = $this->twitterid;
			$url = 'https://api.twitter.com/1/users/lookup.json';
			$params = array('include_entities' => 'true', 'screen_name' => $twid);
			$jdata = $this->fetch_url_data_post($url, $params);
			$followers = $jdata[0]['followers_count'];
			$twitnumid = $jdata[0]['id'];
			$name = $jdata[0]['name'];
			$ret_arr = array('followers' => $followers, 'twitterid' => $twitnumid, 'name' => $name);
		}
		return $ret_arr;
	}

	public function GetCrunchbaseDetails(){
		//http://api.crunchbase.com/v/1/companies/posts?name=salesforce&api_key=kmw9gd4gvrb5f677j4r7tbcf
		//http://api.crunchbase.com/v/1/person/mukund+mohan.js?api_key=kmw9gd4gvrb5f677j4r7tbcf
		$ret_arr = array();
		$company = $this->company;
		if (!in_array($company, $this->stop_companies)){
			$url_for_company = "http://api.crunchbase.com/v/1/companies/posts?name=$company&api_key=kmw9gd4gvrb5f677j4r7tbcf";
			$company_details = $this->fetch_url_data_get($url_for_company);
			if (!isset ($company_details['error'])){
				$cb_url = $company_details['crunchbase_url'];
				$num_posts = $company_details['num_posts'];
				$ret_arr['cb_url'] = $cb_url;
				$ret_arr['posts'] = $num_posts;
			}
		}
		$tw_details = $this->GetTwitterDetails();
		if (count ($tw_details)){
			$name = $tw_details['name'];
			$url_for_name = "http://api.crunchbase.com/v/1/person/".urlencode($name).".js?api_key=kmw9gd4gvrb5f677j4r7tbcf";
			$name_details = $this->fetch_url_data_get($url_for_name);
			if (!isset ($name_details)){
				$cb_name_url = $name_details['crunchbase_url'];
				$ret_arr['cb_name_url'] = $cb_name_url;
			}
		}
		return $ret_arr;
	}
	
	public function GetKloutDetails(){
		//http://api.klout.com/v2/identity.json/tw/16307579?key=r2qdwncj7sjcadmkpgc48v9k
		//http://api.klout.com/v2/user.json/4785079371569478/score?key=r2qdwncj7sjcadmkpgc48v9k
		$klout_score = false;
		$tw_details = $this->GetTwitterDetails();
		if (count ($tw_details) != 0){
			$id = $tw_details['twitterid'];
			$url_for_k_id = "http://api.klout.com/v2/identity.json/tw/$id?key=r2qdwncj7sjcadmkpgc48v9k";
			$kloudid_resp = $this->fetch_url_data_get($url_for_k_id);
			$kloutid = $kloudid_resp['id'];
			$url_for_k_score = "http://api.klout.com/v2/user.json/$kloutid/score?key=r2qdwncj7sjcadmkpgc48v9k";
			$kloutscore_resp = $this->fetch_url_data_get($url_for_k_score);
			$klout_score = $kloutscore_resp['score'];
		}
		return $klout_score;
	}

	public function GetSentimentForMail(){
		return getSentiment($this->body);
	}

	public function GetScoreAndData(){
		$score = 0;
		$ret_arr = array();
		$cb_data = $this->GetCrunchbaseDetails();
        	$sentiment_data = $this->GetSentimentForMail();
        	$twitter_data = $this->GetTwitterDetails();
        	$klout_data = $this->GetKloutDetails();				
		if (isset ($cb_data['cb_name_url'])){
			$score += 20;
			$ret_arr['cb_name_url'] = $cb_data['cb_name_url'];
		}
		if (isset ($cb_data['posts'])){
			$posts = $cb_data['posts'];
			if ($posts > 100){
				$score += 20;
			}
			else{
				$score += 15;
			}
			$ret_arr['posts'] = $posts;
			$ret_arr['cb_url'] = $cb_data['cb_url'];
		}
		if (isset ($sentiment_data['neg'])){
			$neg = $sentiment_data['neg'];
			$pos = $sentiment_data['pos'];
			if ($neg >= 5){
				$score += 35;
			}
			else{
				if ($neg != 0){
					$score += 30;
				}
			}
			$ret_arr['neg'] = $neg;
			$ret_arr['pos'] = $pos;
			if ($neg > 0){
				$ret_arr['sentiment'] = floor (($neg/($neg + $pos))*100) ;
			}
			else{
				$ret_arr['sentiment'] = 0;
			}
		}
		if ($klout_data){
			if ($klout_data > 40){
				$score += 20;
			}
			else{
				$score += 5;
			}
			$ret_arr['klout'] = floor ($klout_data);
		}
		else{
			if (isset ($twitter_data['followers'])){
				$no = $twitter_data['followers'];
				if ($no > 100){
					$score += 15;
				}
				else{
					$score +=5;
				}
				$ret_arr['twitter'] = $no;
			}

		}
		$ret_arr['score'] = $score;
		$priority = 4 - floor ($score/20);
		$ret_arr['priority'] = $priority;
		return $ret_arr;
	}
}

?>
