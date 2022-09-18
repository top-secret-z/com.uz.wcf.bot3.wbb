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
namespace wbb\system\event\listener;

use wbb\data\board\Board;
use wbb\data\board\BoardCache;
use wbb\data\board\RealtimeBoardNodeList;
use wbb\data\thread\Thread;
use wbb\system\condition\uzbot\UzbotWbbConditionHandler;
use wcf\data\user\User;
use wcf\data\uzbot\notification\UzbotNotify;
use wcf\data\uzbot\type\UzbotType;
use wcf\system\condition\ConditionHandler;
use wcf\system\event\listener\IParameterizedEventListener;
use wcf\system\exception\UserInputException;
use wcf\system\WCF;
use wcf\util\ArrayUtil;
use wcf\util\StringUtil;

/**
 * Listen to addForm events for Bot
 */

class UzbotAddFormWbbListener implements IParameterizedEventListener
{
    /**
     * instance of UzbotAddForm
     */
    protected $eventObj;

    /**
     * general data
     */
    protected $boardNodeList;

    /**
     * thread and post data
     */
    protected $threadNotifyBoardID = 0;

    protected $threadNotifyDelayed = 0;

    protected $threadNotifyIsClosed = 0;

    protected $threadNotifyIsDisabled = 0;

    protected $threadNotifyIsDone = 0;

    protected $threadNotifyIsSticky = 0;

    protected $postNotifyThreadID = 0;

    protected $postNotifyIsClosed = 0;

    protected $postNotifyIsDisabled = 0;

    protected $wbbThreadModerationData = 0;

    protected $threadModerationAuthorOnly = 0;

    protected $threadModerationChangeTopic = 0;

    protected $threadModerationClose = 0;

    protected $threadModerationDisable = 0;

    protected $threadModerationDone = 0;

    protected $threadModerationEnable = 0;

    protected $threadModerationMerge = 0;

    protected $threadModerationMove = 0;

    protected $threadModerationOpen = 0;

    protected $threadModerationRestore = 0;

    protected $threadModerationScrape = 0;

    protected $threadModerationSetAsAnnouncement = 0;

    protected $threadModerationSetLabel = 0;

    protected $threadModerationSticky = 0;

    protected $threadModerationTrash = 0;

    protected $threadModerationUndone = 0;

    protected $threadModerationUnsetAsAnnouncement = 0;

    protected $wbbThreadModificationData = 0;

    protected $threadModificationAuthorOnly = 0;

    protected $threadModificationBoardID = 0;

    protected $threadModificationExecuter = '';

    protected $threadModificationExecuterID = 0;

    protected $threadModificationClose = 0;

    protected $threadModificationDisable = 0;

    protected $threadModificationDone = 0;

    protected $threadModificationEnable = 0;

    protected $threadModificationMove = 0;

    protected $threadModificationOpen = 0;

    protected $threadModificationRestore = 0;

    protected $threadModificationScrape = 0;

    protected $threadModificationSetLabel = 0;

    protected $threadModificationSticky = 0;

    protected $threadModificationTrash = 0;

    protected $threadModificationUndone = 0;

    protected $threadModificationUnannounce = 0;

    protected $wbbPostModerationData = 0;

    protected $postModerationClose = 0;

    protected $postModerationDelete = 0;

    protected $postModerationDisable = 0;

    protected $postModerationEdit = 0;

    protected $postModerationEnable = 0;

    protected $postModerationMerge = 0;

    protected $postModerationMove = 0;

    protected $postModerationOpen = 0;

    protected $postModerationRestore = 0;

    protected $postModerationTrash = 0;

    /**
     * further bot data
     */
    protected $postCountAction = 'postTotal';

    protected $topPosterCount = 1;

    protected $topPosterInterval = 1;

    protected $threadNewBoardIDs = [];

    protected $uzbotBoardIDs = [];

    protected $postIsOfficial = 0;

    protected $threadIsOfficial = 0;

    /**
     * postChange switches
     */
    protected $postChangeUpdate = 1;

    protected $postChangeDelete = 1;

    /**
     * condition data
     */
    public $wbbConditions = [];

    /**
     * @inheritDoc
     */
    public function execute($eventObj, $className, $eventName, array &$parameters)
    {
        $this->eventObj = $eventObj;
        $this->{$eventName}();
    }

