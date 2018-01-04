<?

########## HomeMatic Smoke Detection module for IP-Symcon 4.x ##########

/**
 * @file 		module.php
 *
 * @author 		Ulrich Bittner
 * @copyright  (c) 2017
 * @version 	1.00
 * @date: 		2018-01-02, 11:15
 * @lastchange	2018-01-02, 11:15
 *
 * @see        https://git.ulrich-bittner.info/ubittner/SymconHomeMaticSmokeDetection.git
 *
 * @guids 		{0E61F760-109D-4908-9B42-1E3ABE5638EF} library
 *          	{F0A27D8C-DEB7-439D-872C-D5F468FD0ACF} module
 *
 * @changelog	2018-01-02, 11:15, initial module script version 1.00
 *
 */


class HomeMaticSmokeDetection extends IPSModule
{
	public function Create()
	{
		parent::Create();

		// register properties
		$this->RegisterPropertyString("Description", "");
		$this->RegisterPropertyInteger("CategoryID", 0);
		$this->RegisterPropertyString("SmokeDetectors", "");
		$this->RegisterPropertyString("Notification", "");
		$this->RegisterPropertyBoolean("UseNotification", false);
		$this->RegisterPropertyString("WebFrontID", 0);
		$this->RegisterPropertyBoolean("UseAlarmDialer", false);
		$this->RegisterPropertyString("AlarmDialerID", 0);

		// register profiles
		$name = "HM.SmokeDetector";
      if(!IPS_VariableProfileExists($name)) {
         IPS_CreateVariableProfile($name, 0);
      }
		IPS_SetVariableProfileAssociation($name, "0", "OK", "Information", 0x00FF00);
      IPS_SetVariableProfileAssociation($name, "1", "Rauch erkannt", "Flame", 0xFF0000);

		$name = "HM.Battery";
      if(!IPS_VariableProfileExists($name)) {
         IPS_CreateVariableProfile($name, 0);
      }
		IPS_SetVariableProfileAssociation($name, "0", "OK", "Battery", 0x00FF00);
      IPS_SetVariableProfileAssociation($name, "1", "Batterie schwach", "Battery", 0xFF0000);

		$name = "HMIP.SmokeDetector";
      if(!IPS_VariableProfileExists($name)) {
         IPS_CreateVariableProfile($name, 1);
      }
		IPS_SetVariableProfileAssociation($name, "0", "OK", "Information", 0x00FF00);
      IPS_SetVariableProfileAssociation($name, "1", "Rauch erkannt", "Flame", 0xFF0000);

		$name = "HMIP.Battery";
      if(!IPS_VariableProfileExists($name)) {
         IPS_CreateVariableProfile($name, 0);
      }
		IPS_SetVariableProfileAssociation($name, "0", "OK", "Battery", 0x00FF00);
      IPS_SetVariableProfileAssociation($name, "1", "Batterie schwach", "Battery", 0xFF0000);





	}

	public function ApplyChanges()
	{
		parent::ApplyChanges();


		$this->CheckDescription();
		$this->CheckCategory();
		$this->CheckConfiguration();
	}


	#####################################################################################################################################
	## start of modul functions 																												  						  ##
	#####################################################################################################################################

	########## public functions ##########

	public function InstallDevices()
	{
		$smokeDetectors = json_decode($this->ReadPropertyString("SmokeDetectors"), true);
		//print_r($smokeDetectors);
		foreach ($smokeDetectors as $position => $smokeDetector) {
			//echo $position."\n";
			$type = $smokeDetector["Type"];
			switch ($type) {
				case 'HM-Sec-SD':
		      	$this->InstallHMSecSD($position, $smokeDetector["Description"], $smokeDetector["Serial"]);
		      	break;
				case 'HM-Sec-SD-2':
			     	$this->InstallHMSecSD2($position, $smokeDetector["Description"], $smokeDetector["Serial"]);
			     	break;
				case 'HmIP-SWSD':
					$this->InstallHmIPSWSD($position, $smokeDetector["Description"], $smokeDetector["Serial"]);
					break;
			}
		}
	}


