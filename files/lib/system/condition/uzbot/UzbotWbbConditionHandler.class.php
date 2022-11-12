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

use wcf\data\object\type\ObjectTypeCache;
use wcf\system\SingletonFactory;

/**
 * Handles bot receiver conditions.
 */
class UzbotWbbConditionHandler extends SingletonFactory
{
    /**
     * list of grouped user group / inactive assignment condition object types
     * @var    array
     */
    protected $groupedObjectTypes = [];

    /**
     * Returns the list of grouped user group / inactive assignment condition object types.
     *
     * @return    array
     */
    public function getGroupedObjectTypes()
    {
        return $this->groupedObjectTypes;
    }

    /**
     * @see    \wcf\system\SingletonFactory::init()
     */
    protected function init()
    {
        $objectTypes = ObjectTypeCache::getInstance()->getObjectTypes('com.uz.wcf.bot.condition.wbb');

        foreach ($objectTypes as $objectType) {
            if (!$objectType->conditiongroup) {
                continue;
            }

            if (!isset($this->groupedObjectTypes[$objectType->conditiongroup])) {
                $this->groupedObjectTypes[$objectType->conditiongroup] = [];
            }

            $this->groupedObjectTypes[$objectType->conditiongroup][$objectType->objectTypeID] = $objectType;
        }
    }
}