    /**
     * Handles the readData event. Only in UzbotEdit!
     */
    protected function readData()
    {
        if (empty($_POST)) {
            if (!empty($this->eventObj->uzbot->wbbThreadData)) {
                $wbbThreadData = \unserialize($this->eventObj->uzbot->wbbThreadData);
                $this->threadNotifyBoardID = $wbbThreadData['threadNotifyBoardID'];
                $this->threadNotifyDelayed = $wbbThreadData['threadNotifyDelayed'];
                $this->threadNotifyIsClosed = $wbbThreadData['threadNotifyIsClosed'];
                $this->threadNotifyIsDisabled = $wbbThreadData['threadNotifyIsDisabled'];
                $this->threadNotifyIsDone = $wbbThreadData['threadNotifyIsDone'];
                $this->threadNotifyIsSticky = $wbbThreadData['threadNotifyIsSticky'];
            }

            if (!empty($this->eventObj->uzbot->wbbPostData)) {
                $wbbPostData = \unserialize($this->eventObj->uzbot->wbbPostData);
                $this->postNotifyThreadID = $wbbPostData['postNotifyThreadID'];
                $this->postNotifyIsClosed = $wbbPostData['postNotifyIsClosed'];
                $this->postNotifyIsDisabled = $wbbPostData['postNotifyIsDisabled'];
            }

            if (!empty($this->eventObj->uzbot->wbbThreadModerationData)) {
                $wbbThreadModerationData = \unserialize($this->eventObj->uzbot->wbbThreadModerationData);
                $this->threadModerationAuthorOnly = $wbbThreadModerationData['threadModerationAuthorOnly'];
                $this->threadModerationChangeTopic = $wbbThreadModerationData['threadModerationChangeTopic'];
                $this->threadModerationClose = $wbbThreadModerationData['threadModerationClose'];
                $this->threadModerationDisable = $wbbThreadModerationData['threadModerationDisable'];
                $this->threadModerationDone = $wbbThreadModerationData['threadModerationDone'];
                $this->threadModerationEnable = $wbbThreadModerationData['threadModerationEnable'];
                $this->threadModerationMerge = $wbbThreadModerationData['threadModerationMerge'];
                $this->threadModerationMove = $wbbThreadModerationData['threadModerationMove'];
                $this->threadModerationOpen = $wbbThreadModerationData['threadModerationOpen'];
                $this->threadModerationRestore = $wbbThreadModerationData['threadModerationRestore'];
                $this->threadModerationScrape = $wbbThreadModerationData['threadModerationScrape'];
                $this->threadModerationSetAsAnnouncement = $wbbThreadModerationData['threadModerationSetAsAnnouncement'];
                $this->threadModerationSetLabel = $wbbThreadModerationData['threadModerationSetLabel'];
                $this->threadModerationSticky = $wbbThreadModerationData['threadModerationSticky'];
                $this->threadModerationTrash = $wbbThreadModerationData['threadModerationTrash'];
                $this->threadModerationUndone = $wbbThreadModerationData['threadModerationUndone'];
                $this->threadModerationUnsetAsAnnouncement = $wbbThreadModerationData['threadModerationUnsetAsAnnouncement'];
            }

            if (!empty($this->eventObj->uzbot->wbbThreadModificationData)) {
                $wbbThreadModificationData = \unserialize($this->eventObj->uzbot->wbbThreadModificationData);
                $this->threadModificationAuthorOnly = $wbbThreadModificationData['threadModificationAuthorOnly'];
                $this->threadModificationBoardID = $wbbThreadModificationData['threadModificationBoardID'];
                $this->threadModificationExecuter = $wbbThreadModificationData['threadModificationExecuter'];
                $this->threadModificationExecuterID = $wbbThreadModificationData['threadModificationExecuterID'];
                $this->threadModificationClose = $wbbThreadModificationData['threadModificationClose'];
                $this->threadModificationDisable = $wbbThreadModificationData['threadModificationDisable'];
                $this->threadModificationDone = $wbbThreadModificationData['threadModificationDone'];
                $this->threadModificationEnable = $wbbThreadModificationData['threadModificationEnable'];
                $this->threadModificationMove = $wbbThreadModificationData['threadModificationMove'];
                $this->threadModificationOpen = $wbbThreadModificationData['threadModificationOpen'];
                $this->threadModificationRestore = $wbbThreadModificationData['threadModificationRestore'];
                $this->threadModificationScrape = $wbbThreadModificationData['threadModificationScrape'];
                $this->threadModificationSetLabel = $wbbThreadModificationData['threadModificationSetLabel'];
                $this->threadModificationSticky = $wbbThreadModificationData['threadModificationSticky'];
                $this->threadModificationTrash = $wbbThreadModificationData['threadModificationTrash'];
                $this->threadModificationUndone = $wbbThreadModificationData['threadModificationUndone'];

                if (isset($wbbThreadModificationData['threadModificationUnannounce'])) {
                    $this->threadModificationUnannounce = $wbbThreadModificationData['threadModificationUnannounce'];
                }
            }

            if (!empty($this->eventObj->uzbot->wbbPostModerationData)) {
                $wbbPostModerationData = \unserialize($this->eventObj->uzbot->wbbPostModerationData);
                $this->postModerationClose = $wbbPostModerationData['postModerationClose'];
                $this->postModerationDelete = $wbbPostModerationData['postModerationDelete'];
                $this->postModerationDisable = $wbbPostModerationData['postModerationDisable'];
                $this->postModerationEdit = $wbbPostModerationData['postModerationEdit'];
                $this->postModerationEnable = $wbbPostModerationData['postModerationEnable'];
                $this->postModerationMerge = $wbbPostModerationData['postModerationMerge'];
                $this->postModerationMove = $wbbPostModerationData['postModerationMove'];
                $this->postModerationOpen = $wbbPostModerationData['postModerationOpen'];
                $this->postModerationRestore = $wbbPostModerationData['postModerationRestore'];
                $this->postModerationTrash = $wbbPostModerationData['postModerationTrash'];
            }

            $this->postCountAction = $this->eventObj->uzbot->postCountAction;
            $this->topPosterCount = $this->eventObj->uzbot->topPosterCount;
            $this->topPosterInterval = $this->eventObj->uzbot->topPosterInterval;

            $this->postIsOfficial = $this->eventObj->uzbot->postIsOfficial;
            $this->threadIsOfficial = $this->eventObj->uzbot->threadIsOfficial;

            $this->postChangeUpdate = $this->eventObj->uzbot->postChangeUpdate;
            $this->postChangeDelete = $this->eventObj->uzbot->postChangeDelete;

            $this->threadNewBoardIDs = [];
            if (!empty($this->eventObj->uzbot->threadNewBoardIDs)) {
                $this->threadNewBoardIDs = \unserialize($this->eventObj->uzbot->threadNewBoardIDs);
            }
            $this->uzbotBoardIDs = [];
            if (!empty($this->eventObj->uzbot->uzbotBoardIDs)) {
                $this->uzbotBoardIDs = \unserialize($this->eventObj->uzbot->uzbotBoardIDs);
            }

            // conditions
            $this->wbbConditions = UzbotWbbConditionHandler::getInstance()->getGroupedObjectTypes();
            $conditions = ConditionHandler::getInstance()->getConditions('com.uz.wcf.bot.condition.wbb', $this->eventObj->botID);

            foreach ($conditions as $condition) {
                $this->wbbConditions[$condition->getObjectType()->conditiongroup][$condition->objectTypeID]->getProcessor()->setData($condition);
            }
        }
    }

