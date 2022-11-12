<?php

/*
 * Copyright by Udo Zaydowicz.
 * Modified by SoftCreatR.dev.
 *
 * License: http://opensource.org/licenses/lgpl-license.php
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program; if not, write to the Free Software Foundation,
 * Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */
namespace wbb\system\condition\uzbot;

use InvalidArgumentException;
use wbb\data\thread\Thread;
use wbb\data\thread\ThreadList;
use wcf\data\condition\Condition;
use wcf\data\DatabaseObject;
use wcf\data\DatabaseObjectList;
use wcf\system\condition\AbstractSingleFieldCondition;
use wcf\system\condition\IContentCondition;
use wcf\system\condition\IObjectCondition;
use wcf\system\condition\IObjectListCondition;
use wcf\system\exception\UserInputException;
use wcf\system\WCF;

/**
 * Condition implementation for the label state of a thread.
 */
class UzbotThreadLabelStateCondition extends AbstractSingleFieldCondition implements IContentCondition, IObjectCondition, IObjectListCondition
{
    /**
     * @inheritDoc
     */
    protected $label = 'wcf.acp.uzbot.wbb.condition.label';

    /**
     * values of the possible state
     * @var    integer[]
     */
    protected $hasLabels = 0;

    protected $hasNoLabels = 0;

    /**
     * @see    \wcf\system\condition\IObjectListCondition::addObjectListCondition()
     */
    public function addObjectListCondition(DatabaseObjectList $objectList, array $conditionData)
    {
        if (!($objectList instanceof ThreadList)) {
            throw new InvalidArgumentException("Object list is no instance of '" . ThreadList::class . "', instance of '" . \get_class($objectList) . "' given.");
        }

        if (isset($conditionData['hasLabels'])) {
            $objectList->getConditionBuilder()->add('thread.hasLabels = ?', [1]);
        }
        if (isset($conditionData['hasNoLabels'])) {
            $objectList->getConditionBuilder()->add('thread.hasLabels = ?', [0]);
        }
    }

    /**
     * @inheritDoc
     */
    public function checkObject(DatabaseObject $object, array $conditionData)
    {
        // don't need it

        return true;
    }

    /**
     * @see    \wcf\system\condition\ICondition::getData()
     */
    public function getData()
    {
        $data = [];

        if ($this->hasLabels) {
            $data['hasLabels'] = 1;
        }
        if ($this->hasNoLabels) {
            $data['hasNoLabels'] = 1;
        }

        if (!empty($data)) {
            return $data;
        }

        return null;
    }

    /**
     * Returns the "checked" attribute for an input element.
     *
     * @param    string        $propertyName
     * @return    string
     */
    protected function getCheckedAttribute($propertyName)
    {
        if ($this->{$propertyName}) {
            return ' checked';
        }

        return '';
    }

    /**
     * @see    \wcf\system\condition\AbstractSingleFieldCondition::getFieldElement()
     */
    protected function getFieldElement()
    {
        $hasNoLabels = WCF::getLanguage()->get('wcf.acp.uzbot.wbb.condition.label.hasNoLabels');
        $hasLabels = WCF::getLanguage()->get('wcf.acp.uzbot.wbb.condition.label.hasLabels');

        return <<<HTML
<label><input type="checkbox" name="uzbotHasLabels" value="1"{$this->getCheckedAttribute('hasLabels')}> {$hasLabels}</label>
<label><input type="checkbox" name="uzbotHasNoLabels" value="1"{$this->getCheckedAttribute('hasNoLabels')}> {$hasNoLabels}</label>
HTML;
    }

    /**
     * @inheritDoc
     */
    public function readFormParameters()
    {
        if (isset($_POST['uzbotHasLabels'])) {
            $this->hasLabels = 1;
        }
        if (isset($_POST['uzbotHasNoLabels'])) {
            $this->hasNoLabels = 1;
        }
    }

    /**
     * @see    \wcf\system\condition\ICondition::reset()
     */
    public function reset()
    {
        $this->hasLabels = 0;
        $this->hasNoLabels = 0;
    }

    /**
     * @see    \wcf\system\condition\ICondition::setData()
     */
    public function setData(Condition $condition)
    {
        if ($condition->hasLabels !== null) {
            $this->hasLabels = $condition->hasLabels;
        }

        if ($condition->hasNoLabels !== null) {
            $this->hasNoLabels = $condition->hasNoLabels;
        }
    }

    /**
     * @inheritDoc
     */
    public function showContent(Condition $condition)
    {
        // don't need it
        return null;
    }

    /**
     * @see    \wcf\system\condition\ICondition::validate()
     */
    public function validate()
    {
        if ($this->hasLabels && $this->hasNoLabels) {
            $this->errorMessage = 'wcf.acp.uzbot.wbb.condition.label.error.conflict';

            throw new UserInputException('uzbotHasNoLabels', 'conflict');
        }
    }
}
