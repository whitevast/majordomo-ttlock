<?php
/**
* TTLock 
* @package project
*/
//
//
class ttlock extends module {
/**
* ttlock
*
* @access private
*/
function __construct() {
  $this->name="ttlock";
  $this->title="TTLock";
  $this->module_category="<#LANG_SECTION_DEVICES#>";
  $this->checkInstalled();
  $this->getConfig();
  $this->debug = $this->config['LOG_DEBMES'] == 1 ? true : false;
}
/**
* saveParams
*
* Saving module parameters
*
* @access public
*/
function saveParams($data=1) {
 $p=array();
 if (isset($this->id)) {
  $p["id"]=$this->id;
 }
 if (isset($this->view_mode)) {
  $p["view_mode"]=$this->view_mode;
 }
 if (isset($this->edit_mode)) {
  $p["edit_mode"]=$this->edit_mode;
 }
 if (isset($this->data_source)) {
  $p["data_source"]=$this->data_source;
 }
 if (isset($this->tab)) {
  $p["tab"]=$this->tab;
 }
 return parent::saveParams($p);
}
/**
* getParams
*
* Getting module parameters from query string
*
* @access public
*/
function getParams() {
  global $id;
  global $mode;
  global $view_mode;
  global $edit_mode;
  global $data_source;
  global $tab;
  if (isset($id)) {
   $this->id=$id;
  }
  if (isset($mode)) {
   $this->mode=$mode;
  }
  if (isset($view_mode)) {
   $this->view_mode=$view_mode;
  }
  if (isset($edit_mode)) {
   $this->edit_mode=$edit_mode;
  }
  if (isset($data_source)) {
   $this->data_source=$data_source;
  }
  if (isset($tab)) {
   $this->tab=$tab;
  }
}
/**
* Run
*
* Description
*
* @access public
*/
function run() {
 global $session;
  $out=array();
  if ($this->action=='admin') {
   $this->admin($out);
  } else {
   $this->usual($out);
  }
  if (isset($this->owner->action)) {
   $out['PARENT_ACTION']=$this->owner->action;
  }
  if (isset($this->owner->name)) {
   $out['PARENT_NAME']=$this->owner->name;
  }
  $out['VIEW_MODE']=$this->view_mode;
  $out['EDIT_MODE']=$this->edit_mode;
  $out['MODE']=$this->mode;
  $out['ACTION']=$this->action;
  $out['DATA_SOURCE']=$this->data_source;
  $out['TAB']=$this->tab;
  $this->data=$out;
  $p=new parser(DIR_TEMPLATES.$this->name."/".$this->name.".html", $this->data, $this);
  $this->result=$p->result;
}
/**
* BackEnd
*
* Module backend
*
* @access public
*/
function admin(&$out) {
 $this->getConfig();
 $out['CLIENT_ID']=$this->config['CLIENT_ID'];
 $out['CLIENT_SECRET']=$this->config['CLIENT_SECRET'];
 $out['LOG_DEBMES']=$this->config['LOG_DEBMES'];
 $out['TOKEN']=$this->config['TOKEN'];
 $out['REFRESH_TOKEN']=$this->config['REFRESH_TOKEN'];
 $out['UID']=$this->config['UID'];
 if ($this->view_mode=='update_settings') {
   $this->config['CLIENT_ID']=gr('client_id');
   $this->config['CLIENT_SECRET']=gr('client_secret');
   $this->config['LOG_DEBMES']=gr('log_debmes');
   if($this->config['UID'] == ''){
	   $user['CLIENT_ID'] = $this->config['CLIENT_ID'];
	   $user['CLIENT_SECRET'] = $this->config['CLIENT_SECRET'];
	   $user['USERNAME'] = gr('username');
	   $user['PASSWORD'] = gr('password');
	   $data = $this->send($user, 1);
	   if($data){
			if(isset($data['errcode'])){
				$out['ERR'] = $data['errmsg'];
			} else {
				$this->config['UID'] = $data['uid'];
				$this->config['TOKEN'] = $data['access_token'];
				$this->config['REFRESH_TOKEN'] = $data['refresh_token'];
			}
	   } else $out['ERR'] = "Нет ответа от сервера.";
		if(!isset($out['ERR'])){
		$this->saveConfig();
		// Запрашиваем информацию о замках
		$lock['CLIENT_ID'] = $this->config['CLIENT_ID'];
		$lock['TOKEN']=$this->config['TOKEN'];
		$data = $this->send($this->config, 2);
		foreach($data['list'] as $locks){
			$lock['LOCK_ID'] = $locks['lockId'];
			$table_name = 'ttlock_devices';
			$device=SQLSelectOne("SELECT * FROM $table_name WHERE LOCK_ID='".$lock['LOCK_ID']."'");
			$datalock = $this->send($lock, 3);
			if($datalock){
				if(isset($data['errcode'])){
					$out['ERR'] = $data['errmsg'];
				} else {
					$datalock = $this->send($lock, 3);
					$device['TITLE'] = $datalock['lockAlias'];
					$device['MODEL'] = $datalock['modelNum'];
					$device['LOCK_ID'] = $datalock['lockId'];
					$device['MAC'] = $datalock['lockMac'];
					$device['BAT'] = $datalock['electricQuantity'];
					$device['GATE'] = $datalock['hasGateway'];
				}
			} else $out['ERR'] = "Нет ответа от сервера.";
			if (isset($device['ID'])) {
				SQLUpdate($table_name, $device); // update
			} else {
				$device['ID']=SQLInsert($table_name, $device); // adding new record
				//Заполняем таблицу информации
				$info = [['electricQuantity','Заряд батареи'],
				['recordTypeFromLock','recordTypeFromLock'],
				['recordType','recordType'],
				['success','success'],
				['keyboardPwd','keyboardPwd'],
				['username','Имя пользователя']];
				$code['DEVICE_ID'] = $device['ID'];
				for ($i=0; $i<count($info); $i++){
					$code['TITLE'] = $info[$i][0];
					$code['NAME'] = $info[$i][1];
					if($code['TITLE'] == "electricQuantity") $code['VALUE'] = $device['BAT'];
					else $code['VALUE'] = "";
					$code['UPDATED'] = date('Y-m-d H:i:s');
					SQLInsert('ttlock_info', $code);
				}
				//Заполняем таблицу команд
				$commands = [['lock','Открыть/закрыть']];
				$com['DEVICE_ID'] = $device['ID'];
				for ($i=0; $i<count($commands); $i++){
					$com['TITLE'] = $commands[$i][0];
					$com['NAME'] = $commands[$i][1];
					$com['VALUE'] = "0";
					$com['UPDATED'] = date('Y-m-d H:i:s');
					SQLInsert('ttlock_commands', $com);
				}
			}
		}
	}
   }
   if (isset($_POST['change'])) $this->config['UID'] = '';
   if(!isset($out['ERR'])){
		$this->saveConfig();
		$this->redirect("?");
   }
 }
 if (isset($this->data_source) && !isset($_GET['data_source']) && !isset($_POST['data_source'])) {
  $out['SET_DATASOURCE']=1;
 }
 if ($this->data_source=='ttlock_devices' || $this->data_source=='') {
  if ($this->view_mode=='' || $this->view_mode=='search_ttlock_devices') {
   $this->search_ttlock_devices($out);
  }
  if ($this->view_mode=='edit_ttlock_devices') {
   $this->edit_ttlock_devices($out, $this->id);
  }
  if ($this->view_mode=='delete_ttlock_devices') {
   $this->delete_ttlock_devices($this->id);
   $this->redirect("?data_source=ttlock_devices");
  }
 }
 if ($this->data_source=='ttlock_info') {
  if ($this->view_mode=='' || $this->view_mode=='search_ttlock_info') {
   $this->search_ttlock_info($out);
  }
  if ($this->view_mode=='edit_ttlock_info') {
   $this->edit_ttlock_info($out, $this->id);
  }
 }
}


/**
* FrontEnd
*
* Module frontend
*
* @access public
*/
function usual(&$out) {
 $this->admin($out);
}
/**
* ttlock_devices search
*
* @access public
*/
 function search_ttlock_devices(&$out) {
  require(dirname(__FILE__).'/ttlock_devices_search.inc.php');
 }
/**
* ttlock_devices edit/add
*
* @access public
*/
 function edit_ttlock_devices(&$out, $id) {
  require(dirname(__FILE__).'/ttlock_devices_edit.inc.php');
 }
/**
* ttlock_devices delete record
*
* @access public
*/
 function delete_ttlock_devices($id) {
  $rec=SQLSelectOne("SELECT * FROM ttlock_devices WHERE ID='$id'");
  // some action for related tables
  SQLExec("DELETE FROM ttlock_devices WHERE ID='".$rec['ID']."'");
  $properties=SQLSelect("SELECT * FROM ttlock_info WHERE DEVICE_ID='".$rec['ID']."' AND LINKED_OBJECT != '' AND LINKED_PROPERTY != ''");
    foreach($properties as $prop) {
		removeLinkedProperty($prop['LINKED_OBJECT'], $prop['LINKED_PROPERTY'], $this->name);
	}
  SQLExec("DELETE FROM ttlock_info WHERE DEVICE_ID='".$rec['ID']."'");
  $properties=SQLSelect("SELECT * FROM ttlock_commands WHERE DEVICE_ID='".$rec['ID']."' AND LINKED_OBJECT != '' AND LINKED_PROPERTY != ''");
    foreach($properties as $prop) {
		removeLinkedProperty($prop['LINKED_OBJECT'], $prop['LINKED_PROPERTY'], $this->name);
	}
  SQLExec("DELETE FROM ttlock_commands WHERE DEVICE_ID='".$rec['ID']."'");
 }
/**
* ttlock_info search
*
* @access public
*/
 function search_ttlock_info(&$out) {
  require(dirname(__FILE__).'/ttlock_info_search.inc.php');
 }
/**
* ttlock_info edit/add
*
* @access public
*/
 function edit_ttlock_info(&$out, $id) {
  require(dirname(__FILE__).'/ttlock_info_edit.inc.php');
 }
 
