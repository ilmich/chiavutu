<?php

	/**
	 * 
	 * 
	 * @author ilmich
	 * @package Chiavutu
	 */
	class ChiavutuTable {
		
		private $_delimiter="\t";
		private $_tableName="";
		private $_tablePath="";
		private $_fields = array();
		
		public static function getTableFullPath($tableName,$dbDir,$ext=".tsv") {
			return $dbDir.$tableName.$ext;
		}
		
		/**
		 * 
		 * @param string $tableName
		 * @param string $dbDir
		 * @param string $delimiter
		 * @param string $ext 
		 */
		public function __construct($tableName,$dbDir,$delimiter=null,$ext) {
			
			if (!is_null($delimiter)) {
				$this->_delimiter = $delimiter;
			}
			
			//create table name
			$path = self::getTableFullPath($tableName,$dbDir,$ext);
			
			$this->_tableName = $tableName;
			$this->_tablePath = $path;	
						
			//try to load fields
			try {
				$this->_loadFields();
			}catch (ChiavutuException $ex) {
				//do nothing
			}			
		}

		/**
		 * Delete record
		 * @param string $find_condition
		 */
		public function deleteRecord($find_condition) {
			
			@unlink ($this->_tablePath."_");					// it might exist if a previous deletion failed
			rename ($this->_tablePath, $this->_tablePath."_");		// lock the table
			
			$old_table = fopen($this->_tablePath."_",'r'); 	// open the locked table
			$new_table = fopen($this->_tablePath,'w'); 		// open the new table
	
			// lets first transfer the first line, which holds the fields
			$record = fgets($old_table);
			$field  = explode($this->_delimiter, $record);
			fwrite($new_table, $record);
			
			if ($find_condition != '*') { // are there conditions?
				$condition = explode(",", $find_condition);
				$num_condition = 0;
				foreach ($condition as $current_condition) {
					$num_condition += 1;
					list($condition_field[$num_condition], $condition_value[$num_condition]) = explode("=", $current_condition);
					$compare_method[$num_condition] = substr($condition_field[$num_condition], -1);
					$condition_field[$num_condition] = substr($condition_field[$num_condition], 0, strlen($condition_field[$num_condition]) -1);
				}
			}
			
			while ( $record = fgets($old_table) ) {
				$record_values = explode($this->_delimiter, $record);
				$delete_record = FALSE;
				$num_compare = 0;
				
				if ($find_condition != '*') {			// there are conditions
					for ($i = 1; $i <= $num_condition; $i++) {
						$record_value = trim($record_values[$this->_findField($condition_field[$i])]);
						if ($compare_method[$i] == "+" && $record_value == $condition_value[$i]) { $num_compare += 1; }
						if ($compare_method[$i] == "-" && $record_value != $condition_value[$i]) { $num_compare += 1;; }
						if ($compare_method[$i] == "~" && strrpos($record_value, $condition_value[$i]) !== FALSE) { $num_compare += 1;; }
						if ($compare_method[$i] == "<" && intval($record_value) < intval($condition_value[$i])) { $num_compare += 1; }
						if ($compare_method[$i] == ">" && intval($record_value) > intval($condition_value[$i])) { $num_compare += 1; }
					}
					if ($num_compare == $num_condition) { $delete_record = TRUE; }
				}
				else { $delete_record = TRUE; }	// no conditions and no specific id			
				
				if (!$delete_record) {
					fwrite($new_table, $record);
				}
			}
			fclose($old_table);
			fclose($new_table);
			unlink ($db_file."_");			// delete lock table
			return TRUE;
		}
		
		/**
		 * Update record
		 * @param array $value
		 */
		public function updateRecord($value) {
			
			$this->deleteRecord("id+=".$value[0]);
			$this->addRecord($value);
			
		}		
		/**
		 * Add record
		 * @param array $value
		 */
		public function addRecord($value)  {

			$db_handle = fopen ($this->_tablePath, "a+"); // cursor at the end of the file
			
			if (!$db_handle) { 
				throw new ChiavutuException("I/O error when accessing file $this->_tablePath",ChiavutuException::IO_ERROR); 
			}
			
			$record = fgets($db_handle); // first line lists all table fields			
			
			if (trim($value[0]) == "AUTO") { // if the first field is an auto incremented id, then find the highest ID and add +1
				$auto_id = 0;
				while ( $record = fgets($db_handle) ) {					
					$current_record = explode($this->_delimiter, $record);					
					if ($current_record[0] > $auto_id) { $auto_id = $current_record[0]; }
				}				
				$value[0] = $auto_id +1;
			}
			
			$new_record = implode($this->_delimiter, $value);
			
			fwrite($db_handle, $new_record."\r\n");
			fclose($db_handle); // close table
			return $value[0];
			
		}
		
		public function openRecord($select_fields, $find_condition, $sort_order='', $auto_join='false', $limit='') {
				
			$find_condition	= trim($find_condition);
			$sort_order		= trim($sort_order);
			
			$db_handle = fopen ($this->_tablePath, "r+"); 
			
			if (!$db_handle) { 
				throw new ChiavutuException("I/O error when accessing file $this->_tablePath",ChiavutuException::IO_ERROR); 
			}
						
			$record = trim(fgets($db_handle)); // first line lists all table fields
			$field = explode($this->_delimiter,$record);
			
			if ($find_condition != '*') { // are there conditions?
				$condition = explode(",", $find_condition);
				$num_condition = 0;
				foreach ($condition as $current_condition) {
					$num_condition += 1;
					list($condition_field[$num_condition], $condition_value[$num_condition]) = explode("=", $current_condition);
					$compare_method[$num_condition] = substr($condition_field[$num_condition], -1);
					$condition_field[$num_condition] = substr($condition_field[$num_condition], 0, strlen($condition_field[$num_condition]) -1);
				}
			}
			
			if ($sort_order != '') { // do we need to do some sorting?
				list($sort_field, $sort_type, $sort_direction) = explode(" ", $sort_order);
			}
			
			$search_result = "";				// if no records are found this will prevent an error
			$record_counter = -1;				// no records.. yet
			
			while ( $record = trim(fgets($db_handle)) ) {
				$record_values = explode($this->_delimiter, $record);	// process the record
				$add_record = FALSE;
				$num_compare = 0;
				
				if ($find_condition != '*') {			// there are conditions
					for ($i = 1; $i <= $num_condition; $i++) {
						$record_value = trim($record_values[$this->_findField($condition_field[$i])]);
						if ($compare_method[$i] == "+" && $record_value == $condition_value[$i]) { $num_compare += 1; }
						if ($compare_method[$i] == "-" && $record_value != $condition_value[$i]) { $num_compare += 1;; }
						if ($compare_method[$i] == "~" && strrpos($record_value, $condition_value[$i]) !== FALSE) { $num_compare += 1;; }
						if ($compare_method[$i] == "<" && $record_value < $condition_value[$i]) { $num_compare += 1; }
						if ($compare_method[$i] == ">" && $record_value > $condition_value[$i]) { $num_compare += 1; }
					}
					if ($num_compare == $num_condition) { $add_record = TRUE; }
				}
				else { $add_record = TRUE; }	// no conditions and no specific id
				
				if ($add_record) {
					$record_counter += 1;			// add a record
					$search_result[$record_counter] = $record_values;
					if ($sort_order != '') {
						$sort_array[$record_counter] = $record_values[$this->_findField($sort_field)];
					}
				}
			}	
	
			if ($record_counter == -1) { return false; }
			
			if ($auto_join == 'true') {
				
				throw new ChiavutuException("Auto join not yet implemented",ChiavutuException::TABLE_ERROR);
				
//				foreach ($field as $fieldkey => $_field) { 
//					$_field = trim($_field);
//					if (substr($_field, -3) == "_id") {	// an _ID field linked to another table
//						foreach ($search_result as $searchkey => $_search_result) {
//							$value = explode($this->_delimiter, $_search_result);		// divide the record values
//							$table = explode ("_", $_field);			// and id field syntax is tablename_id
//							$value[$fieldkey] = FindValue($table[0], $value[$fieldkey], "name");	// replace the id by the name field
//							$search_result[$searchkey] = implode($this->_delimiter, $value);	// merge it back into a record
//						}
//					}	
//				}
			}	
			
			if ($sort_order != '') { // let's sort the record array according to the sort_array
				if ($sort_direction ==  "asc" && $sort_type == "str") 	{ array_multisort($sort_array, SORT_ASC, SORT_STRING, $search_result); }
				if ($sort_direction ==  "asc" && $sort_type == "num") 	{ array_multisort($sort_array, SORT_ASC, SORT_NUMERIC, $search_result); }
				if ($sort_direction ==  "desc" && $sort_type == "str") 	{ array_multisort($sort_array, SORT_DESC, SORT_STRING, $search_result); }
				if ($sort_direction ==  "desc" && $sort_type == "num") 	{ array_multisort($sort_array, SORT_DESC, SORT_NUMERIC, $search_result); }
			}
			
			if ($select_fields != '*') { // we don't return all fields, just the ones in $select_fields
				$selection = explode(",", $select_fields);
				foreach ($search_result as $result_key => $record) {
					$field = $record; //explode($this->_delimiter, $record);
					foreach ($selection as $key => $sel_field) {
						$new_record[$key] = $field[$this->_findField($sel_field)];						
					}
					$search_result[$result_key] = $new_record; //implode($this->_delimiter, $new_record);
				}
			}
			
			fclose($db_handle); // close table
			
			// now trim all values in the recordset and maybe apply a limit.
			$copy_result = $search_result;
			unset ($search_result);
			foreach ($copy_result as $result_key=>$trim_result) {
				$value = $trim_result; //explode($this->_delimiter, $trim_result);
				foreach ($value as $trim_key => $trim_value) {					
					$value[$trim_key] = trim($trim_value);
				}
				//$search_result[$result_key] = implode($this->_delimiter, $value);
				$search_result[$result_key] = $value;
				if ($result_key+1 == $limit) { break; }
			}
			return $search_result;
		}
		
		/**
		 * Create table
		 * @param array $fields
		 */
		public function create($fields) {
						
			if (!is_array($fields)) {
				throw new ChiavutuException("List of fields must be an array",ChiavutuException::TABLE_ERROR);
			}

			if (file_exists($this->_tablePath)) {
				throw new ChiavutuException("File $this->_tablePath exists",ChiavutuException::TABLE_ERROR);				
			}
			
			$db_handle = @fopen($this->_tablePath,"w+");			
			if (!$db_handle) { 
				throw new ChiavutuException("I/O error when creating file $this->_tablePath",ChiavutuException::IO_ERROR); 
			}
			
			if (!array_search($fields,"id")) {
				array_unshift($fields,"id");		
			}
			
			fputcsv($db_handle,$fields,$this->_delimiter);			
			fclose($db_handle);
			
			$this->_fields = $fields;
			
			return $this;			
		}
		
		/**
		 * Drop table 
		 * 
		 */
		public function drop() {
			
			if (!file_exists($this->_tablePath)) {
				throw new ChiavutuException("File $this->_tablePath not found. Nothing to drop",ChiavutuException::IO_ERROR);				
			}
			
			if (!unlink($this->_tablePath)) {
				throw new ChiavutuException("Error when deleting $this->_tablePath",ChiavutuException::IO_ERROR);
			}
			
			return true;
			
		}
		
		private function _findField($field) {
			return array_search($field,$this->_fields);
		}
		
		private function _findFieldName($find_field) {
			return $this->_fields($find_field);			
		}
		
		private function _loadFields() {
			
			$db_handle = fopen ($this->_tablePath, "r");
			
			if (!$db_handle) { 
				throw new ChiavutuException("I/O error when accessing file $this->_tablePath",ChiavutuException::IO_ERROR); 
			}
			
			$record = trim(fgets($db_handle)); // first line lists all table fields
			$this->_fields = explode($this->_delimiter,$record);
			
			fclose($db_handle);
		}
		
	}	
?>