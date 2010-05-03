<?php
	
	require_once("chaozztable.php");

	class ChaozzNg {
		
		private $_delimiter="";
		private $_tableExt="";
		private $_dbDir="";		
		private $_tables=array();
		
		public function __construct($dbDir,$delimiter="\t",$ext=".tsv") {
			$this->_delimiter = $delimiter;
			$this->_dbDir = $dbDir;
			$this->_tableExt=$ext;
		}
		
		public function getTable($name,$ext=null) {
			
			if (!file_exists($this->_dbDir)) {
				throw new ChaozzNgException("Dir $dbDir not found or not writable",ChaozzNgException::IO_ERROR);
			}
			
			if (is_null($ext)) {
				$ext = $this->_tableExt;
			}
			
			if (!file_exists($this->_getTablePath($name,$ext))) {
				throw new ChaozzNgException("Table $name not found",ChaozzNgException::TABLE_ERROR);
			}
			
			if (!isset($this->_tables[$name])) {
				$this->_tables[$name] = new ChaozzTable($name,$this->_dbDir,$this->_delimiter,$ext);
			}			
			return $this->_tables[$name];
		}

		public function createTable($name,$fields,$tableExt=null) {
						
			if (is_null($tableExt)) {
				$tableExt = $this->_tableExt;
			}
			
			$tb = new ChaozzTable($name,$this->_dbDir,$this->_delimiter,$tableExt);			
			return $tb->create($fields);
			
		}
		
		private function _getTablePath($name,$ext) {
			return ChaozzTable::getTableFullPath($name,$this->_dbDir,$ext);
		}
		
	}
	
	class ChaozzNgException extends Exception {
		
		const UNKNOWN_ERROR = 0;
		
		const IO_ERROR = 1;
		
		const TABLE_ERROR = 2;
		
	}

?>