    /**
     * Handles the assignVariables event.
     */
    protected function assignVariables()
    {
        $this->boardNodeList = new RealtimeBoardNodeList();
        $this->boardNodeList->readNodeTree();

        $this->wbbConditions = UzbotWbbConditionHandler::getInstance()->getGroupedObjectTypes();

        WCF::getTPL()->assign([
            'boardNodeList' => $this->boardNodeList->getNodeList(),

            'threadNotifyBoardID' => $this->threadNotifyBoardID,
            'threadNotifyDelayed' => $this->threadNotifyDelayed,
            'threadNotifyIsClosed' => $this->threadNotifyIsClosed,
            'threadNotifyIsDisabled' => $this->threadNotifyIsDisabled,
            'threadNotifyIsDone' => $this->threadNotifyIsDone,
            'threadNotifyIsSticky' => $this->threadNotifyIsSticky,

            'postNotifyThreadID' => $this->postNotifyThreadID,
            'postNotifyIsClosed' => $this->postNotifyIsClosed,
            'postNotifyIsDisabled' => $this->postNotifyIsDisabled,

            'postCountAction' => $this->postCountAction,
            'topPosterCount' => $this->topPosterCount,
            'topPosterInterval' => $this->topPosterInterval,
            'threadNewBoardIDs' => $this->threadNewBoardIDs,
            'uzbotBoardIDs' => $this->uzbotBoardIDs,

            'postIsOfficial' => $this->postIsOfficial,
            'threadIsOfficial' => $this->threadIsOfficial,

            'postChangeUpdate' => $this->postChangeUpdate,
            'postChangeDelete' => $this->postChangeDelete,

            'threadModerationAuthorOnly' => $this->threadModerationAuthorOnly,
            'threadModerationChangeTopic' => $this->threadModerationChangeTopic,
            'threadModerationClose' => $this->threadModerationClose,
            'threadModerationDisable' => $this->threadModerationDisable,
            'threadModerationDone' => $this->threadModerationDone,
            'threadModerationEnable' => $this->threadModerationEnable,
            'threadModerationMerge' => $this->threadModerationMerge,
            'threadModerationMove' => $this->threadModerationMove,
            'threadModerationOpen' => $this->threadModerationOpen,
            'threadModerationRestore' => $this->threadModerationRestore,
            'threadModerationScrape' => $this->threadModerationScrape,
            'threadModerationSetAsAnnouncement' => $this->threadModerationSetAsAnnouncement,
            'threadModerationSetLabel' => $this->threadModerationSetLabel,
            'threadModerationSticky' => $this->threadModerationSticky,
            'threadModerationTrash' => $this->threadModerationTrash,
            'threadModerationUndone' => $this->threadModerationUndone,
            'threadModerationUnsetAsAnnouncement' => $this->threadModerationUnsetAsAnnouncement,

            'threadModificationAuthorOnly' => $this->threadModificationAuthorOnly,
            'threadModificationBoardID' => $this->threadModificationBoardID,
            'threadModificationExecuter' => $this->threadModificationExecuter,
            'threadModificationExecuterID' => $this->threadModificationExecuterID,
            'threadModificationClose' => $this->threadModificationClose,
            'threadModificationDisable' => $this->threadModificationDisable,
            'threadModificationDone' => $this->threadModificationDone,
            'threadModificationEnable' => $this->threadModificationEnable,
            'threadModificationMove' => $this->threadModificationMove,
            'threadModificationOpen' => $this->threadModificationOpen,
            'threadModificationRestore' => $this->threadModificationRestore,
            'threadModificationScrape' => $this->threadModificationScrape,
            'threadModificationSetLabel' => $this->threadModificationSetLabel,
            'threadModificationSticky' => $this->threadModificationSticky,
            'threadModificationTrash' => $this->threadModificationTrash,
            'threadModificationUndone' => $this->threadModificationUndone,
            'threadModificationUnannounce' => $this->threadModificationUnannounce,

            'postModerationClose' => $this->postModerationClose,
            'postModerationDelete' => $this->postModerationDelete,
            'postModerationDisable' => $this->postModerationDisable,
            'postModerationEdit' => $this->postModerationEdit,
            'postModerationEnable' => $this->postModerationEnable,
            'postModerationMerge' => $this->postModerationMerge,
            'postModerationMove' => $this->postModerationMove,
            'postModerationOpen' => $this->postModerationOpen,
            'postModerationRestore' => $this->postModerationRestore,
            'postModerationTrash' => $this->postModerationTrash,

            'wbbConditions' => $this->wbbConditions,
        ]);
    }

