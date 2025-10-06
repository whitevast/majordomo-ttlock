<?php
/*
* @version 0.1 (wizard)
*/
  if ($this->owner->name=='panel') {
   $out['CONTROLPANEL']=1;
  }
  $table_name='ttlock_devices';
  $rec=SQLSelectOne("SELECT * FROM $table_name WHERE ID='$id'");
  if ($this->mode=='update') {
	if($this->tab=='') {
		$datalock = $this->update($rec);
		if($datalock !== true){
			$out['ERR'] = $datalock;
			$ok = 0;
		}
		else $out['OK']=1;
	}
	// step: data
	//UPDATING RECORD
  }
  // Вкладка Информация
  if ($this->tab=='') {
	  $passage = "";
	  $passage_shedule = json_decode($rec['PASSAGE_SHED'], true);
	  foreach($passage_shedule['weekDays'] as $day){
		  if($day == 1) $passage = $passage."Пн ";
		  else if($day == 2) $passage = $passage."Вт ";
		  else if($day == 3) $passage = $passage."Ср ";
		  else if($day == 4) $passage = $passage."Чт ";
		  else if($day == 5) $passage = $passage."Пт ";
		  else if($day == 6) $passage = $passage."Сб ";
		  else if($day == 7) $passage = $passage."Вс ";
	  }
	  if($passage_shedule['isAllDay'] == 1) $passage = $passage."Весь день";
	  else{
		  $passage = $passage."с ";
		  $start_time = date('H:i', mktime(0, 0, 0, date("m"), date("d"), date("Y")) + $passage_shedule['startTime']*60);
		  $end_time = date('H:i', mktime(0, 0, 0, date("m"), date("d"), date("Y")) + $passage_shedule['endTime']*60);
		  $passage = $passage.$start_time." до ".$end_time."  ";
		  $passage_act = $this->chekPassage($rec['PASSAGE_SHED']) ? '<span class="text-success" title="Статус режима">Активно</span>' : '<span class="text-danger" title="Статус режима">Неактивно</span>';
		  $out['PASS_SHED'] = $passage.$passage_act;
	  }
  }
  
  // Вкладка Данные
  if ($this->tab=='data') {
   //dataset2
   $new_id=0;
   $delete_id = gr('delete_id');
   if ($delete_id) {
    SQLExec("DELETE FROM ttlock_info WHERE ID='".(int)$delete_id."'");
   }
   $properties=SQLSelect("SELECT * FROM ttlock_info WHERE DEVICE_ID='".$rec['ID']."' ORDER BY ID");
   $total=count($properties);
   for($i=0;$i<$total;$i++) {
    if ($properties[$i]['ID']==$new_id) continue;
    if ($this->mode=='update') {
		$old_linked_object=$properties[$i]['LINKED_OBJECT'];
		$old_linked_property=$properties[$i]['LINKED_PROPERTY'];
		global ${'linked_object'.$properties[$i]['ID']};
		$properties[$i]['LINKED_OBJECT']=trim(${'linked_object'.$properties[$i]['ID']});
		global ${'linked_property'.$properties[$i]['ID']};
		$properties[$i]['LINKED_PROPERTY']=trim(${'linked_property'.$properties[$i]['ID']});
		global ${'linked_method'.$properties[$i]['ID']};
		$properties[$i]['LINKED_METHOD']=trim(${'linked_method'.$properties[$i]['ID']});
		// Если юзер удалил привязанные свойство и метод, но забыл про объект, то очищаем его.
		if ($properties[$i]['LINKED_OBJECT'] != '' && ($properties[$i]['LINKED_PROPERTY'] == '' && $properties[$i]['LINKED_METHOD'] == '')) {
			$properties[$i]['LINKED_OBJECT'] = '';
		}
		SQLUpdate('ttlock_info', $properties[$i]);
		if ($old_linked_object && $old_linked_object!=$properties[$i]['LINKED_OBJECT'] || $old_linked_property && $old_linked_property!=$properties[$i]['LINKED_PROPERTY']) {
		removeLinkedProperty($old_linked_object, $old_linked_property, $this->name);
		}
		if ($properties[$i]['LINKED_OBJECT'] && $properties[$i]['LINKED_PROPERTY']) {
		addLinkedProperty($properties[$i]['LINKED_OBJECT'], $properties[$i]['LINKED_PROPERTY'], $this->name);
		}
     }
   }
   $out['PROPERTIES']=$properties;   
  }
  //Вкладка команды
   if ($this->tab=='commands') {
	$commands=SQLSelect("SELECT * FROM ttlock_commands WHERE DEVICE_ID='".$rec['ID']."' ORDER BY ID");
	$total=count($commands);
	for($i=0;$i<$total;$i++) {
		if ($this->mode=='update') {
			$old_linked_object=$commands[$i]['LINKED_OBJECT'];
			$old_linked_property=$commands[$i]['LINKED_PROPERTY'];
			global ${'linked_object'.$commands[$i]['ID']};
			$commands[$i]['LINKED_OBJECT']=trim(${'linked_object'.$commands[$i]['ID']});
			global ${'linked_property'.$commands[$i]['ID']};
			$commands[$i]['LINKED_PROPERTY']=trim(${'linked_property'.$commands[$i]['ID']});
			// Если юзер удалил привязанные свойство и метод, но забыл про объект, то очищаем его.
			if ($commands[$i]['LINKED_OBJECT'] != '' && ($commands[$i]['LINKED_PROPERTY'] == '' && $commands[$i]['LINKED_METHOD'] == '')) {
				$commands[$i]['LINKED_OBJECT'] = '';
			}
			SQLUpdate('ttlock_commands', $commands[$i]);
			if ($old_linked_object && $old_linked_object!=$commands[$i]['LINKED_OBJECT'] && $old_linked_property && $old_linked_property!=$commands[$i]['LINKED_PROPERTY']) {
			removeLinkedProperty($old_linked_object, $old_linked_property, $this->name);
			}
			if ($commands[$i]['LINKED_OBJECT'] && $commands[$i]['LINKED_PROPERTY']) {
			addLinkedProperty($commands[$i]['LINKED_OBJECT'], $commands[$i]['LINKED_PROPERTY'], $this->name);
			}
		}
		if($this->mode == 'switch'){
			if($commands[$i]['ID'] == $this->id){
				$status = SQLSelectOne("SELECT VALUE FROM ttlock_info WHERE DEVICE_ID='{$commands[$i]['DEVICE_ID']}' AND TITLE='status'");
				if($status['VALUE'] == 0) $com = 4;
				else $com = 5;
				$device = SQLSelectOne("SELECT * FROM $table_name WHERE ID='{$commands[$i]['DEVICE_ID']}'");
				$device['CLIENT_ID'] = $this->config['CLIENT_ID'];
				$device['TOKEN']=$this->config['TOKEN'];
				if($this->send($device, $com)){
					$this->Writelog("Команда ".$commands[$i]['NAME']." (".!$commands[$i]['VALUE'].") отправлена на ".$device['TITLE']);
				}
			}
		}
	}
	$out['COMMANDS']=$commands;   
  }
  if (is_array($rec)) {
   foreach($rec as $k=>$v) {
    if (!is_array($v)) {
     $rec[$k]=htmlspecialchars($v);
    }
   }
  }
  outHash($rec, $out);
  $out['LOG']=nl2br($rec['LOG']);
