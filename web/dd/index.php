<?php

$aprs_server = "127.0.0.1";
$msg="";

function sendaprs($call, $lat, $lon, $desc, $ts)
{	global $aprs_server;
	global $msg;
	$s = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
	socket_connect($s, $aprs_server, 14580 );
	$N = 'N';
	if($lat < 0) {
		$lat = - $lat;
		$N = 'S';
	}
	$E = 'E';
	if($lon < 0) {
		$lon = -$lon;
		$E = 'W';
	}	
	$msg = $call.">WEB2NT:!";
	$msg = $msg.sprintf("%02d%05.2f%s%s", floor($lat), ($lat-floor($lat))*60, $N, substr($ts,0,1));
	$msg = $msg.sprintf("%03d%05.2f%s%s", floor($lon), ($lon-floor($lon))*60, $E, substr($ts,1,1));
	$msg = $msg.sprintf("%s%s", $desc, "\r\n");
//	echo $msg;
//	echo "<p>";
	socket_send ($s, $msg, strlen($msg), 0);
	$msg = date("Y-m-d H:i:s ").$msg;
}

if( @$_REQUEST["track"] ==1 ) {
	$call =@$_REQUEST["call"];
	$ts =@$_REQUEST["ts"];

	$handle = @fopen($_FILES['userfile']['tmp_name'], "r");
	$iscoor=0;
	$oldmsg="";
	if ($handle) {
    		while (($buffer = fgets($handle, 409600)) !== false) {
			if(strpos($buffer,"<coordinates>")!==false) {
				$iscoor=1;
				continue;
			} 
			if(strpos($buffer,"</coordinates>")!==false) {
				break;
			}
			if($iscoor) {
				$buffer=trim($buffer);
				//echo "get ".$buffer."<br>";
				$s=explode(" ",$buffer);
				foreach ( $s as $v) {
					$r=explode(",",$v);
					//echo $r[0]."/".$r[1]."/".$r[2]."<br>";
					$newmsg = sprintf("%02d%05.2f", floor($$r[0]), ($r[0]-floor($r[0]))*60);
					$newmsg = $newmsg.sprintf("%03d%05.2f", floor($r[1]), ($r[1]-floor($r[1]))*60);
					if($oldmsg==$newmsg) // duplicate msg
						continue;
					$oldmsg=$newmsg;
					sendaprs($call,$r[1],$r[0],$r[2],$ts);
					echo ".";
					flush();
					sleep(1);
				}
			}
    		}
	}

} 
$call =@$_REQUEST["call"];
$lat =@$_REQUEST["lat"];
$lon =@$_REQUEST["lon"];
$ts =@$_REQUEST["ts"];
$desc =@$_REQUEST["desc"];
$url = @$_REQUEST["url"];

// http://apis.map.qq.com/uri/v1/marker?marker=coord:29.260122,120.335999;title:潘塘村:
// http://map.qq.com/?type=marker&isopeninfowin=1&markertype=1&pointx=120.336&pointy=29.2601&name=%5B%E4%BD%8D%E7%BD%AE%5D&addr=%E6%BD%98%E5%A1%98%E6%9D%91(%E9%87%91%E5%8D%8E%E5%B8%82%E4%B8%9C%E9%98%B3%E5%B8%82%E5%9F%8E%E4%B8%9C%E8%A1%97%E9%81%93%E6%BD%98%E5%A1%98%E6%9D%91)&ref=WeChat



if($ts=="") $ts="/$";