	public function ExecuteSmokeDetectorAlerting(string $smokeDetectorObjectID)
	{
		$notificationParameters = json_decode($this->ReadPropertyString("Notification"), true);
		print_r($notificationParameters);
		foreach ($notificationParameters as $notificationParameter) {
			$type = $notificationParameter["Type"];
			$objectID = $notificationParameter["ObjectID"];
			$duration = $notificationParameter["Duration"];
			$status = $notificationParameter["Status"];
			if ($status == "activated") {
				switch ($type) {
					case 'WebFront Push':
						$this->PushNotification($objectID, $smokeDetectorObjectID);
						break;
					case 'HM-LC-Sw4-WM':
						$this->ToggleHMLCSw4WM($objectID, $duration);
						break;
					case 'HmIP-PCBS':
						$this->ToggleHmIPPCBS($objectID, $duration);
						break;
				}
			}
		}
	}


	########## protected functions ##########

	protected function GetHomeMaticGUID()
	{
		$homeMaticGUID = "{EE4A81C6-5C90-4DB7-AD2F-F6BBD521412E}";
		return $homeMaticGUID;
	}


	protected function CheckHomeMaticDevice(string $Serial)
	{
		$objectID = 0;
		$homeMaticGUID = $this->GetHomeMaticGUID();
		$instanceIDs = IPS_GetInstanceListByModuleID($homeMaticGUID);
		foreach ($instanceIDs as $instanceID) {
			$deviceSerial = IPS_GetProperty($instanceID, "Address");
			if ($Serial == $deviceSerial) {
				$objectID = $instanceID;
			}
		}
		return $objectID;
	}


	protected function InstallHMSecSD(int $Position, string $Description, string $Serial)
	{
		// channel :0 = maintanance
		// channel :1 = device
		$instanceID = $this->InstanceID;
		$Position++;
		for ($i=0; $i <= 1 ; $i++) {
			$address = $Serial.":".$i;
			$objectID = $this->CheckHomeMaticDevice($address);
			if ($objectID == 0) {
				// create device
				$homeMaticGUID = $this->GetHomeMaticGUID();
				$objectID = IPS_CreateInstance($homeMaticGUID);
				IPS_SetProperty($objectID, "Address", $address);
				IPS_ApplyChanges($objectID);
			}
			if ($i === 0) {
				$description = $Description." (Wartung)";
			}
			else {
				$description = $Description;
			}
			IPS_SetName($objectID, $description);
			$categoryID = $this->ReadPropertyInteger("CategoryID");
		   IPS_SetParent($objectID, $categoryID);
			if ($i == 0) {
					$position = 100+$Position;
			}
			else {
				$position = $Position;
			}
			IPS_SetPosition($objectID, $position);
			// children ids
			$childrenIDs = IPS_GetChildrenIDs($objectID);
			foreach ($childrenIDs as $childrenID) {
				IPS_SetHidden($childrenID, true);
				$variableName = IPS_GetName($childrenID);
				switch ($variableName) {
					case "LOWBAT":
					case "Batterie":
						IPS_SetName($childrenID, "Batterie");
						IPS_SetVariableCustomProfile($childrenID, "HM.Battery");
						if ($i == 0) {
							IPS_SetHidden($childrenID, false);
						}
						break;
					case "STATE";
					case "Status":
						IPS_SetName($childrenID, "Status");
						IPS_SetVariableCustomProfile($childrenID, "HM.SmokeDetector");
						IPS_SetVariableCustomAction($childrenID, 1);
						IPS_SetHidden($childrenID, false);
						$eventName = "ExecuteSmokeDetectorAlerting";
						$eventID = @IPS_GetEventIdByName($eventName, $childrenID);
						if ($eventID === false) {
							$eventID = IPS_CreateEvent(0);
						}
						IPS_SetName($eventID, $eventName);
						IPS_SetEventTrigger($eventID, 4, $childrenID);
						IPS_SetEventTriggerValue($eventID, true);
						IPS_SetParent($eventID, $childrenID);
						IPS_SetEventScript($eventID, "HMSD_ExecuteSmokeDetectorAlerting({$instanceID}, {$objectID});");
						IPS_SetEventActive($eventID, true);
						break;
				}
			}
		}
	}


	protected function InstallHMSecSD2(int $Position, string $Description, string $Serial)
	{
		$this->InstallHMSecSD2($Position, $Description, $Serial);
	}