    /**
     * Handles the readFormParameters event.
     */
    protected function readFormParameters()
    {
        if (isset($_POST['threadNotifyBoardID'])) {
            $this->threadNotifyBoardID = \intval($_POST['threadNotifyBoardID']);
        }
        $this->threadNotifyDelayed = $this->threadNotifyIsClosed = $this->threadNotifyIsDisabled = 0;
        $this->threadNotifyIsDone = $this->threadNotifyIsSticky = 0;
        if (isset($_POST['threadNotifyDelayed'])) {
            $this->threadNotifyDelayed = \intval($_POST['threadNotifyDelayed']);
        }
        if (isset($_POST['threadNotifyIsClosed'])) {
            $this->threadNotifyIsClosed = \intval($_POST['threadNotifyIsClosed']);
        }
        if (isset($_POST['threadNotifyIsDisabled'])) {
            $this->threadNotifyIsDisabled = \intval($_POST['threadNotifyIsDisabled']);
        }
        if (isset($_POST['threadNotifyIsDone'])) {
            $this->threadNotifyIsDone = \intval($_POST['threadNotifyIsDone']);
        }
        if (isset($_POST['threadNotifyIsSticky'])) {
            $this->threadNotifyIsSticky = \intval($_POST['threadNotifyIsSticky']);
        }

        $this->postNotifyIsClosed = $this->postNotifyIsDisabled = 0;
        if (isset($_POST['postNotifyThreadID'])) {
            $this->postNotifyThreadID = \intval($_POST['postNotifyThreadID']);
        }
        if (isset($_POST['postNotifyIsClosed'])) {
            $this->postNotifyIsClosed = \intval($_POST['postNotifyIsClosed']);
        }
        if (isset($_POST['postNotifyIsDisabled'])) {
            $this->postNotifyIsDisabled = \intval($_POST['postNotifyIsDisabled']);
        }

        if (isset($_POST['postCountAction'])) {
            $this->postCountAction = StringUtil::trim($_POST['postCountAction']);
        }
        if (isset($_POST['topPosterCount'])) {
            $this->topPosterCount = \intval($_POST['topPosterCount']);
        }
        if (isset($_POST['topPosterInterval'])) {
            $this->topPosterInterval = \intval($_POST['topPosterInterval']);
        }
        if (isset($_POST['threadNewBoardIDs']) && \is_array($_POST['threadNewBoardIDs'])) {
            $this->threadNewBoardIDs = ArrayUtil::toIntegerArray($_POST['threadNewBoardIDs']);
        }
        if (isset($_POST['uzbotBoardIDs']) && \is_array($_POST['uzbotBoardIDs'])) {
            $this->uzbotBoardIDs = ArrayUtil::toIntegerArray($_POST['uzbotBoardIDs']);
        }

        $this->postIsOfficial = $this->threadIsOfficial = 0;
        if (isset($_POST['postIsOfficial'])) {
            $this->postIsOfficial = \intval($_POST['postIsOfficial']);
        }
        if (isset($_POST['threadIsOfficial'])) {
            $this->threadIsOfficial = \intval($_POST['threadIsOfficial']);
        }

        $this->postChangeUpdate = $this->postChangeDelete = 0;
        if (isset($_POST['postChangeUpdate'])) {
            $this->postChangeUpdate = \intval($_POST['postChangeUpdate']);
        }
        if (isset($_POST['postChangeDelete'])) {
            $this->postChangeDelete = \intval($_POST['postChangeDelete']);
        }

        $this->threadModerationChangeTopic = $this->threadModerationClose = $this->threadModerationDisable = 0;
        $this->threadModerationDone = $this->threadModerationEnable = $this->threadModerationMerge = $this->threadModerationMove = $this->threadModerationOpen = 0;
        $this->threadModerationRestore = $this->threadModerationScrape = $this->threadModerationSetAsAnnouncement = $this->threadModerationSetLabel = 0;
        $this->threadModerationSticky = $this->threadModerationTrash = $this->threadModerationUndone = $this->threadModerationAuthorOnly = 0;
        if (isset($_POST['threadModerationAuthorOnly'])) {
            $this->threadModerationAuthorOnly = \intval($_POST['threadModerationAuthorOnly']);
        }
        if (isset($_POST['threadModerationChangeTopic'])) {
            $this->threadModerationChangeTopic = \intval($_POST['threadModerationChangeTopic']);
        }
        if (isset($_POST['threadModerationClose'])) {
            $this->threadModerationClose = \intval($_POST['threadModerationClose']);
        }
        if (isset($_POST['threadModerationDisable'])) {
            $this->threadModerationDisable = \intval($_POST['threadModerationDisable']);
        }
        if (isset($_POST['threadModerationDone'])) {
            $this->threadModerationDone = \intval($_POST['threadModerationDone']);
        }
        if (isset($_POST['threadModerationEnable'])) {
            $this->threadModerationEnable = \intval($_POST['threadModerationEnable']);
        }
        if (isset($_POST['threadModerationMerge'])) {
            $this->threadModerationMerge = \intval($_POST['threadModerationMerge']);
        }
        if (isset($_POST['threadModerationMove'])) {
            $this->threadModerationMove = \intval($_POST['threadModerationMove']);
        }
        if (isset($_POST['threadModerationOpen'])) {
            $this->threadModerationOpen = \intval($_POST['threadModerationOpen']);
        }
        if (isset($_POST['threadModerationRestore'])) {
            $this->threadModerationRestore = \intval($_POST['threadModerationRestore']);
        }
        if (isset($_POST['threadModerationScrape'])) {
            $this->threadModerationScrape = \intval($_POST['threadModerationScrape']);
        }
        if (isset($_POST['threadModerationSetAsAnnouncement'])) {
            $this->threadModerationSetAsAnnouncement = \intval($_POST['threadModerationSetAsAnnouncement']);
        }
        if (isset($_POST['threadModerationSetLabel'])) {
            $this->threadModerationSetLabel = \intval($_POST['threadModerationSetLabel']);
        }
        if (isset($_POST['threadModerationSticky'])) {
            $this->threadModerationSticky = \intval($_POST['threadModerationSticky']);
        }
        if (isset($_POST['threadModerationTrash'])) {
            $this->threadModerationTrash = \intval($_POST['threadModerationTrash']);
        }
        if (isset($_POST['threadModerationUndone'])) {
            $this->threadModerationUndone = \intval($_POST['threadModerationUndone']);
        }
        if (isset($_POST['threadModerationUnsetAsAnnouncement'])) {
            $this->threadModerationUnsetAsAnnouncement = \intval($_POST['threadModerationUnsetAsAnnouncement']);
        }

        $this->threadModificationClose = $this->threadModificationDisable = 0;
        $this->threadModificationDone = $this->threadModificationEnable = $this->threadModificationMove = $this->threadModificationOpen = 0;
        $this->threadModificationRestore = $this->threadModificationScrape = $this->threadModificationSetLabel = 0;
        $this->threadModificationSticky = $this->threadModificationTrash = $this->threadModificationUndone = $this->threadModificationUnannounce = $this->threadModificationAuthorOnly = 0;
        if (isset($_POST['threadModificationAuthorOnly'])) {
            $this->threadModificationAuthorOnly = \intval($_POST['threadModificationAuthorOnly']);
        }
        if (isset($_POST['threadModificationBoardID'])) {
            $this->threadModificationBoardID = \intval($_POST['threadModificationBoardID']);
        }
        if (isset($_POST['threadModificationExecuter'])) {
            $this->threadModificationExecuter = StringUtil::trim($_POST['threadModificationExecuter']);
        }
        if (isset($_POST['threadModificationExecuterID'])) {
            $this->threadModificationExecuterID = \intval($_POST['threadModificationExecuterID']);
        }
        if (isset($_POST['threadModificationClose'])) {
            $this->threadModificationClose = \intval($_POST['threadModificationClose']);
        }
        if (isset($_POST['threadModificationDisable'])) {
            $this->threadModificationDisable = \intval($_POST['threadModificationDisable']);
        }
        if (isset($_POST['threadModificationDone'])) {
            $this->threadModificationDone = \intval($_POST['threadModificationDone']);
        }
        if (isset($_POST['threadModificationEnable'])) {
            $this->threadModificationEnable = \intval($_POST['threadModificationEnable']);
        }
        if (isset($_POST['threadModificationMove'])) {
            $this->threadModificationMove = \intval($_POST['threadModificationMove']);
        }
        if (isset($_POST['threadModificationOpen'])) {
            $this->threadModificationOpen = \intval($_POST['threadModificationOpen']);
        }
        if (isset($_POST['threadModificationRestore'])) {
            $this->threadModificationRestore = \intval($_POST['threadModificationRestore']);
        }
        if (isset($_POST['threadModificationScrape'])) {
            $this->threadModificationScrape = \intval($_POST['threadModificationScrape']);
        }
        if (isset($_POST['threadModificationSetLabel'])) {
            $this->threadModificationSetLabel = \intval($_POST['threadModificationSetLabel']);
        }
        if (isset($_POST['threadModificationSticky'])) {
            $this->threadModificationSticky = \intval($_POST['threadModificationSticky']);
        }
        if (isset($_POST['threadModificationTrash'])) {
            $this->threadModificationTrash = \intval($_POST['threadModificationTrash']);
        }
        if (isset($_POST['threadModificationUndone'])) {
            $this->threadModificationUndone = \intval($_POST['threadModificationUndone']);
        }
        if (isset($_POST['threadModificationUnannounce'])) {
            $this->threadModificationUnannounce = \intval($_POST['threadModificationUnannounce']);
        }

        $this->postModerationClose = $this->postModerationDelete = $this->postModerationDisable = $this->postModerationEdit = 0;
        $this->postModerationEnable = $this->postModerationMerge = $this->postModerationMove = $this->postModerationOpen = 0;
        $this->postModerationRestore = $this->postModerationTrash = 0;
        if (isset($_POST['postModerationClose'])) {
            $this->postModerationClose = \intval($_POST['postModerationClose']);
        }
        if (isset($_POST['postModerationDelete'])) {
            $this->postModerationDelete = \intval($_POST['postModerationDelete']);
        }
        if (isset($_POST['postModerationDisable'])) {
            $this->postModerationDisable = \intval($_POST['postModerationDisable']);
        }
        if (isset($_POST['postModerationEdit'])) {
            $this->postModerationEdit = \intval($_POST['postModerationEdit']);
        }
        if (isset($_POST['postModerationEnable'])) {
            $this->postModerationEnable = \intval($_POST['postModerationEnable']);
        }
        if (isset($_POST['postModerationMerge'])) {
            $this->postModerationMerge = \intval($_POST['postModerationMerge']);
        }
        if (isset($_POST['postModerationMove'])) {
            $this->postModerationMove = \intval($_POST['postModerationMove']);
        }
        if (isset($_POST['postModerationOpen'])) {
            $this->postModerationOpen = \intval($_POST['postModerationOpen']);
        }
        if (isset($_POST['postModerationRestore'])) {
            $this->postModerationRestore = \intval($_POST['postModerationRestore']);
        }
        if (isset($_POST['postModerationTrash'])) {
            $this->postModerationTrash = \intval($_POST['postModerationTrash']);
        }

        $this->wbbThreadModerationData = [
            'threadModerationAuthorOnly' => $this->threadModerationAuthorOnly,
            'threadModerationChangeTopic' => $this->threadModerationChangeTopic,
            'threadModerationClose' => $this->threadModerationClose,
            'threadModerationDisable' => $this->threadModerationDisable,
            'threadModerationDone' => $this->threadModerationDone,
            'threadModerationEnable' => $this->threadModerationEnable,
            'threadModerationMerge' => $this->threadModerationMerge,
            'threadModerationMove' => $this->threadModerationMove,
            'threadModerationOpen' => $this->threadModerationOpen,
            'threadModerationRestore' => $this->threadModerationRestore,
            'threadModerationScrape' => $this->threadModerationScrape,
            'threadModerationSetAsAnnouncement' => $this->threadModerationSetAsAnnouncement,
            'threadModerationSetLabel' => $this->threadModerationSetLabel,
            'threadModerationSticky' => $this->threadModerationSticky,
            'threadModerationTrash' => $this->threadModerationTrash,
            'threadModerationUndone' => $this->threadModerationUndone,
            'threadModerationUnsetAsAnnouncement' => $this->threadModerationUnsetAsAnnouncement,
        ];

        $this->wbbThreadModificationData = [
            'threadModificationAuthorOnly' => $this->threadModificationAuthorOnly,
            'threadModificationBoardID' => $this->threadModificationBoardID,
            'threadModificationExecuter' => $this->threadModificationExecuter,
            'threadModificationExecuterID' => $this->threadModificationExecuterID,
            'threadModificationClose' => $this->threadModificationClose,
            'threadModificationDisable' => $this->threadModificationDisable,
            'threadModificationDone' => $this->threadModificationDone,
            'threadModificationEnable' => $this->threadModificationEnable,
            'threadModificationMove' => $this->threadModificationMove,
            'threadModificationOpen' => $this->threadModificationOpen,
            'threadModificationRestore' => $this->threadModificationRestore,
            'threadModificationScrape' => $this->threadModificationScrape,
            'threadModificationSetLabel' => $this->threadModificationSetLabel,
            'threadModificationSticky' => $this->threadModificationSticky,
            'threadModificationTrash' => $this->threadModificationTrash,
            'threadModificationUndone' => $this->threadModificationUndone,
            'threadModificationUnannounce' => $this->threadModificationUnannounce,
        ];

        $this->wbbPostModerationData = [
            'postModerationClose' => $this->postModerationClose,
            'postModerationDelete' => $this->postModerationDelete,
            'postModerationDisable' => $this->postModerationDisable,
            'postModerationEdit' => $this->postModerationEdit,
            'postModerationEnable' => $this->postModerationEnable,
            'postModerationMerge' => $this->postModerationMerge,
            'postModerationMove' => $this->postModerationMove,
            'postModerationOpen' => $this->postModerationOpen,
            'postModerationRestore' => $this->postModerationRestore,
            'postModerationTrash' => $this->postModerationTrash,
        ];

        // read conditions
        $this->wbbConditions = UzbotWbbConditionHandler::getInstance()->getGroupedObjectTypes();
        foreach ($this->wbbConditions as $conditions) {
            foreach ($conditions as $condition) {
                $condition->getProcessor()->readFormParameters();
            }
        }
    }

