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
use wcf\data\user\User;
use wcf\data\user\UserList;
use wcf\data\uzbot\log\UzbotLogEditor;
use wcf\system\background\BackgroundQueueHandler;
use wcf\system\background\uzbot\NotifyScheduleBackgroundJob;
use wcf\system\cache\builder\UzbotValidBotCacheBuilder;
use wcf\system\event\listener\IParameterizedEventListener;
use wcf\system\language\LanguageFactory;
use wcf\system\message\quote\MessageQuoteManager;
use wcf\system\WCF;

/**
 * Listen to thread actions for Bot
 */
class UzbotThreadActionListener implements IParameterizedEventListener
{
    /**
     * @inheritdoc
     */
    public function execute($eventObj, $className, $eventName, array &$parameters)
    {
        // check module
        if (!MODULE_UZBOT) {
            return;
        }

        $action = $eventObj->getActionName();
        $defaultLanguage = LanguageFactory::getInstance()->getLanguage(LanguageFactory::getInstance()->getDefaultLanguageID());

        // Thread publication
        if ($action == 'triggerPublication') {
            // Read all active, valid bots, abort if none
            $bots = UzbotValidBotCacheBuilder::getInstance()->getData(['typeDes' => 'wbb_threadNew']);
            if (!\count($bots)) {
                return;
            }

            // get threads
            $threads = $eventObj->getObjects();
            if (!\count($threads)) {
                return;
            }

            foreach ($threads as $editor) {
                $thread = $editor->getDecoratedObject();

                // not if created by bot
                if ($thread->isUzbot) {
                    continue;
                }

                foreach ($bots as $bot) {
                    $affectedUserIDs = $countToUserID = $placeholders = [];
                    $count = 1;

                    //monitored boards
                    if (!\in_array($thread->boardID, \unserialize($bot->threadNewBoardIDs))) {
                        continue;
                    }

                    // user conditions
                    $conditions = $bot->getUserConditions();
                    if (\count($conditions)) {
                        if (!$thread->userID) {
                            continue;
                        } else {
                            $userList = new UserList();
                            $userList->getConditionBuilder()->add('user_table.userID = ?', [$thread->userID]);
                            foreach ($conditions as $condition) {
                                $condition->getObjectType()->getProcessor()->addUserCondition($condition, $userList);
                            }
                            $userList->readObjects();
                            if (!\count($userList->getObjects())) {
                                continue;
                            }
                        }
                    }

                    // affected user
                    $user = null;
                    if ($thread->userID) {
                        $user = new User($thread->userID);
                        if (!$user->userID) {
                            $user = null;
                        }
                    }

                    // save threadID for post in affected thread
                    $bot->threadID = $thread->threadID;

                    if ($user) {
                        $affectedUserIDs[] = $user->userID;
                        $countToUserID[$user->userID] = 1;
                    } else {
                        $placeholders['user-email'] = $placeholders['user-groups'] = 'wcf.user.guest';
                        $placeholders['user-name'] = $placeholders['user-profile'] = $placeholders['@user-profile'] = 'wcf.user.guest';
                        $placeholders['user-age'] = 'x';
                        $placeholders['user-id'] = 0;
                    }

                    $placeholders['board-id'] = $thread->boardID;
                    $placeholders['board-name'] = $thread->getBoard()->title;
                    $placeholders['count'] = 1;
                    $placeholders['count-user'] = $user ? $user->wbbPosts : 0;
                    $placeholders['thread-link'] = $thread->getLink();
                    $placeholders['thread-subject'] = $thread->getTitle();
                    $placeholders['thread-text'] = $thread->getFirstPost()->getMessage();
                    if ($user) {
                        $placeholders['translate'] = ['board-name'];
                    } else {
                        $placeholders['translate'] = ['board-name', 'user-email', 'user-groups', 'user-name', 'user-profile', '@user-profile', 'user-age'];
                    }

                    // get quote
                    $post = $thread->getFirstPost();
                    $quote = $this->getQuote($post);
                    $placeholders['quote'] = $quote;

                    // get options; >= 5.2.0
                    $optionValues = [];
                    $sql = "SELECT    thread_form_option.optionTitle, thread_form_option_value.optionValue
                            FROM    wbb" . WCF_N . "_thread_form_option_value thread_form_option_value
                            LEFT JOIN    wbb" . WCF_N . "_thread_form_option thread_form_option
                                ON        (thread_form_option_value.optionID = thread_form_option.optionID)
                            WHERE    postID = ?
                            ORDER BY thread_form_option.showOrder";
                    $statement = WCF::getDB()->prepareStatement($sql);
                    $statement->execute([$post->postID]);
                    $optionValues = $statement->fetchMap('optionTitle', 'optionValue');

                    $placeholders['options'] = $optionValues;

                    // log action
                    if ($bot->enableLog) {
                        // userIDs string
                        if (\count($affectedUserIDs)) {
                            $userIDs = \implode(', ', $affectedUserIDs);
                        } else {
                            $userIDs = $defaultLanguage->get('wcf.user.guest');
                        }

                        if (!$bot->testMode) {
                            UzbotLogEditor::create([
                                'bot' => $bot,
                                'count' => 1,
                                'additionalData' => $defaultLanguage->getDynamicVariable('wcf.acp.uzbot.log.user.affected', [
                                    'total' => 1,
                                    'userIDs' => $userIDs,
                                ]),
                            ]);
                        } else {
                            $result = $defaultLanguage->getDynamicVariable('wcf.acp.uzbot.log.test', [
                                'objects' => 1,
                                'users' => \count($affectedUserIDs),
                                'userIDs' => $userIDs,
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

        // best answer
        if ($action == 'markAsBestAnswer') {
            // Read all active, valid activity bots, abort if none
            $bots = UzbotValidBotCacheBuilder::getInstance()->getData(['typeDes' => 'wbb_bestAnswer']);

            if (\count($bots)) {
                // data
                $params = $eventObj->getParameters();
                $thread = $eventObj->thread;
                $board = $thread->getBoard();
                $post = new Post($params['postID']);
                if (!$post->postID) {
                    return;
                }
                $quote = $this->getQuote($post);
                $user = null;
                if ($post->userID) {
                    $user = new User($post->userID);
                    if (!$user->userID) {
                        $user = null;
                    }
                }

                foreach ($bots as $bot) {
                    $affectedUserIDs = $countToUserID = $placeholders = [];

                    // must be in monitored boards
                    if (!\in_array($thread->boardID, \unserialize($bot->uzbotBoardIDs))) {
                        continue;
                    }

                    // user
                    if ($user) {
                        $affectedUserIDs[] = $user->userID;
                        $countToUserID[$user->userID] = $user->wbbPosts;
                    } else {
                        $placeholders['user-email'] = $placeholders['user-groups'] = 'wcf.user.guest';
                        $placeholders['user-name'] = $placeholders['user-profile'] = $placeholders['@user-profile'] = 'wcf.user.guest';
                        $placeholders['user-age'] = 'x';
                        $placeholders['user-id'] = 0;
                    }

                    // set placeholders
                    $placeholders['board-id'] = $board->boardID;
                    $placeholders['board-name'] = $board->title;
                    $placeholders['count'] = 1;
                    $placeholders['count-user'] = $user ? $user->wbbPosts : 0;
                    $placeholders['post-id'] = $post->postID;
                    $placeholders['post-link'] = $post->getLink();
                    $placeholders['post-subject'] = $post->getTitle();
                    $placeholders['post-text'] = $post->getMessage();
                    $placeholders['quote'] = $quote;
                    $placeholders['thread-link'] = $thread->getLink();
                    $placeholders['thread-subject'] = $thread->getTitle();
                    if ($user) {
                        $placeholders['translate'] = ['board-name'];
                    } else {
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