	protected function InstallHmIPSWSD(int $Position, string $Description, string $Serial)
	{
		// channel :0 = maintanance
		// channel :1 = device
		$instanceID = $this->InstanceID;
		$Position++;
		for ($i=0; $i <= 1 ; $i++) {
			$address = $Serial.":".$i;
			$objectID = $this->CheckHomeMaticDevice($address);
			if ($objectID == 0) {
				// create device
				$homeMaticGUID = $this->GetHomeMaticGUID();
				$objectID = IPS_CreateInstance($homeMaticGUID);
				IPS_SetProperty($objectID, "Address", $address);
				IPS_SetConfiguration($objectID, '{"Protocol":2,"Address":"'.$address.'","EmulateStatus":true}');
				IPS_ApplyChanges($objectID);
			}
			if ($i === 0) {
				$description = $Description." (Wartung)";
			}
			else {
				$description = $Description;
			}
			IPS_SetName($objectID, $description);
			$categoryID = $this->ReadPropertyInteger("CategoryID");
		   IPS_SetParent($objectID, $categoryID);
			if ($i == 0) {
					$position = 100+$Position;
			}
			else {
				$position = $Position;
			}
			IPS_SetPosition($objectID, $position);
			// children ids
			$childrenIDs = IPS_GetChildrenIDs($objectID);
			foreach ($childrenIDs as $childrenID) {
				IPS_SetHidden($childrenID, true);
				$variableName = IPS_GetName($childrenID);
				switch ($variableName) {
					case "LOW_BAT":
					case "Batterie":
						IPS_SetName($childrenID, "Batterie");
						IPS_SetVariableCustomProfile($childrenID, "HMIP.Battery");
						IPS_SetHidden($childrenID, false);
						break;
					case "SMOKE_DETECTOR_ALARM_STATUS":
					case "Status":
						IPS_SetName($childrenID, "Status");
						IPS_SetVariableCustomProfile($childrenID, "HMIP.SmokeDetector");
						IPS_SetHidden($childrenID, false);
						$eventName = "ExecuteSmokeDetectorAlerting";
						$eventID = @IPS_GetEventIdByName($eventName, $childrenID);
						if ($eventID === false) {
							$eventID = IPS_CreateEvent(0);
						}
						IPS_SetName($eventID, $eventName);
						IPS_SetEventTrigger($eventID, 4, $childrenID);
						IPS_SetEventTriggerValue($eventID, 1);
						IPS_SetParent($eventID, $childrenID);
						IPS_SetEventScript($eventID, "HMSD_ExecuteSmokeDetectorAlerting({$instanceID}, {$objectID});");
						IPS_SetEventActive($eventID, true);
						break;
				}
			}
		}
	}


	protected function PushNotification(string $WebFrontID, string $ObjectID)
	{
		$name = IPS_GetName($ObjectID);
		WFC_PushNotification($WebFrontID, "Warnung", "{$name} hat Rauch erkannt. Bitte umgehend prüfen!", '', 0);
	}


	protected function ToggleHMLCSw4WM(string $ObjectID, string $Duration)
	{
		$Duration = $Duration*1000;
		HM_WriteValueBoolean($ObjectID, "STATE", true);
		if ($Duration > 0)
		{
			IPS_Sleep($Duration);
			HM_WriteValueBoolean($ObjectID, "STATE", false);
		}
	}


	protected function ToggleHmIPPCBS(string $ObjectID, string $Duration)
	{
		$this->ToggleHMLCSw4WM($ObjectID, $Duration);
	}


	protected function CheckDescription()
	{
		$description = $this->ReadPropertyString("Description");
		if ($description != "") {
			IPS_SetName($this->InstanceID, $description);
		}
	}


	protected function CheckCategory()
	{
		$category = $this->ReadPropertyInteger("CategoryID");
		if ($category) {
			IPS_SetParent($this->InstanceID, $category);
		}
	}


	protected function CheckConfiguration()
	{
		$this->SetStatus(102);
		/*
		$webFrontID = $this->ReadPropertyString ("WebFrontID");
		$useNotification = $this->ReadPropertyBoolean ("UseNotification");
		if ($useNotification == false) {
			$this->SetStatus(102);
		}
		if ($useNotification == true && $webFrontID == 0) {
			$this->SetStatus(201);
		}
		else {
			if ($webFrontID != 0) {
				$instanceInfo = IPS_GetInstance ($webFrontID);
				$moduleName = $instanceInfo["ModuleInfo"]["ModuleName"];
				$moduleType = $instanceInfo["ModuleInfo"]["ModuleType"];
				if ($moduleName = "WebFront Configurator" && $moduleType == 4) {
					$this->SetStatus(102);
				}
				else {
					$this->SetStatus(202);
				}
			}
		}*/
	}

}
?>
