<?php
	
	require_once("chiavututable.php");

	/**
	 *  
	 * 
	 * @author ilmich
	 * @package Chiavutu
	 */
	class Chiavutu {
		
		private $_delimiter="";
		private $_tableExt="";
		private $_dbDir="";		
		private $_tables=array();
		
		/**
		 * 
		 * @param string $dbDir Absolute path of database directory
		 * @param string $delimiter Fields delimiter (default tab)
		 * @param string $ext Table's file extension (default .tsv)
		 */
		public function __construct($dbDir="./",$delimiter="\t",$ext=".tsv") {
			$this->_delimiter = $delimiter;
			$this->_dbDir = $dbDir;
			$this->_tableExt=$ext;
		}
		
		/**
		 * Create a new instance of table object
		 * 
		 * 
		 * @param string $name Table name
		 * @param string $ext Table file extension (default null)
		 * @return ChiavutuTable instance of table object  
		 */
		public function getTable($name,$ext=null) {
			
			if (!file_exists($this->_dbDir)) {
				throw new ChiavutuException("Dir $dbDir not found or not writable",ChiavutuException::IO_ERROR);
			}
			
			if (is_null($ext)) {
				$ext = $this->_tableExt;
			}
			
			if (!file_exists($this->_getTablePath($name,$ext))) {
				throw new ChiavutuException("Table $name not found",ChiavutuException::TABLE_ERROR);
			}
			
			if (!isset($this->_tables[$name])) {
				$this->_tables[$name] = new ChiavutuTable($name,$this->_dbDir,$this->_delimiter,$ext);
			}			
			return $this->_tables[$name];
		}

		/**
		 * This method create a new table
		 * 
		 * @param string $name Table name
		 * @param array $fields List of fields
		 * @param string $tableExt File extension (default = null)
		 */
		public function createTable($name,$fields,$tableExt=null) {
						
			if (is_null($tableExt)) {
				$tableExt = $this->_tableExt;
			}
			
			$tb = new ChiavutuTable($name,$this->_dbDir,$this->_delimiter,$tableExt);			
			return $tb->create($fields);
			
		}
		
		private function _getTablePath($name,$ext) {
			return ChiavutuTable::getTableFullPath($name,$this->_dbDir,$ext);
		}
		
	}
	
	/**
	 * 
	 * @author ilmich
	 * @package Chiavutu
	 */
	class ChiavutuException extends Exception {
		
		/**
		 * Generic error
		 * @var int 
		 */
		const UNKNOWN_ERROR = 0;
		
		/**
		 * I/O error
		 * @var int 
		 */
		const IO_ERROR = 1;
		
		/**
		 * Table error
		 * @var int 
		 */
		const TABLE_ERROR = 2;
		
	}

?>