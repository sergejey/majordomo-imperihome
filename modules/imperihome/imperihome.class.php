<?php
/**
* ImperiHome 
* @package project
* @author Wizard <sergejey@gmail.com>
* @copyright http://majordomo.smartliving.ru/ (c)
* @version 0.1 (wizard, 16:12:11 [Dec 03, 2017])
*/
//
//
class imperihome extends module {
/**
* imperihome
*
* Module class constructor
*
* @access private
*/
function imperihome() {
  $this->name="imperihome";
  $this->title="ImperiHome";
  $this->module_category="<#LANG_SECTION_APPLICATIONS#>";
  $this->checkInstalled();
}
/**
* saveParams
*
* Saving module parameters
*
* @access public
*/
function saveParams($data=0) {
 $p=array();
 if (IsSet($this->id)) {
  $p["id"]=$this->id;
 }
 if (IsSet($this->view_mode)) {
  $p["view_mode"]=$this->view_mode;
 }
 if (IsSet($this->edit_mode)) {
  $p["edit_mode"]=$this->edit_mode;
 }
 if (IsSet($this->tab)) {
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
  if (IsSet($this->owner->action)) {
   $out['PARENT_ACTION']=$this->owner->action;
  }
  if (IsSet($this->owner->name)) {
   $out['PARENT_NAME']=$this->owner->name;
  }
  $out['VIEW_MODE']=$this->view_mode;
  $out['EDIT_MODE']=$this->edit_mode;
  $out['MODE']=$this->mode;
  $out['ACTION']=$this->action;
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

 $out['API_DEBUG']=(int)$this->config['API_DEBUG'];
 if ($this->view_mode=='update_settings') {
   global $api_debug;
   $this->config['API_DEBUG']=$api_debug;
   $this->saveConfig();
   $this->redirect("?");
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

}

function api($params) {
    $this->getConfig();
    if ($this->config['API_DEBUG']) {
        DebMes($_SERVER['REQUEST_URI'],'imperihome');
    }
    $result = array();
    if ($params[0]=='devices') {
        $device_id=(int)preg_replace('/\D/','',$params[1]);
        if ($device_id) {
            $device=SQLSelectOne("SELECT * FROM devices WHERE ID=".$device_id);
            $action = '';
            $processed = 0;
            if ($params[2] == 'action' && $params[3]!='') {
                $action = $params[3];
                $action_params = $params[4];
            }
            if ($device['TYPE']=='relay' && $action=='setStatus') {
                setGlobal($device['LINKED_OBJECT'].'.status',$action_params);
            }
            if ($device['TYPE']=='button' && $action=='launchScene') {
                callMethod($device['LINKED_OBJECT'].'.pressed');
            }
            if ($device['TYPE']=='thermostat' && $action=='setMode') {
                //setGlobal($device['LINKED_OBJECT'].'.status',$action_params);
                if (strtolower($action_params)=='eco') {
                    callMethod($device['LINKED_OBJECT'].'.turnOff');
                } else {
                    callMethod($device['LINKED_OBJECT'].'.turnOn');
                }
            }
            if ($params[2] == 'value' && $params[3]=='histo') {
                $from = (int)($params[4]/1000);
                $to = (int)($params[5]/1000);
                $history = array();
                $history_data = getHistory($device['LINKED_OBJECT'].'.value',$from,$to);
                $total = count($history_data);
                for($i=0;$i<$total;$i++) {
                    $history[]=array('date'=>strtotime($history_data[$i]['ADDED'])*1000,'value'=>(float)$history_data[$i]['VALUE']);
                }
                $result['values'] = $history;
            }
            if ($processed) {
                $result['success'] = true;
                $result['errormsg'] = 'ok';
            }

        } else {
            $devices = array();
            $simple_devices=SQLSelect("SELECT * FROM devices");
            $total = count($simple_devices);
            //print_R($simple_devices);exit;
            for ($i = 0; $i < $total; $i++) {
                $ot = $simple_devices[$i]['LINKED_OBJECT'];
                $rec=array();
                $rec['id']='dev'.$simple_devices[$i]['ID'];
                $rec['room']='room'.$simple_devices[$i]['LOCATION_ID'];
                $rec['name']=$simple_devices[$i]['TITLE'];
                $dev_params=array();
                if ($simple_devices[$i]['TYPE']=='relay') {
                    $rec['type']='DevSwitch';
                    $param=array();
                    $param['key']='Status';
                    $param['value']=(string)getGlobal($ot.'.status');
                    $dev_params[]=$param;
                    $params['key']='pulseable';
                    $param['value']=false;
                    $dev_params[]=$param;
                }

                //rgb dimmer thermostat

                if ($simple_devices[$i]['TYPE']=='button') {
                    $rec['type']='DevScene';
                }

                $type_map=array(
                    'sensor_temp'=>array('DevType'=>'DevTemperature'),
                    'sensor_humidity'=>array('DevType'=>'DevHygrometry'),
                    'sensor_state'=>array('DevType'=>'DevGenericSensor'),
                    'sensor_percentage'=>array('DevType'=>'DevGenericSensor'),
                    'sensor_pressure'=>array('DevType'=>'DevGenericSensor'),
                    'sensor_power'=>array('DevType'=>'DevGenericSensor'),
                    'sensor_voltage'=>array('DevType'=>'DevGenericSensor'),
                    'sensor_current'=>array('DevType'=>'DevGenericSensor'),
                    'sensor_light'=>array('DevType'=>'DevLuminosity'),
                    'counter'=>array('DevType'=>'DevGenericSensor'),
                );
                if (isset($type_map[$simple_devices[$i]['TYPE']])) {
                    $rec['type']=$type_map[$simple_devices[$i]['TYPE']]['DevType'];
                    $param=array();
                    $param['key']='Value';
                    $param['value']=(string)getGlobal($ot.'.value');
                    $param['graphable'] = true;
                    $unit = gg($ot.'.unit');
                    if ($unit !='') {
                        $param['unit']=$unit;
                    }
                    $dev_params[]=$param;
                }

                //smoke, leak

                if ($simple_devices[$i]['TYPE']=='openclose') {
                    $rec['type']='DevLock';
                    $param=array();
                    $param['key']='Status';
                    $param['value']=(string)getGlobal($ot.'.status');
                    $dev_params[]=$param;
                }
                if ($simple_devices[$i]['TYPE']=='motion') {
                    $rec['type']='DevMotion';
                    $param=array();
                    $param['key']='armable';
                    $param['value']=0;
                    $dev_params[]=$param;
                    $param=array();
                    $param['key']='ackable';
                    $param['value']=0;
                    $dev_params[]=$param;
                    $param=array();
                    $param['key']='Armed';
                    $param['value']=1;
                    $dev_params[]=$param;
                    $param=array();
                    $param['key']='Tripped';
                    $param['value']=(int)getGlobal($ot.'.status');
                    $dev_params[]=$param;
                    $param=array();
                    $param['key']='lasttrip';
                    $param['value']=(int)(getGlobal($ot.'.updated'))*1000;
                    $dev_params[]=$param;
                }

                if ($simple_devices[$i]['TYPE']=='thermostat') {
                    $rec['type']='DevThermostat';

                    //curmode
                    $param=array();
                    $param['key']='curmode';
                    if (gg($ot.'.status')) {
                        $param['value']='Normal';
                    } else {
                        $param['value']='ECO';
                    }
                    $dev_params[]=$param;

                    //curtemp
                    $param=array();
                    $param['key']='curtemp';
                    $param['value']=(string)getGlobal($ot.'.value');
                    $dev_params[]=$param;

                    //cursetpoint
                    $param=array();
                    $param['key']='cursetpoint';
                    $param['value']=(string)getGlobal($ot.'.currentTargetValue');
                    $dev_params[]=$param;

                    //step
                    $param=array();
                    $param['key']='step';
                    $param['value']='0.5';
                    $dev_params[]=$param;

                    //minVal
                    $param=array();
                    $param['key']='minVal';
                    $param['value']='0';
                    $dev_params[]=$param;

                    //maxVal
                    $param=array();
                    $param['key']='maxVal';
                    $param['value']='100';
                    $dev_params[]=$param;

                    //availablemodes
                    $param=array();
                    $param['key']='availablemodes';
                    $param['value']='Normal,ECO';
                    $dev_params[]=$param;
                }

                if ($rec['type']!='') {
                    $rec['params']=$dev_params;
                    $devices[]=$rec;
                }
            }
            $result['devices']=$devices;
        }
    } elseif ($params[0]=='rooms') {
        $result['rooms']=array();
        $rooms=SQLSelect("SELECT ID, TITLE FROM locations");
        $total = count($rooms);
        for($i=0;$i<$total;$i++) {
            $result['rooms'][]=array('id'=>'room'.$rooms[$i]['ID'],'name'=>$rooms[$i]['TITLE']);
        }
    } elseif ($params[0]=='system') {
        $result['id']='MajorDoMo';
        $result['apiversion']=0;
    }
    echo json_encode($result,JSON_PRETTY_PRINT);
}

 function processSubscription($event, $details='') {
 $this->getConfig();
  if ($event=='SAY') {
   $level=$details['level'];
   $message=$details['message'];
   //...
  }
 }
/**
* Install
*
* Module installation routine
*
* @access private
*/
 function install($data='') {
  subscribeToEvent($this->name, 'SAY');
  parent::install();
 }
// --------------------------------------------------------------------
}
/*
*
* TW9kdWxlIGNyZWF0ZWQgRGVjIDAzLCAyMDE3IHVzaW5nIFNlcmdlIEouIHdpemFyZCAoQWN0aXZlVW5pdCBJbmMgd3d3LmFjdGl2ZXVuaXQuY29tKQ==
*
*/
