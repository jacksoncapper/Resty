<?php
	function getRelationship($schema, $id){
		if(!property_exists($GLOBALS["resty"], "_users"))
			return;
		if($schema->name == $GLOBALS["resty"]->_users){
			$usersSchema = $GLOBALS["usersSchema"];
			// Private
			if($id == $GLOBALS["authUser"])
				return "private";
			else if(property_exists($usersSchema, "owner")){
				// Super
				$currentID = $id;
				while(true){
					if($currentID == $GLOBALS["authUser"])
						return "super";
					else if($currentID === null)
						break;
					$currentID = $GLOBALS["db"]->query("SELECT `" . $usersSchema->owner . "` FROM `" . $usersSchema->name . "` WHERE id = " . $GLOBALS["db"]->quote($currentID))->fetch(PDO::FETCH_COLUMN, 0);
				}
				// Sub
				$currentID = $GLOBALS["authUser"];
				while(true){
					if($currentID == $id)
						return "sub";
					else if($currentID === null)
						break;
					$currentID = $GLOBALS["db"]->query("SELECT `" . $usersSchema->owner . "` FROM `" . $usersSchema->name . "` WHERE id = " . $GLOBALS["db"]->quote($currentID))->fetch(PDO::FETCH_COLUMN, 0);
				}
				// Semi
				$meSuperuserID = $GLOBALS["db"]->query("SELECT `" . $usersSchema->owner . "` FROM `" . $usersSchema->name . "` WHERE id = " . $GLOBALS["db"]->quote($GLOBALS["authUser"]))->fetch(PDO::FETCH_COLUMN, 0);
				$itemSuperuserID = $GLOBALS["db"]->query("SELECT `" . $usersSchema->owner . "` FROM `" . $usersSchema->name . "` WHERE id = " . $GLOBALS["db"]->quote($itemSql[property_exists($schema, "owner")]))->fetch(PDO::FETCH_COLUMN, 0);
				if($meSuperuserID === $itemSuperuserID)
					return "semi";
			}
		}
		else if($schema->owner != null){
			$owner = $GLOBALS["db"]->query("SELECT `" . $schema->owner . "` FROM `" . $schema->name . "` WHERE `" . $schema->id . "` = " . $GLOBALS["db"]->quote($id))->fetch(PDO::FETCH_COLUMN, 0);
			foreach($schema->fields as $name => $field)
				if($name == $schema->owner)
					return getRelationship(getSchema($field->referenceSubject), $owner);
		}
		return null;
	}
	function interceptTags($source, $tags, $isolate = false){
		@$sourceHtml = DOMDocument::loadHTML($isolate ? "<p>" . $source . "</p>" : $source);
		foreach($tags as $tagName => $tagValue)
			while($sourceHtml->getElementsByTagName($tagName)->length){
				$sourceTagXml = $sourceHtml->getElementsByTagName($tagName)->item(0);
				foreach(DOMDocument::loadHTML("<html><body>" . $tagValue . "</body></html>")->getElementsByTagName("body")->item(0)->childNodes as $childXml)
					$sourceTagXml->parentNode->insertBefore($sourceHtml->importNode($childXml, true), $sourceTagXml);
				$sourceTagXml->parentNode->removeChild($sourceTagXml);
			}
		return $isolate ? $sourceHtml->getElementsByTagName("p")->item(0)->nodeValue : $sourceHtml->saveHTML();
	}
	function generateID($length){
		return substr(md5($_SERVER["SERVER_ADDR"] . uniqid()), 0, $length);
	}
	function errorBadRequest($message = null){
		header("HTTP/1.1 400 Bad Request");
		if($message !== null){
			header("Content-Type: text/json");
			echo is_string($message) ? $message : json_encode($message);
		}
		exit;
	}
	function errorNotFound($message = null){
		header("HTTP/1.1 404 Not Found");
		if($message !== null){
			header("Content-Type: text/plain");
			echo is_string($message) ? $message : json_encode($message);
		}
		exit;
	}
	function errorForbidden($message = null){
		header("HTTP/1.1 403 Forbidden");
		if($message !== null){
			header("Content-Type: text/plain");
			echo is_string($message) ? $message : json_encode($message);
		}
		exit;
	}	
	function getSchema($subject, $lite = false){
		$schema = new stdClass();
		$primaryField = $GLOBALS["db"]->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE"
			. " WHERE CONSTRAINT_NAME = 'PRIMARY'"
			. " AND TABLE_SCHEMA = " . $GLOBALS["db"]->quote($GLOBALS["resty"]->_database->name)
			. " AND TABLE_NAME = " . $GLOBALS["db"]->quote($subject))->fetch(PDO::FETCH_COLUMN, 0);
		if($primaryField !== false)
			$schema->id = $primaryField;
		$schema->name = $subject;
		$schema->meta = json_decode($GLOBALS["db"]->query("SELECT TABLE_COMMENT FROM INFORMATION_SCHEMA.TABLES"
			. " WHERE TABLE_SCHEMA = " . $GLOBALS["db"]->quote($GLOBALS["resty"]->_database->name)
			. " AND TABLE_NAME = " . $GLOBALS["db"]->quote($subject))->fetch(PDO::FETCH_COLUMN, 0));
		$schema->meta = $schema->meta !== null ? $schema->meta : new stdClass();
		$schema->methods = array("POST", "BROWSE", "GET", "PUT", "DELETE");
		$schema->href = $GLOBALS["url"] . "/" . $subject;

		if($lite === true)
			return $schema;

		// Fields
		$schema->fields = new stdClass();
		foreach($GLOBALS["db"]->query("SHOW FULL COLUMNS FROM `" . $subject . "`") as $fieldSql){
			$field = new stdClass();
			$field->type = $fieldSql["Type"];
			$field->meta = json_decode($fieldSql["Comment"]);
			$field->meta = $field->meta !== null ? $field->meta : new stdClass();
			
			// Value
			$field->class = "value";
			$field->typex = "string";
			// Boolean
			if(strpos($fieldSql["Type"], "tinyint") !== false)
				$field->typex = "boolean";
			// Number
			else if(strpos($fieldSql["Type"], "decimal") !== false)
				$field->typex = "number";
			else if(strpos($fieldSql["Type"], "double") !== false)
				$field->typex = "number";
			else if(strpos($fieldSql["Type"], "float") !== false)
				$field->typex = "number";
			else if(strpos($fieldSql["Type"], "int") !== false)
				$field->typex = "integer";
			// Datetime
			else if(strpos($fieldSql["Type"], "datetime") !== false || strpos($fieldSql["Type"], "timestamp") !== false)
				$field->typex = "datetime";
			// Enum 
			else if(strpos($fieldSql["Type"], "enum") !== false){
				$field->typex = "select";
				$field->enum = array();
				preg_match('/^enum\((.*)\)$/', $fieldSql["Type"], $matches);
				foreach(explode(",", $matches[1]) as $value)
					$field->enum[] = trim($value, "'");
			}
			
			// File
			if(property_exists($field->meta, "file") && $field->meta->file == "true")
				$field->class = "file";
			
			// Out-reference
			else{
				$referenceSql = $GLOBALS["db"]->query("SELECT * FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE"
					. " WHERE TABLE_SCHEMA = " . $GLOBALS["db"]->quote($GLOBALS["resty"]->_database->name)
					. " AND TABLE_NAME LIKE " . $GLOBALS["db"]->quote($subject)
					. " AND COLUMN_NAME LIKE " . $GLOBALS["db"]->quote($fieldSql["Field"])
					. " AND REFERENCED_TABLE_NAME IS NOT NULL")->fetch();
				if($referenceSql !== false){
					$field->class = "out-reference";
					$field->referenceSubject = $referenceSql["REFERENCED_TABLE_NAME"];
				}
			}
			
			// Nullable
			$field->nullable = $fieldSql["Null"] == "YES";

			$schema->fields->{$fieldSql["Field"]} = $field;
		}
		
		// In-references
		foreach($GLOBALS["db"]->query("SELECT * FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE"
			. " WHERE TABLE_SCHEMA = " . $GLOBALS["db"]->quote($GLOBALS["resty"]->_database->name)
			. " AND REFERENCED_TABLE_NAME = " . $GLOBALS["db"]->quote($subject)) as $referenceSql){
			$inreference = new stdClass();
			$inreference->class = "in-reference";
			$inreference->referenceField = $referenceSql["COLUMN_NAME"];
			$meta = json_decode($GLOBALS["db"]->query("SELECT TABLE_COMMENT FROM INFORMATION_SCHEMA.TABLES"
				. " WHERE TABLE_SCHEMA = " . $GLOBALS["db"]->quote($GLOBALS["resty"]->_database->name)
				. " AND TABLE_NAME = " . $GLOBALS["db"]->quote($referenceSql["TABLE_NAME"]))->fetch(PDO::FETCH_COLUMN, 0));
			$meta = $meta === null ? new stdClass() : $meta;
			$inreference->meta = $meta;
			$schema->fields->{$referenceSql["TABLE_NAME"]} = $inreference;
		}

		// Meta
		// Normalise
		if($subject == $GLOBALS["resty"]->_users){
			$schema->meta->username = property_exists($schema->meta, "username") ? $schema->meta->username : (property_exists($schema->fields, "username") ? "username" : "email");
			$schema->meta->passcode = property_exists($schema->meta, "passcode") ? $schema->meta->passcode : "passcode";
			$schema->meta->email = property_exists($schema->meta, "email") ? $schema->meta->email : "email";
		}
		$schema->meta->access = property_exists($schema->meta, "access") ? $schema->meta->access : array("private", "super");
		$schema->meta->affect = property_exists($schema->meta, "affect") ? $schema->meta->affect : array("private", "super");
		$schema->meta->owner = null;
		foreach($schema->fields as $fieldName=> $field)
			if($field->class == "out-reference" && $field->referenceSubject == $GLOBALS["resty"]->_users){
				$schema->meta->owner = $fieldName;
				break;
			}
		foreach($schema->fields as $fieldName=> &$field){
			$field->meta->encrypt = property_exists($field->meta, "encrypt") ? $field->meta->encrypt : $fieldName == $schema->meta->passcode;
			$field->meta->file = property_exists($field->meta, "file") ? $field->meta->file : false;
			$field->meta->get = property_exists($field->meta, "get") ? $field->meta->get : array("private", "super");
			$field->meta->set = property_exists($field->meta, "set") ? $field->meta->set : array("private", "super");
		}
		// Register
		foreach($schema->meta as $name=> $value)
			$schema->{$name} = $value;
		foreach($schema->fields as &$field)
			foreach($field->meta as $name=> $value)
				$field->{$name} = $value;
		
		return $schema;
	}
	function browseSubject($subject, $options = null){
		$schema = is_object($subject) ? $subject : getSchema($subject);
		$subject = is_object($subject) ? $schema->name : $subject;
		$options = $options !== null ? $options : new stdClass();

		// Defaults
		foreach($options as $optionName => $optionValue)
			if($optionValue == "_me"){
				$optionNameBits = explode(":", $optionName);
				if(count($optionNameBits) > 1 && in_array($optionNameBits[1], array_keys((array)$schema->fields))){
					$field = $schema->fields->{$optionNameBits[1]};
					if($field->class == "out-reference" && $field->referenceSubject == "users")
						$options->{$optionName} = $GLOBALS["authUser"];
				}
			}
		if(property_exists($options, "lmt")){
			if(!property_exists($options, "off"))
				$options->off = "0";
			if(!property_exists($options, "pge"))
				$options->pge = "0";
		}

		$items = new stdClass();
		
		// Browse
		if(property_exists($GLOBALS["resty"], $subject) && property_exists($GLOBALS["resty"]->{$subject}, "browse"))
			return call_user_func($GLOBALS["resty"]->{$subject}->browse, $options, $items);
		
		// Prebrowse
		$customWhereSql = "";
		if(property_exists($GLOBALS["resty"], $subject) && property_exists($GLOBALS["resty"]->{$subject}, "preBrowse")){
			$customWhereSql = call_user_func($GLOBALS["resty"]->{$subject}->preBrowse, $options, $items);
			if($customWhereSql === false)
				return;
		}

		$whereSql = $customWhereSql;		
		foreach($schema->fields as $name => $field)
			if($field->class == "value" || $field->class == "out-reference")
				if(property_exists($options, "rgx:" . $name))
					$whereSql .= " AND `" . $name . "` " . ($options->{"rgx:" . $name} == "" ? "IS NULL" : " REGEXP " . $GLOBALS["db"]->quote($options->{"rgx:" . $name}));
				else if(property_exists($options, "is:" . $name))
					$whereSql .= " AND `" . $name . "` " . ($options->{"is:" . $name} == "" ? "IS NULL" : " = " . $GLOBALS["db"]->quote($options->{"is:" . $name}));
				else if(property_exists($options, "lt:" . $name))
					$whereSql .= " AND `" . $name . "` " . ($options->{"lt:" . $name} == "" ? "IS NULL" : " < " . $GLOBALS["db"]->quote($options->{"lt:" . $name}));
				else if(property_exists($options, "lte:" . $name))
					$whereSql .= " AND `" . $name . "` " . ($options->{"lte:" . $name} == "" ? "IS NULL" : " <= " . $GLOBALS["db"]->quote($options->{"lte:" . $name}));
				else if(property_exists($options, "gt:" . $name))
					$whereSql .= " AND `" . $name . "` " . ($options->{"gt:" . $name} == "" ? "IS NULL" : " > " . $GLOBALS["db"]->quote($options->{"gt:" . $name}));
				else if(property_exists($options, "gte:" . $name))
					$whereSql .= " AND `" . $name . "` " . ($options->{"gte:" . $name} == "" ? "IS NULL" : " >= " . $GLOBALS["db"]->quote($options->{"gte:" . $name}));
				else if(property_exists($options, "isn:" . $name))
					$whereSql .= " AND `" . $name . "` " . ($options->{"isn:" . $name} == "" ? "IS NULL" : " <> " . $GLOBALS["db"]->quote($options->{"isn:" . $name}));
		if(property_exists($options, "rgx") && $options->rgx != ""){
			$whereSql .= " AND (FALSE";
			foreach($schema->fields as $name => $field)
				if($field->class == "value" || $field->class == "out-reference")
					$whereSql .= " OR `" . $name . "` REGEXP " . $GLOBALS["db"]->quote($options->rgx);
			$whereSql .= ")";
		}

		$limitSql = "";
		if(property_exists($options, "lmt"))
			$limitSql .= " LIMIT " . ($options->off + ($options->pge * $options->lmt)) . ", " . $options->lmt;

		$orderSql = "";
		foreach($schema->fields as $name => $value)
			if(property_exists($options, "ord:" . $name))
				$orderSql = " ORDER BY `" . $name . "` " . ($options->{"ord:" . $name} == "descending" ? "DESC" : "ASC");

		$items->items = array();
		foreach($GLOBALS["db"]->query("SELECT `" . $schema->id . "` FROM `" . $subject . "` WHERE TRUE" . $whereSql . $orderSql . $limitSql) as $itemSql){
			$suboptions = (object)(array)$options;
			$item = getItem($schema, $itemSql[$schema->id], $suboptions);
			if($item !== null)
				$items->items[] = $item;
			else
				$whereSql .= " AND `" . $schema->id . "` <> " . $GLOBALS["db"]->quote($itemSql[$schema->id]);
		}

		$count = $GLOBALS["db"]->query("SELECT COUNT(*) FROM `" . $subject . "` WHERE TRUE" . $whereSql)->fetch(PDO::FETCH_COLUMN, 0);
		$items->count = $count;
		if(property_exists($options, "lmt"))
			$items->pageCount = ceil($count / $options->lmt);
		
		// Postbrowse
		if(property_exists($GLOBALS["resty"], $subject) && property_exists($GLOBALS["resty"]->{$subject}, "postBrowse"))
			call_user_func($GLOBALS["resty"]->{$subject}->postBrowse, $options, $items);

		return $items;
	}
	function applyItem($subject, $id, $item, $attachments = null){
		$schema = is_object($subject) ? $subject : getSchema($subject);
		$subject = is_object($subject) ? $schema->name : $subject;
		$item = $item !== null ? $item : new stdClass();
		$attachments = $attachments !== null ? $attachments : new stdClass();

		if($id !== null)
			if($schema->owner != null){
				// Security: Get Relationship
				$relationship = getRelationship($schema, $id);
			
				// Security: Check Resource Access Policy
				if(property_exists($GLOBALS["resty"], $subject) && property_exists($GLOBALS["resty"]->{$subject}, "access-policy"))
					$accessPolicy = call_user_func($GLOBALS["resty"]->{$subject}->{"access-policy"}, $id, $options, $relationship);
				else if(property_exists($schema, "access"))
					$accessPolicy = $schema->access;
				if(isset($accessPolicy) && !in_array($relationship, $accessPolicy))
					return null;
			
				// Security: Check Resource Affect Policy
				if(property_exists($GLOBALS["resty"], $subject) && property_exists($GLOBALS["resty"]->{$subject}, "affect-policy"))
					$affectPolicy = call_user_func($GLOBALS["resty"]->{$subject}->{"affect-policy"}, $id, new stdClass(), $relationship);
				else if(property_exists($schema, "affect"))
					$affectPolicy = $schema->affect;
				if(isset($affectPolicy) && !in_array($relationship, $affectPolicy))
					return null;
			}

		// Apply
		if(property_exists($GLOBALS["resty"], $subject) && property_exists($GLOBALS["resty"]->{$subject}, "apply"))
			return call_user_func($GLOBALS["resty"]->{$subject}->apply, $id, $item, $attachments);

		// Pre-apply
		if(property_exists($GLOBALS["resty"], $subject) && property_exists($GLOBALS["resty"]->{$subject}, "preApply"))
			if(call_user_func($GLOBALS["resty"]->{$subject}->preApply, $id, $item, $attachments) === false)
				return;

		// Check for database-generated ID
		if(property_exists($schema, "id")){
			$dbGeneratedID = false;
			foreach($item as $fieldName => $value)
				if($fieldName == $schema->id)
					$dbGeneratedID = true;
			if(!$dbGeneratedID)
				if($id === null){
					$length = $GLOBALS["db"]->query("SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.columns WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $subject . "' AND COLUMN_NAME = '" . $schema->id . "'")->fetch(PDO::FETCH_COLUMN, 0);
					if($GLOBALS["db"]->query("SHOW COLUMNS FROM `" . $schema->name . "` WHERE EXTRA LIKE '%auto_increment%'")->fetch() === false)
						$idx = generateID($length);
				}
				else
					$idx = $id;
		}

		// Defaults
		foreach($schema->fields as $name => $field)
			if($field->class == "out-reference")
				if(property_exists($GLOBALS["resty"], "users") && $field->referenceSubject == $GLOBALS["resty"]->_users && property_exists($item, $name) && $item->{$name} == "_me")
					$item->{$name} = array_key_exists("authUser", $GLOBALS) ? $GLOBALS["authUser"] : null;

		// Set
		$setSql = "";
		foreach($schema->fields as $name => $field)
			if(property_exists($item, $name) || property_exists($attachments, $name)){
				
				// Security: Check Field Set Policy
				if(property_exists($GLOBALS["resty"], $subject) && property_exists($GLOBALS["resty"]->{$subject}, "setSecurity"))
					$setPolicy = call_user_func($GLOBALS["resty"]->{$subject}->setSecurity, $id, $item, $attachments, $name, $relationship);
				else if(property_exists($field, "set"))
					$setPolicy = $field->set;
				if(isset($setPolicy) && !in_array($relationship, $setPolicy))
					return null;
				
				if($field->class == "value")
					$setSql .= ($setSql != "" ? ", " : "") . "`" . $name . "` = " . ($item->{$name} === null ? "NULL" : $GLOBALS["db"]->quote($item->{$name}));
				else if($field->class == "out-reference"){
					if(is_object($item->{$name})){
						$outID = applyItem($field->referenceSubject, null, $item->{$name});
						if($outID === null)
							continue;
						$setSql .= ($setSql != "" ? ", " : "") . "`" . $name . "` = " . $GLOBALS["db"]->quote($outID);
					}
					else{
						// Security: Check Field Set-Reference Policy
						if($item->{$name} !== null)
							if(applyItem($field->referenceSubject, $item->{$name}, new stdClass()) === null)
								continue;
						$setSql .= ($setSql != "" ? ", " : "") . "`" . $name . "` = " . ($item->{$name} === null ? "NULL" : $GLOBALS["db"]->quote($item->{$name}));
					}
				}
				else if($field->class == "file")
					$setSql .= ($setSql != "" ? ", " : "") . "`" . $name . "` = " . (property_exists($attachments, $name) ? $GLOBALS["db"]->quote($attachments->{$name}->extension) : "NULL");
			}

		if(property_exists($schema, "id"))
			$action = $id === null ? "INSERT" : "UPDATE";
		else{
			$count = $GLOBALS["db"]->query("SELECT COUNT(*) FROM `" . $schema->name . "`")->fetch(PDO::FETCH_COLUMN, 0);
			$action = $count > 0 ? "UPDATE" : "INSERT";
		}
		$GLOBALS["db"]->exec($action . " `" . $schema->name . "`"
			. ($setSql != "" ? " SET " . $setSql : "")
			. ($id !== null ? " WHERE `" . $schema->id . "` = " . $GLOBALS["db"]->quote($idx) : ""));
		$idx = isset($idx) ? $idx : $GLOBALS["db"]->lastInsertId();
		
		// Encryption
		foreach($schema->fields as $name => $field)
			if($field->class == "value" && property_exists($field, "encrypt") && $field->encrypt == "true")
				if(property_exists($item, $name))
					$GLOBALS["db"]->exec("UPDATE `" . $schema->name . "` SET `" . $name . "` = " . $GLOBALS["db"]->quote(md5($item->{$name} . $idx)) . " WHERE `" . $schema->id . "` = " . $GLOBALS["db"]->quote($idx));
		
		// Files
		foreach($schema->fields as $name => $field)
			if(property_exists($item, $name) || property_exists($attachments, $name))
				if($field->class == "in-reference"){
					$GLOBALS["db"]->exec("DELETE FROM `" . $name . "` WHERE `" . $field->referenceField . "` = " . $GLOBALS["db"]->quote($idx));
					foreach($item->{$name} as $subitem){
						$subitem->{$field->referenceField} = $idx;
						applyItem($name, null, $subitem);
					}
				}
				else if($field->class == "file"){
					if(property_exists($item, $name))
						if($item->{$name} !== null){
							// URL download
							$tmpFile = tempnam(__DIR__, "");
							file_put_contents($tmpFile, fopen($item->{$name}, "r"));
							$file = new stdClass();
							$file->interim = $tmpFile;
							$file->extension = explode("?", pathinfo($item->{$name}, PATHINFO_EXTENSION));
							$file->extension = $file->extension[0];
							$attachments->{$name} = $file;
							unset($item->{$name});
						}
						else{
							// Remove files
							$directory = $GLOBALS["resty"]->_files . "/" . $schema->name;
							unlink($directory . "/" . $id . "/" . $name . "." . $item->{$name});
							if(count(scandir($directory . "/" . $id)) == 2)
								rmdir($directory . "/" . $id);
						}
					
					if(property_exists($attachments, $name)){
						$directory = $GLOBALS["resty"]->_files . "/" . $schema->name;
						if(!file_exists($directory . "/" . $idx))
							mkdir($directory . "/" . $idx, 0777, true);
						rename($attachments->{$name}->interim, $directory . "/" . $idx . "/" . $name . "." . $attachments->{$name}->extension);
					}
				}
		
		// Post-apply
		if(property_exists($GLOBALS["resty"], $subject) && property_exists($GLOBALS["resty"]->{$subject}, "postApply"))
			call_user_func($GLOBALS["resty"]->{$subject}->postApply, $idx, $item, $attachments);
		
		return $idx;
	}
	function getItem($subject, $id, $options = null){
		$schema = is_object($subject) ? $subject : getSchema($subject);
		$subject = is_object($subject) ? $schema->name : $subject;
		$options = $options !== null ? $options : new stdClass();

		$item = new stdClass();
		
		if($schema->owner != null){
			// Security: Get Relationship
			$relationship = getRelationship($schema, $id);
			if(property_exists($options, "own") && $options->own == "true")
				$item->ownership = $relationship !== null ? $relationship : null;

			// Security: Check Resource Access Policy
			if(property_exists($GLOBALS["resty"], $subject) && property_exists($GLOBALS["resty"]->{$subject}, "access-policy"))
				$accessPolicy = call_user_func($GLOBALS["resty"]->{$subject}->{"access-policy"}, $id, $options, $relationship);
			else if(property_exists($schema, "access"))
				$accessPolicy = $schema->access;
			if(isset($accessPolicy) && !in_array($relationship, $accessPolicy))
				return null;
		}
		
		// Get
		if(property_exists($GLOBALS["resty"], $subject) && property_exists($GLOBALS["resty"]->{$subject}, "get"))
			return call_user_func($GLOBALS["resty"]->{$subject}->get, $id, $item, $options);

		// Preget
		if(property_exists($GLOBALS["resty"], $subject) && property_exists($GLOBALS["resty"]->{$subject}, "preGet"))
			if(call_user_func($GLOBALS["resty"]->{$subject}->preGet, $id, $item, $options) === false)
				return;

		$itemSql = $GLOBALS["db"]->query("SELECT * FROM `" . $subject . "`" . ($id !== null ? " WHERE `" . $schema->id . "` = " . $GLOBALS["db"]->quote($id) : ""))->fetch();
		if($itemSql === false)
			return null;
		
		foreach($schema->fields as $name => $field){
			
			// Security: Check Field Get Policy
			if(property_exists($GLOBALS["resty"], $subject) && property_exists($GLOBALS["resty"]->{$subject}, "getSecurity"))
				$getPolicy = call_user_func($GLOBALS["resty"]->{$subject}->preBrowseSecurity, $id, $options, $relationship, $name);
			else if(property_exists($field, "get"))
				$getPolicy = $field->get;
			if(isset($getPolicy) && !in_array($relationship, $getPolicy))
				return null;
			
			if($field->class == "value"){
				if($itemSql[$name] === null)
					$item->{$name} = null;
				else if(!property_exists($field, "encrypt") || $field->encrypt != "true")
					if($field->typex == "number")
						$item->{$name} = floatval($itemSql[$name]);
					else if($field->typex == "datetime")
						$item->{$name} = date("c", strtotime($itemSql[$name]));
					else
						$item->{$name} = utf8_encode($itemSql[$name]);
			}
			else if($field->class == "file"){
				if($itemSql[$name] === null)
					$item->{$name} = null;
				else
					$item->{$name} = "http://" . $_SERVER["HTTP_HOST"] . "/" . $GLOBALS["resty"]->_files . "/" . $schema->name . "/" . $id . "/" . $name . "." . $itemSql[$name];
			}
			else if($field->class == "out-reference"){
				if($itemSql[$name] === null)
					$item->{$name} = null;
				else if(!property_exists($options, "inc:" . $name) || $options->{"inc:" . $name} != "true")					
					$item->{$name} = $itemSql[$name];
				else if(property_exists($options, "inc:" . $name) && $options->{"inc:" . $name} == "true"){
					$suboptions = new stdClass();
					foreach($options as $optionName => $optionValue){
						$subnames = explode(":", $optionName);
						if(count($subnames) > 2 && $subnames[0] == "inc" && $subnames[1] == $name){
							unset($subnames[0]);
							unset($subnames[1]);
							$optionName = implode(":", $subnames);
							$suboptions->{$optionName} = $optionValue;
						}
					}
					$item->{$name} = getItem($field->referenceSubject, $itemSql[$name], $suboptions);
				}
			}
			else if($field->class == "in-reference" && property_exists($options, "inc:" . $name) && $options->{"inc:" . $name} == "true"){
				$suboptions = new stdClass();
				foreach($options as $optionName => $optionValue){
					$subnames = explode(":", $optionName);
					if(count($subnames) > 2 && $subnames[0] == "inc" && $subnames[1] == $name){
						unset($subnames[0]);
						unset($subnames[1]);
						$optionName = implode(":", $subnames);
						$suboptions->{$optionName} = $optionValue;
					}
				}
				$suboptions->{"is:" . $field->referenceField} = $id;
				$item->{$name} = browseSubject($name, $suboptions)->items;
			}
		}
		
		$item->href = $GLOBALS["url"] . "/" . $subject . (property_exists($schema, "id") ? "/" . $id : "");
		
		// Postget
		if(property_exists($GLOBALS["resty"], $subject) && property_exists($GLOBALS["resty"]->{$subject}, "postGet"))
			if(call_user_func($GLOBALS["resty"]->{$subject}->postGet, $id, $item, $options) === false)
				return;
		
		return $item;
	}
	function deleteItem($subject, $id){
		$schema = is_object($subject) ? $subject : getSchema($subject);
		$subject = is_object($subject) ? $schema->name : $subject;
		$item = getItem($subject, $id);
		
		if($schema->owner != null){
			// Security: Get Ownership
			$relationship = getRelationship($schema, $id);
			if(property_exists($options, "own") && $options->own == "true")
				$item->ownership = $relationship !== null ? $relationship : null;
			
			// Security: Check Resource Access Policy
			if(property_exists($GLOBALS["resty"], $subject) && property_exists($GLOBALS["resty"]->{$subject}, "access-policy"))
				$accessPolicy = call_user_func($GLOBALS["resty"]->{$subject}->{"access-policy"}, $id, $options, $relationship);
			else if(property_exists($schema, "access"))
				$accessPolicy = $schema->access;
			if(isset($accessPolicy) && !in_array($relationship, $accessPolicy))
				return null;
	
			// Security: Check Resource Affect Policy
			if(property_exists($GLOBALS["resty"], $subject) && property_exists($GLOBALS["resty"]->{$subject}, "affect-policy"))
				$affectPolicy = call_user_func($GLOBALS["resty"]->{$subject}->{"affect-policy"}, $id, new stdClass(), $relationship);
			else if(property_exists($schema, "affect"))
				$affectPolicy = $schema->affect;
			if(isset($affectPolicy) && !in_array($relationship, $affectPolicy))
				return null;
		}
		
		// Delete
		if(property_exists($GLOBALS["resty"], $subject) && property_exists($GLOBALS["resty"]->{$subject}, "delete"))
			return call_user_func($GLOBALS["resty"]->{$subject}->delete, $id);

		// Pre-delete
		if(property_exists($GLOBALS["resty"], $subject) && property_exists($GLOBALS["resty"]->{$subject}, "preDelete"))
			call_user_func($GLOBALS["resty"]->{$subject}->preDelete, $id);

		// Remove files
		foreach($schema->fields as $name => $field)
			if($field->class == "file"){
				$directory = $GLOBALS["resty"]->_files . "/" . $schema->name;
				unlink($directory . "/" . $id . "/" . $name . "." . $item->{$name});
				if(count(scandir($directory . "/" . $id)) == 2)
					rmdir($directory . "/" . $id);
			}
		// DB	
		$GLOBALS["db"]->exec("DELETE FROM `" . $schema->name . "` WHERE `" . $schema->id . "` = " . $GLOBALS["db"]->quote($id));
		
		// Post-delete
		if(property_exists($GLOBALS["resty"], $subject) && property_exists($GLOBALS["resty"]->{$subject}, "postDelete"))
			call_user_func($GLOBALS["resty"]->{$subject}->postDelete, $id);
	}

	// Initiate	
	$GLOBALS["resty"] = $resty;
	if($_SERVER["REQUEST_METHOD"] == "OPTIONS"){
		header("HTTP/1.1 200 OK");
		header("Access-Control-Allow-Origin: *");
		header("Access-Control-Allow-Methods: POST, GET, PUT, DELETE");
		header("Access-Control-Allow-Credentials: true");
		header("Access-Control-Allow-Headers: Content-Type, Authorization");
		exit;
	}
	header("Access-Control-Allow-Origin: *");
	date_default_timezone_set("UTC");
	$GLOBALS["db"] = new PDO("mysql:host=" . $GLOBALS["resty"]->_database->host . ";dbname=" . $GLOBALS["resty"]->_database->name, $GLOBALS["resty"]->_database->username, $GLOBALS["resty"]->_database->password);
	$GLOBALS["db"]->exec("SET SESSION time_zone = '+00:00'");
	// Users Table Convention
	if(!property_exists($resty, "_users")){
		$subjects = new stdClass();
		if($GLOBALS["db"]->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES"
			. " WHERE TABLE_SCHEMA = " . $GLOBALS["db"]->quote($resty->_database->name)
			. " AND TABLE_NAME = 'users'")->fetch(PDO::FETCH_COLUMN) > 0)
			$resty->_users = "users";
	}
	$GLOBALS["url"] = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://" . $_SERVER["HTTP_HOST"] . "/" . $GLOBALS["resty"]->_path;
	if(property_exists($GLOBALS["resty"], "_users")){
		$GLOBALS["usersSchema"] = $usersSchema = getSchema($GLOBALS["resty"]->_users);
		$GLOBALS["authUser"] = null;
	}

	// Authentication
	if(array_key_exists("PHP_AUTH_USER", $_SERVER) && property_exists($GLOBALS["resty"], "_users")){
		$userSql = $db->query("SELECT `" . $usersSchema->id . "` FROM `" . $GLOBALS["resty"]->_users . "` WHERE `" . $usersSchema->username . "` = " . $db->quote($_SERVER["PHP_AUTH_USER"]))->fetch();
		if($userSql !== false){
			$sql = "SELECT id FROM `" . $GLOBALS["resty"]->_users . "` WHERE id = " . $db->quote($userSql[$usersSchema->id]);
			$sql .= " AND (`" . $usersSchema->passcode . "` = " . $db->quote($_SERVER["PHP_AUTH_PW"]);
			if($usersSchema->fields->{$usersSchema->passcode}->encrypt)
				$sql .= " OR `" . $usersSchema->passcode . "` = " . $db->quote(md5($_SERVER["PHP_AUTH_PW"] . $userSql[$usersSchema->id]));
			$sql .= ")";
			$userSql = $db->query($sql)->fetch();
		}
		if($userSql !== false)
			$GLOBALS["authUser"] = $userSql[$usersSchema->id];
		else{
			header("HTTP/1.1 401 Unauthorized");
			header("WWW-Authenticate: Basic realm=\"Resty\"");
			exit;
		}
	}

	// Get
	$get = new stdClass();
	foreach($_GET as $name => $value)
		if(substr($name, 0, 2) != "__")
			$get->{$name} = $value;
	
	// Post
	if(count($_POST) > 0){
		$post = new stdClass();
		foreach($_POST as $name => $value)
			$post->{$name} = $value;
	}
	if(!isset($post) || $post == null){
		$postString = file_get_contents("php://input");
		if((!array_key_exists("__item", $_GET) || strpos($_GET["__item"], ".") === false) && (!array_key_exists("__field", $_GET) || strpos($_GET["__field"], ".") === false)){
			$post = json_decode($postString);
			if($post === null)
				$post = $postString;
		}
	}

	// Files
	$files = new stdClass();
	if(count($_FILES) > 0){
		$_xFILES = array();
		$firstFile = reset($_FILES);
		if(is_array($firstFile["name"]))
			foreach($_FILES as $key => $all)
		        foreach($all as $i => $val)
		            $_xFILES[$i][$key] = $val;
		else
			$_xFILES = $_FILES;
		foreach($_xFILES as $name => $xFile){
			$file = new stdClass();
			$file->interim = $xFile["tmp_name"];
			$file->extension = pathinfo($xFile["name"], PATHINFO_EXTENSION);
			$files->{$name} = $file;
		}
	}
	else if((array_key_exists("__item", $_GET) && strpos($_GET["__item"], ".") > 0) || (array_key_exists("__field", $_GET) && strpos($_GET["__field"], ".") > 0)){
		$file = new stdClass();
		$name = basename(strpos($_GET["__item"], ".") > 0 ? $_GET["__item"] : $_GET["__field"]);
		$file->{$name}->interim = generateID(32);
		$file->{$name}->extension = pathinfo(strpos($_GET["__item"], ".") > 0 ? $_GET["__item"] : $_GET["__field"], PATHINFO_EXTENSION);
		$putdata = fopen("php://input", "r");
		$fp = fopen($file->{$name}, "w");
		while($data = fread($putdata, 1024))
		  fwrite($fp, $data);
		fclose($fp);
		fclose($putdata);
		$files->file = $file;
	}
	
	// Schema
	if(array_key_exists("__subject", $_GET) && $_GET["__subject"] != "_schemas")
		$schema = getSchema($_GET["__subject"]);
	
	// Subject-to-item
	if(array_key_exists("__subject", $_GET) && $_GET["__subject"] != "_schemas")
		if(!property_exists($schema, "id")){
			if(array_key_exists("__item", $_GET))
				$_GET["__field"] = $_GET["__item"];
			unset($_GET["__item"]);
		}
		
	// Schemas Root
	if(array_key_exists("__subject", $_GET) && $_GET["__subject"] == "_schemas" && !array_key_exists("__item", $_GET))
		unset($_GET["__subject"]);

	// Field
	if(array_key_exists("__field", $_GET)){
		if($_SERVER["REQUEST_METHOD"] == "POST" || $_SERVER["REQUEST_METHOD"] == "PUT"){
			$filesArray = (array)$files;
			$postArray = (array)$post;
			$item = (object)array(
				$_GET["__field"] => count((array)$files) > 0 ? $filesArray[0] : (count((array)$post) > 0 ? $postArray[0] : null)
			);
			$result = applyItem($_GET["__subject"], $_GET["__item"], $post, $files);
			if($result !== null)
				header("HTTP/1.1 204 No Content");
			else
				header("HTTP/1.1 404 Not Found");
		}
		else if($_SERVER["REQUEST_METHOD"] == "GET"){
			$item = getItem($_GET["__subject"], $_GET["__item"], $get);
			if($item !== null){
				$value = $item->{$_GET["__field"]};
				header("HTTP/1.1 200 OK");
				header("Content-Type: text/json");
				echo(json_encode($value));
			}
			else
				header("HTTP/1.1 404 Not Found");
		}
		else if($_SERVER["REQUEST_METHOD"] == "DELETE"){
			$item = (object)array(
				$_GET["__field"] => null
			);
			applyItem($_GET["__subject"], $_GET["__item"], $item, $files);
			header("HTTP/1.1 204 No Content");
		}
	}
	// Item
	else if(array_key_exists("__item", $_GET)){
		if($_GET["__item"] == "_me")
			$_GET["__item"] = $GLOBALS["authUser"];
		if($_GET["__item"] === null)
			header("HTTP/1.1 404 Not Found");
		else if($_SERVER["REQUEST_METHOD"] == "POST" || $_SERVER["REQUEST_METHOD"] == "PUT"){
			// Virtual
			if(property_exists($GLOBALS["resty"], $_GET["__subject"]) && property_exists($GLOBALS["resty"]->{$_GET["__subject"]}, "apply"))
				call_user_func($GLOBALS["resty"]->{$_GET["__subject"]}->apply, $_GET["__item"], $post, $files);
			else
				applyItem($schema, $_GET["__item"], $post, $files);
			header("HTTP/1.1 204 No Content");
		}
		else if($_SERVER["REQUEST_METHOD"] == "GET"){
			if($_GET["__subject"] == "_schemas"){
				header("HTTP/1.1 200 OK");
				header("Content-Type: text/json");
				echo json_encode(getSchema($_GET["__item"]));
			}
			else{
				// Virtual
				if(property_exists($GLOBALS["resty"], $_GET["__subject"]) && property_exists($GLOBALS["resty"]->{$_GET["__subject"]}, "get"))
					$item = call_user_func($GLOBALS["resty"]->{$_GET["__subject"]}->apply, $_GET["__item"], $post, $files);
				else
					$item = getItem($schema, $_GET["__item"], $get);
				if($item !== null){
					header("HTTP/1.1 200 OK");
					header("Content-Type: text/json");
					echo json_encode($item);
				}
				else
					header("HTTP/1.1 404 Not Found");
			}
		}
		else if($_SERVER["REQUEST_METHOD"] == "DELETE"){
			// Virtual
			if(property_exists($GLOBALS["resty"], $_GET["__subject"]) && property_exists($GLOBALS["resty"]->{$_GET["__subject"]}, "delete"))
				call_user_func($GLOBALS["resty"]->{$_GET["__subject"]}->apply, $_GET["__item"]);
			else
				deleteItem($schema, $_GET["__item"]);
			header("HTTP/1.1 204 No Content");
		}
	}
	// Subject
	else if(array_key_exists("__subject", $_GET)){
		if($_SERVER["REQUEST_METHOD"] == "POST" || $_SERVER["REQUEST_METHOD"] == "PUT"){
			if($_GET["__subject"] == "_lost"){
				$browse = browseSubject($GLOBALS["resty"]->_users, $get);
				if(count($browse->items) >= 1){
					if(property_exists($resty, "_email") && property_exists($resty->_email, "lost")){
						$usersSchema = getSchema($resty->_users);
						$id = $browse->items[0]->{$usersSchema->id};
						$email = $browse->items[0]->{$usersSchema->email};
						$subject = interceptTags($resty->_email->lost->subject, $browse->items[0], true);
						$tempPasscode = generateID(5);
						$encryptedTempPasscode = $usersSchema->fields->{$usersSchema->passcode}->encrypt ? md5($tempPasscode . $id) : $tempPasscode;
						$GLOBALS["db"]->exec("UPDATE `" . $usersSchema->name . "` SET `" . $usersSchema->passcode . "` = " . $GLOBALS["db"]->quote($encryptedTempPasscode) . " WHERE `" . $usersSchema->id . "` = " . $GLOBALS["db"]->quote($id));
						$body = interceptTags($resty->_email->lost->body, (object)array_merge(array( "temppasscode"=> $tempPasscode ), (array)$browse->items[0]));
						mail($email, $subject, $body, "content-type:text/html;charset=utf-8");
					}
				}
				else{
					header("HTTP/1.1 404 Not Found");
					header("Content-Type: text/plain");
				}
			}
			else if(is_array($post)){
				$output = array();
				foreach($post as $item){
					// Virtual apply
					if(property_exists($GLOBALS["resty"], $_GET["__subject"]) && property_exists($GLOBALS["resty"]->{$_GET["__subject"]}, "apply"))
						$id = call_user_func($GLOBALS["resty"]->{$_GET["__subject"]}->apply, null, $item, $files);
					else
						$id = applyItem($_GET["__subject"], null, $item, $files);
					$output[] = $GLOBALS["url"] . "/" . $_GET["__subject"] . "/" . $id;
				}
				header("HTTP/1.1 200 OK");
				header("Content-Type: text/json");
				echo json_encode($output);
			}
			else{
				// Virtual apply
				if(property_exists($GLOBALS["resty"], $_GET["__subject"]) && property_exists($GLOBALS["resty"]->{$_GET["__subject"]}, "apply"))
					$id = call_user_func($GLOBALS["resty"]->{$_GET["__subject"]}->apply, null, $post, $files);
				// Actual apply
				else
					$id = applyItem($_GET["__subject"], null, $post, $files);

				header("HTTP/1.1 200 OK");
				header("Content-Type: text/plain");
				echo $id;
			}
		}
		else if($_SERVER["REQUEST_METHOD"] == "GET"){
			// Virtual browse
			if(property_exists($GLOBALS["resty"], $_GET["__subject"]) && property_exists($GLOBALS["resty"]->{$_GET["__subject"]}, "browse"))
				$browse = call_user_func($GLOBALS["resty"]->{$_GET["__subject"]}->browse, $get);
			// Actual browse
			else
				$browse = browseSubject($_GET["__subject"], $get);
			header("HTTP/1.1 200 OK");
			header("Content-Type: text/json");
			echo json_encode($browse);
		}
	}
	// Map
	else if($_SERVER["REQUEST_METHOD"] == "GET"){
		// Database Map
		$subjects = new stdClass();
		foreach($GLOBALS["db"]->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES"
			. " WHERE TABLE_SCHEMA = " . $GLOBALS["db"]->quote($GLOBALS["resty"]->_database->name)) as $sectionSql){
			$meta = json_decode($GLOBALS["db"]->query("SELECT TABLE_COMMENT FROM INFORMATION_SCHEMA.TABLES"
				. " WHERE TABLE_SCHEMA = " . $GLOBALS["db"]->quote($GLOBALS["resty"]->_database->name)
				. " AND TABLE_NAME = " . $GLOBALS["db"]->quote($sectionSql["TABLE_NAME"]))->fetch(PDO::FETCH_COLUMN, 0));
			$meta = $meta === null ? new stdClass() : $meta;
			$subjects->{$sectionSql["TABLE_NAME"]} = getSchema($sectionSql["TABLE_NAME"], true);
		}
		// API Map
		if(array_key_exists("api", $GLOBALS))
			foreach($GLOBALS["api"] as $name => $subject)
				if($name[0] == "_"){
					$methods = array();
					foreach($subject as $name => $function)
						if($name == "apply" || $name == "preApply" || $name == "postApply"){
							if(!in_array("POST", $methods))
								$methods[] = "POST";
							if(!in_array("PUT", $methods))
								$methods[] = "PUT";	
						}
						else if($name == "browse" || $name == "preBrowse" || $name == "postBrowse"){
							if(!in_array("GET", $methods))
								$methods[] = "BROWSE";
						}
						else if($name == "get" || $name == "preGet" || $name == "postGet"){
							if(!in_array("GET", $methods))
								$methods[] = "GET";
						}
						else if($name == "delete" || $name == "preDelete" || $name == "postDelete"){
							if(!in_array("DELETE", $methods))
								$methods[] = "DELETE";
						}
					$subject->{$name} = (object)array(
						"href" => $GLOBALS["url"] . "/" . $name,
						"schema" => $GLOBALS["url"] . "/" . $name . "/_schema",
						"methods" => $methods
					);
				}
		header("HTTP/1.1 200 OK");
		header("Content-Type: text/json");
		echo json_encode($subjects);
	}
?>
