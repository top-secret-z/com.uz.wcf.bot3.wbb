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

use wbb\data\post\Post;
use wbb\data\thread\Thread;
use wcf\data\object\type\ObjectTypeCache;
use wcf\data\user\User;
use wcf\data\user\UserList;
use wcf\data\uzbot\log\UzbotLogEditor;
use wcf\system\background\BackgroundQueueHandler;
use wcf\system\background\uzbot\NotifyScheduleBackgroundJob;
use wcf\system\cache\builder\UzbotValidBotCacheBuilder;
use wcf\system\event\listener\IParameterizedEventListener;
use wcf\system\language\LanguageFactory;
use wcf\system\WCF;
use wcf\util\StringUtil;

/**
 * Listen to post moderations for Bot
 */
class UzbotPostModerationListener implements IParameterizedEventListener
{
    /**
     * @inheritDoc
     */
    public function execute($eventObj, $className, $eventName, array &$parameters)
    {
        // check module
        if (!MODULE_UZBOT) {
            return;
        }

        // only action create and user
        if ($eventObj->getActionName() != 'create') {
            return;
        }
        if (!WCF::getUser()->userID) {
            return;
        }

        // only post modification
        $objectTypeID = ObjectTypeCache::getInstance()->getObjectTypeIDByName('com.woltlab.wcf.modifiableContent', 'com.woltlab.wbb.post');
        $params = $eventObj->getParameters();
        if ($params['data']['objectTypeID'] != $objectTypeID) {
            return;
        }

        // only if bots
        $bots = UzbotValidBotCacheBuilder::getInstance()->getData(['typeDes' => 'wbb_postModeration']);
        if (!\count($bots)) {
            return;
        }

        // preset data
        $defaultLanguage = LanguageFactory::getInstance()->getLanguage(LanguageFactory::getInstance()->getDefaultLanguageID());
        $postID = $params['data']['objectID'];
        $threadID = $params['data']['parentObjectID'];

        $action = $params['data']['action'];
        $moderatorID = $params['data']['userID'];
        $additional = [];
        if (isset($params['data']['additionalData'])) {
            $additional = \unserialize($params['data']['additionalData']);
        }

        // allow 'delete'
        $validActions = ['close', 'delete', 'disable', 'edit', 'enable', 'merge', 'move', 'open', 'restore', 'trash'];
        if (!\in_array($action, $validActions)) {
            return;
        }

        // only moderation
        $post = new Post($postID);    // may be null (delete)
        $thread = new Thread($threadID);

        if ($post && $post->userID == $moderatorID) {
            return;
        }

        // Step through bots
        foreach ($bots as $bot) {
            $affectedUserIDs = $countToUserID = $placeholders = [];
            $count = 0;

            // check action
            if (!\stripos($bot->wbbPostModerationData, $action)) {
                continue;
            }    // 0/false doesn't matter

            // check board
            if (!\in_array($thread->boardID, \unserialize($bot->uzbotBoardIDs))) {
                continue;
            }

            // check action
            $moderations = \unserialize($bot->wbbPostModerationData);
            $act = 0;
            foreach ($moderations as $key => $value) {
                if (0 == \strcasecmp($key, 'postModeration' . $action) && $value == 1) {
                    $act = 1;
                    break;
                }
            }

            if (!$act) {
                continue;
            }

            // get affected users
            if ($bot->changeAffected) {
                $affectedUserIDs[] = $moderatorID;
                $countToUserID[$moderatorID] = 1;
                $count = 1;
            } else {
                if ($action != 'delete') {
                    $affectedUserIDs[] = $post->userID;
                    $countToUserID[$post->userID] = 1;
                    $count = 1;
                } else {
                    // leave if no user
                    if (isset($additional['username'])) {
                        $user = User::getUserByUsername($additional['username']);
                        if (!$user->userID) {
                            continue;
                        }

                        $affectedUserIDs[] = $user->userID;
                        $countToUserID[$user->userID] = 1;
                        $count = 1;
                    } else {
                        continue;
                    }
                }
            }

            // check users
            $conditions = $bot->getUserConditions();
            if (\count($conditions)) {
                $userList = new UserList();
                $userList->getConditionBuilder()->add('user_table.userID IN (?)', [$affectedUserIDs]);
                foreach ($conditions as $condition) {
                    $condition->getObjectType()->getProcessor()->addUserCondition($condition, $userList);
                }
                $userList->readObjects();
                if (!\count($userList->getObjects())) {
                    continue;
                }
            }

            // found users, board and a valid action, get data
            // log action
            if ($bot->enableLog) {
                if (!$bot->testMode) {
                    UzbotLogEditor::create([
                        'bot' => $bot,
                        'count' => $count,
                        'additionalData' => $defaultLanguage->getDynamicVariable('wcf.acp.uzbot.log.user.affected', [
                            'total' => \count($affectedUserIDs),
                            'userIDs' => \implode(', ', $affectedUserIDs),
                        ]),
                    ]);
                } else {
                    $result = $defaultLanguage->getDynamicVariable('wcf.acp.uzbot.log.test', [
                        'objects' => $count,
                        'users' => \count($affectedUserIDs),
                        'userIDs' => \implode(', ', $affectedUserIDs),
                    ]);
                    if (\mb_strlen($result) > 64000) {
                        $result = \mb_substr($result, 0, 64000) . ' ...';
                    }
                    UzbotLogEditor::create([
                        'bot' => $bot,
                        'count' => $count,
                        'testMode' => 1,
                        'additionalData' => \serialize(['', '', $result]),
                    ]);
                }
            }

            // check for and prepare notification
            $notify = $bot->checkNotify(true, true);
            if ($notify === null) {
                continue;
            }

            $bot->threadID = $thread->threadID;
            $board = $thread->getBoard();
            $moderator = WCF::getUser();

            $placeholders['board-id'] = $thread->boardID;
            $placeholders['board-name'] = $thread->getBoard()->title;
            $placeholders['count'] = \count($affectedUserIDs);

            $placeholders['moderation-action'] = 'wcf.uzbot.wbb.moderation.post.' . $action;
            $placeholders['moderation-reason'] = '';
            if (isset($additional['reason'])) {
                $placeholders['moderation-reason'] = $additional['reason'];
            }
            $placeholders['moderator-id'] = $moderator->userID;
            $placeholders['moderator-link'] = $moderator->getLink();
            $placeholders['moderator-link2'] = StringUtil::getAnchorTag($moderator->getLink(), $moderator->username);
            $placeholders['moderator-name'] = $moderator->username;

            $placeholders['thread-link'] = $thread->getLink();
            $placeholders['thread-subject'] = $thread->getTitle();
            if ($action != 'delete') {
                $placeholders['thread-text'] = $thread->getFirstPost()->getMessage();
            } else {
                // cancel placeholder. Might be only post; cached.
                $placeholders['thread-text'] = 'thread-text';
            }
            $placeholders['translate'] = ['board-name', 'moderation-action'];

            if ($action != 'delete') {
                $placeholders['post-link'] = $post->getLink();
                $placeholders['post-subject'] = $post->getTitle();
                $placeholders['post-text'] = $post->getMessage();
            } else {
                $placeholders['post-link'] = $placeholders['post-subject'] = $placeholders['post-text'] = 'wcf.uzbot.wbb.moderation.post.deleted';
                $placeholders['translate'][] = 'post-link';
                $placeholders['translate'][] = 'post-subject';
                $placeholders['translate'][] = 'post-text';
            }

            // test mode
            $testUserIDs = $testToUserIDs = [];
            $testUserIDs[] = $affectedUserIDs[0];
            $testToUserIDs[$affectedUserIDs[0]] = $countToUserID[$affectedUserIDs[0]];

            // send to scheduler
            $data = [
                'bot' => $bot,
                'placeholders' => $placeholders,
                'affectedUserIDs' => !$bot->testMode ? $affectedUserIDs : $testUserIDs,
                'countToUserID' => !$bot->testMode ? $countToUserID : $testToUserIDs,
            ];

            $job = new NotifyScheduleBackgroundJob($data);
            BackgroundQueueHandler::getInstance()->performJob($job);
        }
    }
}