    /**
     * Handles the validate event.
     */
    protected function validate()
    {
        // Get type / notify data
        $type = UzbotType::getTypeByID($this->eventObj->typeID);
        $notify = UzbotNotify::getNotifyByID($this->eventObj->notifyID);

        // threadNotifyBoardID
        if ($notify->notifyTitle == 'thread') {
            if ($this->threadNotifyBoardID == 0) {
                throw new UserInputException('threadNotifyBoardID', 'notConfigured');
            }
            $board = BoardCache::getInstance()->getBoard($this->threadNotifyBoardID);
            if (!$board->boardID) {
                throw new UserInputException('threadNotifyBoardID', 'notValid');
            }
            if (!$board->isBoard()) {
                throw new UserInputException('threadNotifyBoardID', 'notValid');
            }
        }

        // postNotifyThreadID
        if ($notify->notifyTitle == 'post') {
            // threadID may be 0 with certain bots
            $allowed = ['wbb_postModeration', 'wbb_threadModification', 'wbb_threadModeration', 'wbb_threadNew', 'wbb_postCount', 'wbb_postChange', 'wbb_bestAnswer'];
            if (!$this->postNotifyThreadID) {
                if (!\in_array($type->typeTitle, $allowed)) {
                    throw new UserInputException('postNotifyThreadID', 'notValid');
                }
            } else {
                $thread = new Thread($this->postNotifyThreadID);
                if (!$thread->threadID) {
                    throw new UserInputException('postNotifyThreadID', 'notValid');
                }
            }
        }

        // need notify?
        if ($type->needNotify && !$notify->notifyID) {
            throw new UserInputException('notifyID', 'missing');
        }

        // need count for trigger values
        if ($type->needCount && $type->typeTitle == 'wbb_postCount') {
            if ($this->postCountAction == 'postTotal' || $this->postCountAction == 'postX') {
                $counts = ArrayUtil::trim(\explode(',', $this->eventObj->userCount));
                $counts = ArrayUtil::toIntegerArray($counts);

                if (!\count($counts)) {
                    throw new UserInputException('userCount', 'empty');
                }
            }
        }

        // thread new - wbb_threadNew
        if ($type->typeTitle == 'wbb_threadNew') {
            // board(s) must be selected
            if (!\count($this->threadNewBoardIDs)) {
                throw new UserInputException('threadNewBoardIDs', 'notConfigured');
            }
            // no thread notification in monitored boards on threadNew
            if ($notify->notifyTitle == 'thread') {
                if (\in_array($this->threadNotifyBoardID, $this->threadNewBoardIDs)) {
                    throw new UserInputException('threadNotifyBoardID', 'notAllowedOnThreadNew');
                }
            }
        }

        // action needing boardIDs
        $actions = ['wbb_postModeration', 'wbb_threadModeration', 'wbb_postChange', 'wbb_topPoster', 'wbb_bestAnswer'];
        if (\in_array($type->typeTitle, $actions)) {
            // board(s) must be selected
            if (!\count($this->uzbotBoardIDs)) {
                throw new UserInputException('uzbotBoardIDs', 'notConfigured');
            }
        }

        // wbb_threadModeration
        if ($type->typeTitle == 'wbb_threadModeration') {
            if (\array_sum($this->wbbThreadModerationData) - $this->wbbThreadModerationData['threadModerationAuthorOnly'] == 0) {
                throw new UserInputException('threadModerationAction', 'notConfigured');
            }
        }

        // wbb_threadModification
        if ($type->typeTitle == 'wbb_threadModification') {
            // unset change labels if no labels
            if (empty($this->eventObj->labelGroups) || empty($this->eventObj->availableLabels)) {
                $this->wbbThreadModificationData['threadModificationSetLabel'] = 0;
                $this->threadModificationSetLabel = 0;
            }

            if (\array_sum($this->wbbThreadModificationData) - $this->wbbThreadModificationData['threadModificationAuthorOnly'] == 0) {
                throw new UserInputException('threadModificationAction', 'notConfigured');
            }

            // if move, board must be selected and must exist
            if ($this->threadModificationMove) {
                $board = BoardCache::getInstance()->getBoard($this->threadModificationBoardID);
                if (!$board || !$board->isBoard()) {
                    throw new UserInputException('threadModificationBoardID', 'notValid');
                }
            }

            // executer must exist
            if (empty($this->threadModificationExecuter)) {
                throw new UserInputException('threadModificationExecuter');
            }
            $user = User::getUserByUsername($this->threadModificationExecuter);
            if (!$user->userID) {
                throw new UserInputException('threadModificationExecuter', 'invalid');
            }
            $this->threadModificationExecuterID = $user->userID;
            $this->wbbThreadModificationData['threadModificationExecuterID'] = $user->userID;
        }

        // wbb_postModeration
        if ($type->typeTitle == 'wbb_postModeration') {
            if (!\array_sum($this->wbbPostModerationData)) {
                throw new UserInputException('postModerationAction', 'notConfigured');
            }
        }

        // wbb_postChange
        if ($type->typeTitle == 'wbb_postChange') {
            if (!$this->postChangeUpdate && !$this->postChangeDelete) {
                throw new UserInputException('postChangeAction', 'notConfigured');
            }
        }
    }

