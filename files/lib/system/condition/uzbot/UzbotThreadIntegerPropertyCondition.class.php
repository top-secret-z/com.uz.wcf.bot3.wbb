<?php
namespace wbb\system\condition\uzbot;
use wbb\data\thread\Thread;
use wbb\data\thread\ThreadList;
use wbb\system\condition\thread\ThreadIntegerPropertyCondition;
use wcf\data\condition\Condition;
use wcf\data\DatabaseObjectList;
use wcf\system\WCF;

/**
 * Condition implementation for an integer to day property of a thread.
 * 
 * @author		2014-2022 Zaydowicz
 * @license		GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package		com.uz.wcf.bot3.wbb
 */
class UzbotThreadIntegerPropertyCondition extends ThreadIntegerPropertyCondition {
	/**
	 * @inheritDoc
	 */
	public function addObjectListCondition(DatabaseObjectList $objectList, array $conditionData) {
		if (!($objectList instanceof ThreadList)) {
			throw new \InvalidArgumentException("Object list is no instance of '".ThreadList::class."', instance of '".get_class($objectList)."' given.");
		}
		
		if (isset($conditionData['greaterThan'])) {
			$objectList->getConditionBuilder()->add('thread.'.$this->getPropertyName().' < ?', [TIME_NOW - 86400 * $conditionData['greaterThan']]);
		}
	}
	
	/**
	 * @inheritDoc
	 */
	protected function getLabel() {
		return WCF::getLanguage()->get('wcf.acp.uzbot.wbb.condition.days.'.$this->getPropertyName());
	}
	
	/**
	 * @inheritDoc
	 */
	public function getFieldElement() {
		$greaterThanPlaceHolder = WCF::getLanguage()->get('wcf.condition.greaterThan');
		$lessThanPlaceHolder = WCF::getLanguage()->get('wcf.condition.lessThan');
		
		return <<<HTML
<input type="number" name="greaterThan_{$this->getIdentifier()}" value="{$this->greaterThan}" placeholder="{$greaterThanPlaceHolder}"{$this->getMinMaxAttributes('greaterThan')} class="medium">
HTML;
	}
}