 //Действия при изменении привязанных свойств
 function propertySetHandle($object, $property, $value) {
  $this->getConfig();
   $table='ttlock_commands';
   $properties=SQLSelect("SELECT ID FROM $table WHERE LINKED_OBJECT LIKE '".DBSafe($object)."' AND LINKED_PROPERTY LIKE '".DBSafe($property)."'");
   $total=count($properties);
   if ($total) {
    for($i=0;$i<$total;$i++) {
     $command = SQLSelectOne("SELECT * FROM ttlock_commands WHERE ID='".(int)$properties[$i]['ID']."'");
	 $device = SQLSelectOne("SELECT * FROM ttlock_devices WHERE ID='".(int)$command['DEVICE_ID']."'");
	 $device['CLIENT_ID'] = $this->config['CLIENT_ID'];
	 $device['TOKEN']=$this->config['TOKEN'];
	 switch($command['TITLE']){
		 case "lock":
			if($value == "0") $com = 5;
			else if($value == "1") $com = 4;
			break;
	 }
	 if($this->send($device, $com)){
		 $this->Writelog("Команда ".$command['NAME']." (".$value.") отправлена на ".$device['TITLE']);
	 }
	 $command['VALUE']=$value;
	 $command['UPDATED'] = date('Y-m-d H:i:s');
	 SQLUpdate('ttlock_commands', $command);
    }
   }
 }
function processCycle() {
	$this->getConfig();
}
  //Запись в привязанное свойство
function setProperty($device, $value, $params = ''){
    if ($device['LINKED_OBJECT'] && $device['LINKED_PROPERTY']) {
		setGlobal($device['LINKED_OBJECT'] . '.' . $device['LINKED_PROPERTY'], $value, array($this->name=>1), $this->name);
    }
	if ($device['LINKED_OBJECT'] && $device['LINKED_METHOD']) {
     $params['VALUE'] = $value;
	 callMethodSafe($device['LINKED_OBJECT'] . '.' . $device['LINKED_METHOD'], $params);
    }
}

// Глобальный поиск по модулю
 function findData($data) {
    $res = array();
	//TTLock devices
    $devices = SQLSelect("SELECT ID, TITLE, MODEL FROM ttlock_devices where `TITLE` like '%" . DBSafe($data) . "%' OR `MODEL` like '%" . DBSafe($data) . "%' OR `MAC` like '%" . DBSafe($data) . "%'  order by TITLE");
	foreach($devices as $device){
         $res[]= '&nbsp;<span class="label label-info">devices</span>&nbsp;<a href="/panel/ttlock.html?md=ttlock&inst=adm&data_source=&view_mode=edit_ttlock_devices&id=' . $device['ID'] . '.html">' . $device['TITLE'].($device['MODEL'] ? '<small style="color: gray;padding-left: 5px;"><i class="glyphicon glyphicon-arrow-right" style="font-size: .8rem;vertical-align: text-top;color: lightgray;"></i> ' . $device['MODEL'] . '</small>' : ''). '</a>';
    }
    //TTLock info
    $infos = SQLSelect("SELECT ID, TITLE, NAME, DEVICE_ID FROM ttlock_info where `TITLE` like '%" . DBSafe($data) . "%' OR `NAME` like '%" . DBSafe($data) . "%' order by TITLE");
    foreach($infos as $info){
		$alarm = SQLSelectOne('SELECT TITLE FROM ttlock_devices WHERE ID="'.$info['DEVICE_ID'].'"');
		$res[]= '&nbsp;<span class="label label-info">'.$alarm['TITLE'].'</span>&nbsp;<span class="label label-primary">info</span>&nbsp;<a href="/panel/ttlock.html?md=ttlock&inst=adm&data_source=&view_mode=edit_ttlock_devices&tab=data&id=' . $info['DEVICE_ID'] . '.html">' . $info['NAME'].'</a>';
    }
	 //TTLock commands
    $cmds = SQLSelect("SELECT ID, TITLE, NAME, DEVICE_ID FROM ttlock_commands where `TITLE` like '%" . DBSafe($data) . "%' OR `NAME` like '%" . DBSafe($data) . "%' order by TITLE");
    foreach($cmds as $cmd){
		$alarm = SQLSelectOne('SELECT TITLE FROM ttlock_devices WHERE ID="'.$info['DEVICE_ID'].'"');
		$res[]= '&nbsp;<span class="label label-info">'.$alarm['TITLE'].'</span>&nbsp;<span class="label label-primary">command</span>&nbsp;<a href="/panel/ttlock.html?md=ttlock&inst=adm&data_source=&view_mode=edit_ttlock_devices&tab=commands&id=' . $cmd['DEVICE_ID'] . '.html">' . $cmd['NAME'].'</a>';
    }
    return $res;
 }
 
/**
* Install
*
* Module installation routine
*
* @access private
*/
 function install($data='') {
  parent::install();
 }
/**
* Uninstall
*
* Module uninstall routine
*
* @access public
*/
 function uninstall() {
  $id = SQLSelect('SELECT ID FROM ttlock_devices');
  for($i=0; $i<count($id); $i++){
	$this->delete_ttlock_devices($id[$i]['ID']);
  }
  SQLExec('DROP TABLE IF EXISTS ttlock_devices');
  SQLExec('DROP TABLE IF EXISTS ttlock_info');
  SQLExec('DROP TABLE IF EXISTS ttlock_commands');
  unlink('..//../webhook_ttlock.php');
  parent::uninstall();
 }
/**
* dbInstall
*
* Database installation routine
*
* @access private
*/
 function dbInstall($data) {
  $data = <<<EOD
 ttlock_devices: ID int(10) unsigned NOT NULL auto_increment
 ttlock_devices: TITLE varchar(100) NOT NULL DEFAULT ''
 ttlock_devices: MODEL varchar(20) NOT NULL DEFAULT ''
 ttlock_devices: LOCK_ID int(10) NOT NULL DEFAULT '0'
 ttlock_devices: MAC varchar(20) NOT NULL DEFAULT ''
 ttlock_devices: BAT varchar(20) NOT NULL DEFAULT ''
 ttlock_devices: GATE int(10) NOT NULL DEFAULT '0'
 ttlock_devices: LOG text NOT NULL DEFAULT ''
 ttlock_info: ID int(10) unsigned NOT NULL auto_increment
 ttlock_info: DEVICE_ID int(10) NOT NULL DEFAULT '0'
 ttlock_info: TITLE varchar(100) NOT NULL DEFAULT ''
 ttlock_info: NAME varchar(255) NOT NULL DEFAULT ''
 ttlock_info: VALUE varchar(20) NOT NULL DEFAULT ''
 ttlock_info: LINKED_OBJECT varchar(100) NOT NULL DEFAULT ''
 ttlock_info: LINKED_PROPERTY varchar(100) NOT NULL DEFAULT ''
 ttlock_info: LINKED_METHOD varchar(100) NOT NULL DEFAULT ''
 ttlock_info: UPDATED datetime
 ttlock_commands: ID int(10) unsigned NOT NULL auto_increment
 ttlock_commands: DEVICE_ID int(10) NOT NULL DEFAULT '0'
 ttlock_commands: TITLE varchar(255) NOT NULL DEFAULT ''
 ttlock_commands: NAME varchar(255) NOT NULL DEFAULT ''
 ttlock_commands: VALUE int(10) NOT NULL DEFAULT '0'
 ttlock_commands: LINKED_OBJECT varchar(100) NOT NULL DEFAULT ''
 ttlock_commands: LINKED_PROPERTY varchar(100) NOT NULL DEFAULT ''
 ttlock_commands: UPDATED datetime
EOD;
  parent::dbInstall($data);
 }
// --------------------------------------------------------------------

/////////////////////////My_functions//////////////////////////////////

function send($device, $action, $data = ""){ //1 - получение токена, 2 - запрос информации о замках, 3 - расширенная информация о замке 4 - запереть, 5 - отпереть, 6 - информация о состоянии замка, 7 - запрос информации о сободном проходе, 8 - настроить расписание свободного прохода
	$api = "https://euapi.ttlock.com/v3/lock/";
	$date = time()."000";
	if($action != 1) $lockdata = "clientId=".$device['CLIENT_ID']."&accessToken=".$device['TOKEN']."&lockId=".$device['LOCK_ID']."&date=$date";
	switch ($action){
		case 1:
			$ip = "https://euapi.ttlock.com/oauth2/token";
			$post = "clientId=".$device['CLIENT_ID']."&clientSecret=".$device['CLIENT_SECRET']."&username=".$device['USERNAME']."&password=".md5($device['PASSWORD']);
			break;
		case 2:
			$ip = $api."list?clientId=".$device['CLIENT_ID']."&accessToken=".$device['TOKEN']."&pageNo=1&pageSize=20&date=$date";;
			break;
		case 3:
			$ip = $api."detail?".$lockdata;
			break;
		case 4:
			$ip = $api."lock";
			$post = $lockdata;
			break;
		case 5:
			$ip = $api."unlock";
			$post = $lockdata;
			break;
		case 6:
			$ip = $api."queryOpenState?".$lockdata;
			break;
		case 7:
			$ip = $api."getPassageModeConfiguration?".$lockdata;
			break;
		case 8:
			$ip = $api."configurePassageMode";
			$post = "clientId=".$device['CLIENT_ID']."&accessToken=".$device['TOKEN']."&lockId=".$device['LOCK_ID']."&passageMode=1&cyclicConfig=[{'isAllDay':2,'startTime':1020,'endTime':1320,'weekDays':[6,7]}]&type=2&date=$date";
		
	}
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $ip);
	curl_setopt($ch, CURLOPT_HTTPHEADER, Array('Content-Type: application/x-www-form-urlencoded'));
	if (isset($post)){
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
	}
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
	curl_setopt($ch, CURLOPT_TIMEOUT, 5);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$html = curl_exec($ch);
	if(isset($html)) $data = json_decode($html, true);
	else $data = false;
	curl_close($ch);
	return $data;
}

