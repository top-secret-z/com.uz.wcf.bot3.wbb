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
use wbb\system\condition\thread\ThreadIntegerPropertyCondition;
use wcf\data\condition\Condition;
use wcf\data\DatabaseObjectList;
use wcf\system\WCF;

/**
 * Condition implementation for an integer to day property of a thread.
 */
class UzbotThreadIntegerPropertyCondition extends ThreadIntegerPropertyCondition
{
    /**
     * @inheritDoc
     */
    public function addObjectListCondition(DatabaseObjectList $objectList, array $conditionData)
    {
        if (!($objectList instanceof ThreadList)) {
            throw new InvalidArgumentException("Object list is no instance of '" . ThreadList::class . "', instance of '" . \get_class($objectList) . "' given.");
        }

        if (isset($conditionData['greaterThan'])) {
            $objectList->getConditionBuilder()->add('thread.' . $this->getPropertyName() . ' < ?', [TIME_NOW - 86400 * $conditionData['greaterThan']]);
        }
    }

    /**
     * @inheritDoc
     */
    protected function getLabel()
    {
        return WCF::getLanguage()->get('wcf.acp.uzbot.wbb.condition.days.' . $this->getPropertyName());
    }

    /**
     * @inheritDoc
     */
    public function getFieldElement()
    {
        $greaterThanPlaceHolder = WCF::getLanguage()->get('wcf.condition.greaterThan');
        $lessThanPlaceHolder = WCF::getLanguage()->get('wcf.condition.lessThan');

        return <<<HTML
<input type="number" name="greaterThan_{$this->getIdentifier()}" value="{$this->greaterThan}" placeholder="{$greaterThanPlaceHolder}"{$this->getMinMaxAttributes('greaterThan')} class="medium">
HTML;
    }
}
