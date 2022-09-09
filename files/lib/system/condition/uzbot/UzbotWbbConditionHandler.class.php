<?php
namespace wbb\system\condition\uzbot;
use wcf\data\object\type\ObjectTypeCache;
use wcf\data\uzbot\Uzbot;
use wcf\system\SingletonFactory;

/**
 * Handles bot receiver conditions.
 * 
 * @author		2014-2022 Zaydowicz
 * @license		GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package		com.uz.wcf.bot3.wbb
 */
class UzbotWbbConditionHandler extends SingletonFactory {
	/**
	 * list of grouped user group / inactive assignment condition object types
	 * @var	array
	 */
	protected $groupedObjectTypes = [];

	/**
	 * Returns the list of grouped user group / inactive assignment condition object types.
	 *
	 * @return	array
	 */
	public function getGroupedObjectTypes() {
		return $this->groupedObjectTypes;
	}
	
	/**
	 * @see	\wcf\system\SingletonFactory::init()
	 */
	protected function init() {
		$objectTypes = ObjectTypeCache::getInstance()->getObjectTypes('com.uz.wcf.bot.condition.wbb');
		
		foreach ($objectTypes as $objectType) {
			if (!$objectType->conditiongroup) continue;
			
			if (!isset($this->groupedObjectTypes[$objectType->conditiongroup])) {
				$this->groupedObjectTypes[$objectType->conditiongroup] = [];
			}
			
			$this->groupedObjectTypes[$objectType->conditiongroup][$objectType->objectTypeID] = $objectType;
		}
	}
}