function receive($data){
	if(!isset($data['records'])){
		$this->WriteLog("Получены нестандартные данные:".$data);
		return;
	}
	$records = json_decode($data['records'], true)[0];
	$device = SQLSelectOne('SELECT * FROM ttlock_devices WHERE LOCK_ID="'.$records['lockId'].'"');
	if($device['ID']){
		$info = SQLSelect("SELECT * FROM ttlock_info WHERE DEVICE_ID='".$device['ID']."'");
		foreach($info as $inf){
			if($inf['TITLE'] == 'electricQuantity'){
				if(isset($records['electricQuantity'])){
					if($inf['VALUE'] != $records['electricQuantity']){
						$params['OLD_VALUE'] = $inf['VALUE'];
						$params['NEW_VALUE'] = $records['electricQuantity'];
						$this->setProperty($inf, $records['electricQuantity'], $params);
						$inf['VALUE'] = $records['electricQuantity'];
						$inf['UPDATED'] = date('Y-m-d H:i:s');
						SQLUpdate('ttlock_info', $inf);
						$device['BAT'] = $records['electricQuantity'];
					}
				}
			}
			else{
				if(isset($records[$inf['TITLE']])){
					$params['OLD_VALUE'] = $inf['VALUE'];
					$params['NEW_VALUE'] = $records[$inf['TITLE']];
					$this->setProperty($inf, $records[$inf['TITLE']], $params);
					$inf['VALUE'] = $records[$inf['TITLE']];
					$inf['UPDATED'] = date('Y-m-d H:i:s');
					SQLUpdate('ttlock_info', $inf);
				}
			}
		}
		require(dirname(__FILE__).'/ttlock_recordtype.inc.php');
		$key = $records['keyboardPwd'] != "" ? ". Код доступа: ".$records['keyboardPwd'] : "";
		$device['LOG'] = date('Y-m-d H:i:s')." ".$recordTypeFromLock[$records['recordTypeFromLock']].". Пользователь: ".$records['username'].$key."\n".$device['LOG'];
		if(substr_count($device['LOG'], "\n") > 30){ //очищаем самые давние события, если их более 30
			$device['LOG'] = substr($device['LOG'], 0, strrpos(trim($device['LOG']), "\n"));
		}
		SQLUpdate('ttlock_devices', $device);
	}
}

function WriteLog($msg){
     if ($this->debug) {
        DebMes($msg, $this->name);
     }
  }
}
