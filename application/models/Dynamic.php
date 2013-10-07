<?php
	/**
	 * This class implements the basis for dynamic models.
	 * In this implementation class definitions are generated dynamically.
	 * This class and its descendants should be declared abstract!
	 */
	abstract class Dynamic extends LSActiveRecord
	{
		/**
		 * @var int The dynamic part of the class name.
		 */
		protected $id;

		public function __construct($scenario = 'insert') {
			parent::__construct($scenario);
			list(,$this->id)=explode('_', get_class($this));
			//$this->id = explode('_', get_class($this))[1];
		}

		/**
		 *
		 * @param type $className
		 * @return Dynamic2
		 */

		public static function model($className = null) {
			if (!isset($className))
			{
				$className =  get_called_class();
			}
			elseif (is_numeric($className))
			{
				$className = get_called_class() . '_' . $className;
			}
			return parent::model($className);
		}

		public static function create($id, $scenario = 'insert')
		{
			$className = get_called_class() . '_' . $id;
			return new $className($scenario);
		}

	}

?>