if($call=="") {
	$call = $_COOKIE["call"];
	$ts = $_COOKIE["ts"];
	$desc = $_COOKIE["desc"];
	if($ts=="") $ts="/$";
	$call = strtoupper($call);
}else {
	$lat =@$_REQUEST["lat"];
	$lon =@$_REQUEST["lon"];
	$ts =@$_REQUEST["ts"];
	$desc =@$_REQUEST["desc"];
	$call = strtoupper($call);
	setcookie("call",$call,time()+24*3600*365);
	setcookie("ts",$ts,time()+24*3600*365);
	setcookie("desc",$desc,time()+24*3600*365);

	if($url != "")  {
    		if (($u = strstr($url, "marker=coord:"))) {
        		$u = explode(":", $u);
        		$u = $u[1];
        		$u = explode(";", $u);
        		$u = $u[0];
        		$u = explode(",", $u);
        		$x = $u[1];
        		$y = $u[0];
    		} else {
        		$u = explode("&", $url);
        		$x = 0;
        		$y = 0;
        		for($i = 0; $i < count($u); $i++) {
            			$r = explode('=', $u[$i]);
            			if($r[0] == "pointx")
                			$x = $r[1];
            			else if($r[0] == "pointy")
                			$y = $r[1];
        		}
    		}
	
		include "GpsPositionTransform.class.php";

    		if(GpsPositionTransform::outOfChina($y, $x)) {
    		} else {
        		$r = GpsPositionTransform::gcj_To_Gps84($y, $x);
        		$x = $r["lon"];
        		$y = $r["lat"];
    		}
		$latui = $y;
		$lonui = $x;
	} else {
		$lati = explode(".",$lat);
		if(count($lati)<=2) 
			$latui = $lat;
		else if(strlen($lati[2])==3)
			$latui = $lati[0] + ($lati[1]+$lati[2]/1000)/60;
		else
			$latui = $lati[0] + $lati[1]/60+$lati[2]/3600;

		$loni = explode(".",$lon);
		if(count($loni)<=2) 
			$lonui = $lon;
		else if(strlen($loni[2])==3)
			$lonui = $loni[0] + ($loni[1]+$loni[2]/1000)/60;
		else
			$lonui = $loni[0] + $loni[1]/60 +$loni[2]/3600;
	}
}
?>

<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta name="viewport" content="initial-scale=1.0" />
</head>
<body>
<?php

if(@$_REQUEST["send"]==1) 
	sendaprs($call,$latui,$lonui,$desc,$ts);

echo "<h3>发送APRS位置信息</h3>\n";
echo "<form name=aprs action=index.php method=post>";
echo "<input type=hidden name=send value=1>";
echo "<table>";
echo "<tr><td>呼号：</td><td><input name=call value=\"".$call."\"></td></tr>\n";
echo "<tr><td>纬度：</td><td><input id=lat name=lat value=\"".$lat."\"></td></tr>\n";
echo "<tr><td>经度：</td><td><input id=lon name=lon value=\"".$lon."\"></td></tr>\n";
echo "<tr><td>类型：</td><td><input name=ts value=\"".$ts."\"></td></tr>\n";
echo "<tr><td>消息：</td><td><input name=desc value=\"".$desc."\"></td></tr>\n";
echo "<tr><td>微信：</td><td><input name=url size=200></td></tr>\n";
echo "<tr><td colspan=2>如果提供了微信位置链接，将从微信链接获取经纬度信息</td></tr>\n";
echo "<tr><td colspan=2><br><button type=button onClick=\"get_location();\">当前位置</button>&nbsp;&nbsp;&nbsp;";
echo "<input type=submit value=\"发送信息\"></input></td></tr>\n";
echo "</table>";
echo "</form><p>";
echo "经纬度格式（依据小数点数或数字位数）<br>\n";
echo "<table>";
echo "<tr><td>ddd.dddddd</td><td>度.度</td><td>31.12035º</td></tr>";
echo "<tr><td>ddd.mm.ss</td><td>度.分.秒</td><td>31º12'42\"</td></tr>";
echo "<tr><td>ddd.mm.mmm</td><td>度.分.分（3位）</td><td>31º10.335'</td></tr>";
echo "</table>";
?>

<div id=out><?php if($msg!="") echo "<font color=green>".$msg."</font>"; ?></div>

<p>
<h3>发送轨迹文件(kml格式)</h3>
<form enctype="multipart/form-data" action="index.php" method="POST">
<!-- MAX_FILE_SIZE must precede the file input field -->
<input type="hidden" name="MAX_FILE_SIZE" value="300000" />
<input type=hidden name=track value=1>
<!-- Name of input element determines name in $_FILES array -->
轨迹文件(kml): <input name="userfile" type="file" /><br>
呼号：<input name=call><br>
类型：<input name=ts value="/$"><br>
<input type="submit" value="发送轨迹" />
</form>

<script type="text/javascript">
function get_location(){
	var output = document.getElementById("out");

	if (!navigator.geolocation){
		output.innerHTML = "<p>您的浏览器不支持地理位置</p>";
		return;
	}

	function success(position) {
		var latitude  = position.coords.latitude;
		var longitude = position.coords.longitude;

		output.innerHTML = '<p>获取位置成功</p>';
		document.getElementById("lon").value = longitude;
		document.getElementById("lat").value = latitude;
  	};

  	function error(err) {
    		output.innerHTML = "无法获取位置 Error:" + err.code + ': ' + err.message;
  	};

  	output.innerHTML = "<p>Locating…</p>";

	var options = {
  		enableHighAccuracy: true,
  		timeout: 5000,
  		maximumAge: 0
	};
  	navigator.geolocation.getCurrentPosition(success, error, options);
}
</script>
