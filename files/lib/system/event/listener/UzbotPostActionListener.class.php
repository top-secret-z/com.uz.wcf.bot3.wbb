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

use wcf\data\user\User;
use wcf\data\user\UserList;
use wcf\data\uzbot\log\UzbotLogEditor;
use wcf\data\uzbot\top\UzbotTop;
use wcf\data\uzbot\top\UzbotTopEditor;
use wcf\system\background\BackgroundQueueHandler;
use wcf\system\background\uzbot\NotifyScheduleBackgroundJob;
use wcf\system\cache\builder\UzbotValidBotCacheBuilder;
use wcf\system\event\listener\IParameterizedEventListener;
use wcf\system\language\LanguageFactory;
use wcf\system\message\quote\MessageQuoteManager;
use wcf\system\WCF;

/**
 * Listen to WBB Post actions for Bot
 */
class UzbotPostActionListener implements IParameterizedEventListener
{
    /**
     * @inheritDoc
     */
    public function execute($eventObj, $className, $eventName, array &$parameters)
    {
        if (!MODULE_UZBOT) {
            return;
        }

        $action = $eventObj->getActionName();

        $defaultLanguage = LanguageFactory::getInstance()->getLanguage(LanguageFactory::getInstance()->getDefaultLanguageID());

        // on new posts get user post count, top poster and total post count
        if ($action == 'triggerPublication') {
            // Read all active, valid bots, abort if none
            $bots = UzbotValidBotCacheBuilder::getInstance()->getData(['typeDes' => 'wbb_postCount']);
            if (!\count($bots)) {
                return;
            }

            // get posts
            $posts = $eventObj->getObjects();
            if (!\count($posts)) {
                return;
            }

            foreach ($posts as $editor) {
                $post = $editor->getDecoratedObject();

                // check later, whether post is counted
                $thread = $post->getThread();
                if (!$thread->threadID) {
                    return;
                }
                $board = $thread->getBoard();
                if (!$board->boardID) {
                    return;
                }

                // top poster
                $top = new UzbotTop(1);
                $topUser = new User($top->post);

                // total post count
                $sql = "SELECT COUNT(*) AS count
                        FROM    wbb" . WCF_N . "_post
                        WHERE    isDeleted = ?";
                $statement = WCF::getDB()->prepareStatement($sql);
                $statement->execute([0]);
                $countTotal = $statement->fetchColumn();

                $user = new User($post->userID);

                foreach ($bots as $bot) {
                    $affectedUserIDs = $countToUserID = $placeholders = [];
                    $count = 1;

                    // only users if not postTop
                    if ($bot->postCountAction != 'postTotal' && !$user->userID) {
                        continue;
                    }

                    // user condition relevant on postX only
                    if ($bot->postCountAction == 'postX') {
                        $conditions = $bot->getUserConditions();

                        if (\count($conditions)) {
                            $userList = new UserList();
                            $userList->getConditionBuilder()->add('user_table.userID = ?', [$user->userID]);
                            foreach ($conditions as $condition) {
                                $condition->getObjectType()->getProcessor()->addUserCondition($condition, $userList);
                            }
                            $userList->readObjects();
                            if (!\count($userList->getObjects())) {
                                continue;
                            }
                        }
                    }

                    // only on count match or new top poster
                    $counts = \explode(',', $bot->userCount);
                    $hit = false;

                    switch ($bot->postCountAction) {
                        case 'postTotal':
                            if (\in_array($countTotal, $counts)) {
                                $hit = true;
                            }
                            break;

                        case 'postX':
                            if ($board->countUserPosts) {
                                if (\in_array($user->wbbPosts, $counts)) {
                                    $hit = true;
                                }
                            }
                            break;

                        case 'postTop':
                            if ($board->countUserPosts) {
                                if ($user->wbbPosts > $topUser->wbbPosts && $user->userID != $topUser->userID) {
                                    $hit = true;
                                    if (!$bot->testMode) {
                                        $editor = new UzbotTopEditor($top);
                                        $editor->update(['post' => $user->userID]);
                                    }
                                }
                            }
                            break;
                    }

                    if ($hit) {
                        $affectedUserIDs = $countToUserID = $placeholders = [];

                        if ($user->userID) {
                            $affectedUserIDs[] = $user->userID;
                            $countToUserID[$user->userID] = $user->wbbPosts;
                        }

                        // post, thread etc. data
                        // $thread = $post->getThread(); $board = $thread->getBoard(); see above
                        $quote = $this->getQuote($post);

                        // set placeholders
                        $placeholders['board-id'] = $board->boardID;
                        $placeholders['board-name'] = $board->title;
                        $placeholders['count'] = $countTotal;
                        $placeholders['count-user'] = $user->userID ? $user->wbbPosts : 0;
                        $placeholders['post-id'] = $post->postID;
                        $placeholders['post-link'] = $post->getLink();
                        $placeholders['post-subject'] = $post->getTitle();
                        $placeholders['post-text'] = $post->getMessage();
                        $placeholders['quote'] = $quote;
                        $placeholders['thread-link'] = $thread->getLink();
                        $placeholders['thread-subject'] = $thread->getTitle();
                        $placeholders['translate'] = ['board-name'];

                        // add guest placeholders
                        if (!$user->userID) {
                            $placeholders['user-email'] = $placeholders['user-groups'] = 'wcf.user.guest';
                            $placeholders['user-name'] = $placeholders['user-profile'] = $placeholders['@user-profile'] = 'wcf.user.guest';
                            $placeholders['user-age'] = 'x';
                            $placeholders['user-id'] = 0;

                            $placeholders['translate'] = ['board-name', 'user-email', 'user-groups', 'user-name', 'user-profile', '@user-profile', 'user-age'];
                        }

                        // save threadID for post in affected thread
                        $bot->threadID = $thread->threadID;

                        // log action
                        if ($bot->enableLog) {
                            if (!$bot->testMode) {
                                UzbotLogEditor::create([
                                    'bot' => $bot,
                                    'count' => 1,
                                    'additionalData' => $defaultLanguage->getDynamicVariable('wcf.acp.uzbot.log.user.affected', [
                                        'total' => 1,
                                        'userIDs' => \implode(', ', $affectedUserIDs),
                                    ]),
                                ]);
                            }
                        } else {
                            $result = $defaultLanguage->getDynamicVariable('wcf.acp.uzbot.log.test', [
                                'objects' => 1,
                                'users' => \count($affectedUserIDs),
                                'userIDs' => \implode(', ', $affectedUserIDs),
                            ]);
                            if (\mb_strlen($result) > 64000) {
                                $result = \mb_substr($result, 0, 64000) . ' ...';
                            }
                            UzbotLogEditor::create([
                                'bot' => $bot,
                                'count' => 1,
                                'testMode' => 1,
                                'additionalData' => \serialize(['', '', $result]),
                            ]);
                        }

                        // check for and prepare notification
                        $notify = $bot->checkNotify(true, true);
                        if ($notify === null) {
                            continue;
                        }

                        // send to scheduler
                        $data = [
                            'bot' => $bot,
                            'placeholders' => $placeholders,
                            'affectedUserIDs' => $affectedUserIDs,
                            'countToUserID' => $countToUserID,
                        ];

                        $job = new NotifyScheduleBackgroundJob($data);
                        BackgroundQueueHandler::getInstance()->performJob($job);
                    }
                }
            }
        }

        // post change by user himself
        if ($action == 'update' || $action == 'trash') {
            // Read all active, valid bots, abort if none
            $bots = UzbotValidBotCacheBuilder::getInstance()->getData(['typeDes' => 'wbb_postChange']);
            if (!\count($bots)) {
                return;
            }

            // get posts / data
            $posts = $eventObj->getObjects();
            if (!\count($posts)) {
                return;
            }

            $params = $eventObj->getParameters();

            foreach ($posts as $editor) {
                $post = $editor->getDecoratedObject();

                if ($post->userID && WCF::getUser()->userID == $post->userID) {
                    //preset data
                    $user = WCF::getUser();
                    $thread = $post->getThread();

                    // get reason
                    $reason = '';
                    if (isset($params['reason'])) {
                        $reason = $params['reason'];
                    } elseif (isset($params['data']['editReason'])) {
                        $reason = $params['data']['editReason'];
                    }

                    foreach ($bots as $bot) {
                        // check type of change
                        if ($action == 'update' && !$bot->postChangeUpdate) {
                            continue;
                        }
                        if ($action == 'trash' && !$bot->postChangeDelete) {
                            continue;
                        }

                        $affectedUserIDs = $countToUserID = $placeholders = [];
                        $count = 1;

                        // must be in monitored boards
                        if (!\in_array($thread->boardID, \unserialize($bot->uzbotBoardIDs))) {
                            continue;
                        }

                        $conditions = $bot->getUserConditions();
                        if (\count($conditions)) {
                            $userList = new UserList();
                            $userList->getConditionBuilder()->add('user_table.userID = ?', [$user->userID]);
                            foreach ($conditions as $condition) {
                                $condition->getObjectType()->getProcessor()->addUserCondition($condition, $userList);
                            }
                            $userList->readObjects();
                            if (!\count($userList->getObjects())) {
                                continue;
                            }
                        }

                        // found one
                        $affectedUserIDs[] = $user->userID;
                        $countToUserID[$user->userID] = $user->wbbPosts;

                        // post, thread and etc. data
                        $thread = $post->getThread();
                        $board = $thread->getBoard();
                        $quote = $this->getQuote($post);

                        // set placeholders
                        $placeholders['action'] = $action == 'update' ? 'wcf.acp.uzbot.wbb.action.changed' : 'wcf.acp.uzbot.wbb.action.deleted';
                        $placeholders['board-id'] = $board->boardID;
                        $placeholders['board-name'] = $board->title;
                        $placeholders['count'] = 1;
                        $placeholders['count-user'] = 1;
                        $placeholders['post-id'] = $post->postID;
                        $placeholders['post-link'] = $post->getLink();
                        $placeholders['post-subject'] = $post->getTitle();
                        $placeholders['post-text'] = $post->getMessage();
                        $placeholders['quote'] = $quote;
                        $placeholders['reason'] = $reason;
                        $placeholders['thread-link'] = $thread->getLink();
                        $placeholders['thread-subject'] = $thread->getTitle();
                        $placeholders['translate'] = ['board-name', 'action'];

                        // save threadID for post in affected thread
                        $bot->threadID = $thread->threadID;

                        // log action
                        if ($bot->enableLog) {
                            if (!$bot->testMode) {
                                UzbotLogEditor::create([
                                    'bot' => $bot,
                                    'count' => 1,
                                    'additionalData' => $defaultLanguage->getDynamicVariable('wcf.acp.uzbot.log.user.affected', [
                                        'total' => 1,
                                        'userIDs' => \implode(', ', $affectedUserIDs),
                                    ]),
                                ]);
                            } else {
                                $result = $defaultLanguage->getDynamicVariable('wcf.acp.uzbot.log.test', [
                                    'objects' => 1,
                                    'users' => \count($affectedUserIDs),
                                    'userIDs' => \implode(', ', $affectedUserIDs),
                                ]);
                                if (\mb_strlen($result) > 64000) {
                                    $result = \mb_substr($result, 0, 64000) . ' ...';
                                }
                                UzbotLogEditor::create([
                                    'bot' => $bot,
                                    'count' => 1,
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

                        // send to scheduler
                        $data = [
                            'bot' => $bot,
                            'placeholders' => $placeholders,
                            'affectedUserIDs' => $affectedUserIDs,
                            'countToUserID' => $countToUserID,
                        ];

                        $job = new NotifyScheduleBackgroundJob($data);
                        BackgroundQueueHandler::getInstance()->performJob($job);
                    }
                }
            }
        }
    }

    /**
     * Create qoute from post / thread
     */
    protected function getQuote($post)
    {
        $quoteID = MessageQuoteManager::getInstance()->addQuote(
            'com.woltlab.wbb.post',
            $post->threadID,
            $post->postID,
            $post->getExcerpt(),
            $post->getMessage()
        );

        if ($quoteID === false) {
            $removeQuoteID = MessageQuoteManager::getInstance()->getQuoteID(
                'com.woltlab.wbb.post',
                $post->postID,
                $post->getExcerpt(),
                $post->getMessage()
            );
            MessageQuoteManager::getInstance()->removeQuote($removeQuoteID);
        }

        $returnValues = [
            'count' => MessageQuoteManager::getInstance()->countQuotes(),
            'fullQuoteMessageIDs' => MessageQuoteManager::getInstance()->getFullQuoteObjectIDs(['com.woltlab.wbb.post']),
        ];
        if ($quoteID) {
            $returnValues['renderedQuote'] = MessageQuoteManager::getInstance()->getQuoteComponents($quoteID);

            $username = \str_replace(["\\", "'"], ["\\\\", "\\'"], $returnValues['renderedQuote']['username']);
            $link = \str_replace(["\\", "'"], ["\\\\", "\\'"], $returnValues['renderedQuote']['link']);
            $message = $returnValues['renderedQuote']['text'];

            // build quote and replace
            $quote = '<woltlab-quote data-author="' . $username . '" data-link="' . $link . '">';
            $quote .= $message;
            $quote .= '</woltlab-quote>';
        } else {
            $quote = '';
        }

        return $quote;
    }
}
