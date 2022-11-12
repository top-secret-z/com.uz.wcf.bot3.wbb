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
namespace wbb\system\cronjob;

use wcf\data\cronjob\Cronjob;
use wcf\data\user\UserList;
use wcf\data\uzbot\log\UzbotLogEditor;
use wcf\data\uzbot\UzbotEditor;
use wcf\system\background\BackgroundQueueHandler;
use wcf\system\background\uzbot\NotifyScheduleBackgroundJob;
use wcf\system\cache\builder\UzbotValidBotCacheBuilder;
use wcf\system\cronjob\AbstractCronjob;
use wcf\system\database\util\PreparedStatementConditionBuilder;
use wcf\system\language\LanguageFactory;
use wcf\system\WCF;

/**
 * Cronjob for Top Poster for Bot.
 */
class UzbotPostTopPosterCronjob extends AbstractCronjob
{
    /**
     * @see    wcf\system\cronjob\ICronjob::execute()
     */
    public function execute(Cronjob $cronjob)
    {
        parent::execute($cronjob);

        if (!MODULE_UZBOT) {
            return;
        }

        // Read all active, valid activity bots, abort if none
        $bots = UzbotValidBotCacheBuilder::getInstance()->getData(['typeDes' => 'wbb_topPoster']);
        if (empty($bots)) {
            return;
        }

        $defaultLanguage = LanguageFactory::getInstance()->getLanguage(LanguageFactory::getInstance()->getDefaultLanguageID());

        // Step through all bots and get top poster
        foreach ($bots as $bot) {
            // set first next if 0
            if (!$bot->topPosterNext) {
                $month = \date('n');
                $year = \date('Y');

                switch ($bot->topPosterInterval) {
                    case 1:
                        $next = \strtotime('next Monday');
                        break;
                    case 2:
                        $next = \gmmktime(0, 0, 0, $month != 12 ? $month + 1 : 1, 1, $month != 12 ? $year : $year + 1);
                        break;
                    case 3:
                        if ($month >= 10) {
                            $next = \gmmktime(0, 0, 0, 1, 1, $year + 1);
                        } elseif ($month >= 7) {
                            $next = \gmmktime(0, 0, 0, 10, 1, $year);
                        } elseif ($month >= 4) {
                            $next = \gmmktime(0, 0, 0, 7, 1, $year);
                        } else {
                            $next = \gmmktime(0, 0, 0, 4, 1, $year);
                        }
                        break;
                }

                $editor = new UzbotEditor($bot);
                $editor->update(['topPosterNext' => $next]);
                UzbotEditor::resetCache();

                $bot->topPosterNext = $next;
            }

            // leave if time does not match, unless test mode
            if (!$bot->testMode) {
                if ($bot->topPosterNext > TIME_NOW) {
                    continue;
                }
            }

            // must execute
            $end = $bot->topPosterNext;
            $month = \date('n');
            $year = \date('Y');

            switch ($bot->topPosterInterval) {
                case 1:
                    $start = $end - 7 * 86400;
                    $next = $end + 7 * 86400;
                    break;
                case 2:
                    $start = \gmmktime(0, 0, 0, $month > 1 ? $month - 1 : 12, 1, $month > 1 ? $year : $year - 1);
                    $next = \gmmktime(0, 0, 0, $month != 12 ? $month + 1 : 1, 1, $month != 12 ? $year : $year + 1);
                    break;
                case 3:
                    $start = \gmmktime(0, 0, 0, $month > 3 ? $month - 3 : 10, 1, $month > 3 ? $year : $year - 1);
                    if ($month >= 10) {
                        $next = \gmmktime(0, 0, 0, 1, 1, $year + 1);
                    } elseif ($month >= 7) {
                        $next = \gmmktime(0, 0, 0, 10, 1, $year);
                    } elseif ($month >= 4) {
                        $next = \gmmktime(0, 0, 0, 7, 1, $year);
                    } else {
                        $next = \gmmktime(0, 0, 0, 4, 1, $year);
                    }
                    break;
            }

            // update bot, unless test mode
            if (!$bot->testMode) {
                $editor = new UzbotEditor($bot);
                $editor->update(['topPosterNext' => $next]);
                UzbotEditor::resetCache();
            }

            // get top poster
            $affectedUserIDs = $countToUserID = $placeholders = $userIDs = [];
            $rank = 0;

            $boardIDs = \unserialize($bot->uzbotBoardIDs);
            $conditions = $bot->getUserConditions();
            if (\count($conditions)) {
                $userList = new UserList();
                foreach ($conditions as $condition) {
                    $condition->getObjectType()->getProcessor()->addUserCondition($condition, $userList);
                }
                $userList->readObjects();
                $temp = $userList->getObjects();
                if (\count($temp)) {
                    foreach ($temp as $user) {
                        $userIDs[] = $user->userID;
                    }
                }
            }

            $conditionBuilder = new PreparedStatementConditionBuilder();
            $conditionBuilder->add('board.boardID IN (?)', [$boardIDs]);
            if (\count($userIDs)) {
                $conditionBuilder->add('post.userID IN (?)', [$userIDs]);
            } else {
                $conditionBuilder->add('post.userID > ?', [0]);
            }
            $conditionBuilder->add('post.isDeleted = ?', [0]);
            $conditionBuilder->add('post.time > ?', [$start]);
            $conditionBuilder->add('post.time < ?', [$end]);

            $sql = "SELECT         post.userID as topID, COUNT(*) as count
                    FROM        wbb" . WCF_N . "_post post
                    LEFT JOIN     wbb" . WCF_N . "_thread thread ON (thread.threadID = post.threadID)
                    LEFT JOIN     wbb" . WCF_N . "_board board ON (board.boardID = thread.boardID)
                    " . $conditionBuilder . "
                    GROUP BY    topID
                    ORDER BY    count DESC";

            $statement = WCF::getDB()->prepareStatement($sql, $bot->topPosterCount);
            $statement->execute($conditionBuilder->getParameters());
            while ($row = $statement->fetchArray()) {
                $rank++;
                $affectedUserIDs[] = $row['topID'];
                $countToUserID[$row['topID']] = $row['count'];
                $placeholders['ranks'][$row['topID']] = $rank;
            }

            // data
            if ($bot->enableLog) {
                if (!$bot->testMode) {
                    UzbotLogEditor::create([
                        'bot' => $bot,
                        'count' => \count($affectedUserIDs),
                        'additionalData' => $defaultLanguage->getDynamicVariable('wcf.acp.uzbot.log.user.affected', [
                            'total' => \count($affectedUserIDs),
                            'userIDs' => \implode(', ', $affectedUserIDs),
                        ]),
                    ]);
                } else {
                    $result = $defaultLanguage->getDynamicVariable('wcf.acp.uzbot.log.test', [
                        'objects' => \count($affectedUserIDs),
                        'users' => \count($affectedUserIDs),
                        'userIDs' => \implode(', ', $affectedUserIDs),
                    ]);
                    if (\mb_strlen($result) > 64000) {
                        $result = \mb_substr($result, 0, 64000) . ' ...';
                    }
                    UzbotLogEditor::create([
                        'bot' => $bot,
                        'count' => \count($affectedUserIDs),
                        'testMode' => 1,
                        'additionalData' => \serialize(['', '', $result]),
                    ]);
                }
            }

            // notification
            if (!\count($affectedUserIDs)) {
                continue;
            }

            $notify = $bot->checkNotify(true, true);
            if ($notify === null) {
                continue;
            }

            $placeholders['date-from'] = $placeholders['time-from'] = $start;
            $placeholders['date-to'] = $placeholders['time-to'] = $end - 1;

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