    /**
     * Handles the save event.
     */
    protected function save()
    {
        // wbbThreadNotifyData
        $wbbThreadData = [
            'threadNotifyBoardID' => $this->threadNotifyBoardID,
            'threadNotifyDelayed' => $this->threadNotifyDelayed,
            'threadNotifyIsClosed' => $this->threadNotifyIsClosed,
            'threadNotifyIsDisabled' => $this->threadNotifyIsDisabled,
            'threadNotifyIsDone' => $this->threadNotifyIsDone,
            'threadNotifyIsSticky' => $this->threadNotifyIsSticky,
        ];

        $wbbPostData = [
            'postNotifyThreadID' => $this->postNotifyThreadID,
            'postNotifyIsClosed' => $this->postNotifyIsClosed,
            'postNotifyIsDisabled' => $this->postNotifyIsDisabled,
        ];

        $this->eventObj->additionalFields = \array_merge($this->eventObj->additionalFields, [
            'wbbThreadData' => \serialize($wbbThreadData),
            'wbbPostData' => \serialize($wbbPostData),
            'wbbPostModerationData' => \serialize($this->wbbPostModerationData),
            'wbbThreadModerationData' => \serialize($this->wbbThreadModerationData),
            'wbbThreadModificationData' => \serialize($this->wbbThreadModificationData),
            'postCountAction' => $this->postCountAction,
            'topPosterCount' => $this->topPosterCount,
            'topPosterInterval' => $this->topPosterInterval,
            'topPosterNext' => 0,
            'threadNewBoardIDs' => \serialize($this->threadNewBoardIDs),
            'uzbotBoardIDs' => \serialize($this->uzbotBoardIDs),
            'postIsOfficial' => $this->postIsOfficial,
            'threadIsOfficial' => $this->threadIsOfficial,
            'postChangeUpdate' => $this->postChangeUpdate,
            'postChangeDelete' => $this->postChangeDelete,
        ]);
    }

    /**
     * Handles the saved event.
     */
    protected function saved()
    {
        // transform conditions array into one-dimensional array and save
        $conditions = [];
        foreach ($this->wbbConditions as $groupedObjectTypes) {
            $conditions = \array_merge($conditions, $groupedObjectTypes);
        }

        $oldConditions = ConditionHandler::getInstance()->getConditions('com.uz.wcf.bot.condition.wbb', $this->eventObj->botID);
        ConditionHandler::getInstance()->updateConditions($this->eventObj->botID, $oldConditions, $conditions);
    }
}
