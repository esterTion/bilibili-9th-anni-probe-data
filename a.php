<?php
chdir(__DIR__);

for ($i = 1; $i < 4; $i++) {
  $probeData = json_decode(file_get_contents("${i}_probe.json"), true);
  file_put_contents("${i}_probe.json", str_replace("\n", "\r\n", json_encode($probeData, JSON_PRETTY_PRINT)));
}
exit;


function cget($url,$param=array()){
	/*
		$param参数表
		proxy = (string)fake ip
		header = (object)HTTP header
		post = (string) HTTP POST data
		curlOPT = (object)extra curl option
	*/
	if(gettype($param)=='string')$param=array('ip'=>$param);

	$preserveCurl = isset($param['curl']);
	$cgetCurl= $preserveCurl ? $param['curl'] : curl_init();
	$done=false;
	while(!$done){
	
	$header=array();
	if(!empty($param['header'])){
		foreach($param['header'] as $k=>$v){
			$header[$k]=$v;
		}
	}
	$header_s=array();
	foreach($header as $k=>$v){
		$header_s[]=$k.': '.$v;
	}
	curl_setopt_array($cgetCurl, array(
		CURLOPT_URL=>$url,
		CURLOPT_HEADER=>1,
		CURLOPT_HTTPHEADER=>$header_s,
		CURLOPT_USERAGENT=>'Mozilla/5.0 (compatible; MSIE 11.0; Windows NT 6.1; WOW64; Trident/6.0)',
		CURLOPT_RETURNTRANSFER=>1,
		CURLOPT_SSL_VERIFYPEER=>0,
		CURLOPT_TIMEOUT=>10
	));
	if(!empty($param['post'])){
		curl_setopt_array($cgetCurl, array(
			CURLOPT_CUSTOMREQUEST=>'POST',
			CURLOPT_POST=>1,
			CURLOPT_POSTFIELDS=>$param['post']
		));
	} else {
		curl_setopt_array($cgetCurl, array(
			CURLOPT_CUSTOMREQUEST=>'GET',
			CURLOPT_POST=>0,
			CURLOPT_POSTFIELDS=>''
		));
	}
	if(!empty($param['proxy'])){
		curl_setopt($cgetCurl, CURLOPT_PROXY,$param['proxy']);
	}
	if(!empty($param['curlOPT'])){
		curl_setopt_array($cgetCurl,$param['curlOPT']);
	}
	$return = curl_exec($cgetCurl);
	$headerSize = curl_getinfo($cgetCurl, CURLINFO_HEADER_SIZE);
	
	$header = explode("\r\n",substr($return, 0, $headerSize));
	$body = substr($return, $headerSize);
	$location='';
	foreach($header as $headeritem){
		if(substr($headeritem,0,8)=='Location')
		$location=substr($headeritem,10);
	}
	if(empty($location)){
		$done=true;
	}else{
		$done=false;
		$url=$location;
	}
	}
	!$preserveCurl && curl_close($cgetCurl);
	return $body;
}

$curl = curl_init();
for ($i = 1; $i < 4; $i++) {
  $list = json_decode(file_get_contents("$i.json"), true)['data'];
  $len = count($list);
  $probeData = [
    'has' => [],
    'missing' => []
  ];
  $j = 0;
  if (file_exists("${i}_probe.json")) {
    $probeData = json_decode(file_get_contents("${i}_probe.json"), true);
    $j = count(array_keys($probeData['has'])) + count($probeData['missing']);
  }
  for (; $j < $len; $j++) {
    echo "\r$j / $len " . $list[$j];
    $offset = 0;
    do {
      $data = json_decode(cget('http://api.vc.bilibili.com/dynamic_svr/v1/dynamic_svr/space_history?visitor_uid=0&host_uid='.$list[$j].'&offset_dynamic_id=' . $offset, ['curl'=>$curl, 'header'=>[
        'User-Agent'=>'Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:51.0) Gecko/20100101 Firefox/61.0',
        'Referer'=>'http://space.bilibili.com/',
        'Origin'=>'http://space.bilibili.com',
        'Cookie'=>'finger=c650951b',
        'Connection'=>'keep-alive'
      ]]), true);
      
      try {
        if ($data == NULL) {
          echo "\nempty\n";
          continue;
        }
        if ($data['code'] !== 0) {
          print_r($data);
          file_put_contents("${i}_probe.json", json_encode($probeData));
          exit;
        }
        if (!isset($data['data']['cards'])) {
          $probeData['missing'][] = $list[$j];
          echo "\n";
          break;
        }
        $found = false;
        foreach ($data['data']['cards'] as &$card) {
          if ($card['desc']['type'] === 1 && $card['desc']['orig_dy_id'] === 134705258328201427) {
            $probeData['has'][$list[$j]] = $card['desc']['dynamic_id'];
            $found = true;
            break;
          }
          $last = $card;
        }
        if (!$found) {
          if (!empty($last) && $last['desc']['timestamp'] > 1530201912) {
            $offset = $last['desc']['dynamic_id'];
            continue;
          }
          $probeData['missing'][] = $list[$j];
          echo "\n";
        }
      } catch(Exception $e) {
        $probeData['missing'][] = $list[$j];
        echo "\n";
      }
      break;
    } while (1);
  }

  file_put_contents("${i}_probe.json", json_encode($probeData));
  echo "\n";
}