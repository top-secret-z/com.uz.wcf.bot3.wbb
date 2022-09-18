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

use wbb\data\board\Board;
use wbb\data\thread\Thread;
use wcf\data\cronjob\Cronjob;
use wcf\data\uzbot\stats\UzbotStats;
use wcf\data\uzbot\stats\UzbotStatsEditor;
use wcf\system\background\BackgroundQueueHandler;
use wcf\system\background\uzbot\NotifyScheduleBackgroundJob;
use wcf\system\cache\builder\UzbotValidBotCacheBuilder;
use wcf\system\cronjob\AbstractCronjob;
use wcf\system\WCF;

/**
 * Cronjob for Forum Stats for Bot.
 */
class UzbotStatsWbbCronjob extends AbstractCronjob
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

        // always create stats

        // read data
        $statsOld = new UzBotStats(1);
        $stats = new UzBotStats(1);

        // Make new stats
        // Board
        $sql = "SELECT    COUNT(*) as board,
                        COALESCE(SUM(isClosed), 0) AS boardClosed,
                        COALESCE(SUM(isInvisible), 0) AS boardInvisible
                FROM     wbb" . WCF_N . "_board";
        $statement = WCF::getDB()->prepareStatement($sql);
        $statement->execute();
        $row = $statement->fetchArray();
        $stats->board = $row['board'];
        $stats->boardClosed = $row['boardClosed'];
        $stats->boardInvisible = $row['boardInvisible'];

        $sql = "SELECT    COUNT(*) as total
                FROM     wbb" . WCF_N . "_board
                WHERE    boardType = ?";
        $statement = WCF::getDB()->prepareStatement($sql);
        $statement->execute([Board::TYPE_CATEGORY]);
        $row = $statement->fetchArray();
        $stats->boardCategory = $row['total'];

        $sql = "SELECT    COUNT(*) as total
                FROM     wbb" . WCF_N . "_board
                WHERE    boardType = ?";
        $statement = WCF::getDB()->prepareStatement($sql);
        $statement->execute([Board::TYPE_LINK]);
        $row = $statement->fetchArray();
        $stats->boardLink = $row['total'];

        // Thread
        $sql = "SELECT    COUNT(*) as thread,
                        COALESCE(SUM(isAnnouncement), 0) AS threadAnnouncement,
                        COALESCE(SUM(polls), 0) AS threadPoll,
                        COALESCE(SUM(isSticky), 0) AS threadSticky,
                        COALESCE(SUM(isDisabled), 0) AS threadDisabled,
                        COALESCE(SUM(isClosed), 0) AS threadClosed,
                        COALESCE(SUM(isDeleted), 0) AS threadDeleted,
                        COALESCE(SUM(views), 0) AS threadView
                FROM     wbb" . WCF_N . "_thread";
        $statement = WCF::getDB()->prepareStatement($sql);
        $statement->execute();
        $row = $statement->fetchArray();
        $stats->thread = $row['thread'];
        $stats->threadAnnouncement = $row['threadAnnouncement'];
        $stats->threadPoll = $row['threadPoll'];
        $stats->threadSticky = $row['threadSticky'];
        $stats->threadDisabled = $row['threadDisabled'];
        $stats->threadClosed = $row['threadClosed'];
        $stats->threadDeleted = $row['threadDeleted'];
        $stats->threadView = $row['threadView'];

        // Post
        $sql = "SELECT    COUNT(postID) as post,
                        COALESCE(SUM(isDisabled), 0) AS postDisabled,
                        COALESCE(SUM(isClosed), 0) AS postClosed,
                        COALESCE(SUM(isDeleted), 0) AS postDeleted,
                        COALESCE(SUM(editCount), 0) AS postEdit
                FROM     wbb" . WCF_N . "_post";
        $statement = WCF::getDB()->prepareStatement($sql);
        $statement->execute();
        $row = $statement->fetchArray();
        $stats->post = $row['post'];
        $stats->postDisabled = $row['postDisabled'];
        $stats->postClosed = $row['postClosed'];
        $stats->postDeleted = $row['postDeleted'];
        $stats->postEdit = $row['postEdit'];

        // don't update stats here

        // Read all active, valid activity bots, abort if none
        $bots = UzbotValidBotCacheBuilder::getInstance()->getData(['typeDes' => 'wbb_statistics']);
        if (!\count($bots)) {
            return;
        }

        $result = [
            'board' => $stats->board,
            'boardOld' => $statsOld->board,
            'boardClosed' => $stats->boardClosed,
            'boardClosedOld' => $statsOld->boardClosed,
            'boardInvisible' => $stats->boardInvisible,
            'boardInvisibleOld' => $statsOld->boardInvisible,
            'boardCategory' => $stats->boardCategory,
            'boardCategoryOld' => $statsOld->boardCategory,
            'boardLink' => $stats->boardLink,
            'boardLinkOld' => $statsOld->boardLink,
            'thread' => $stats->thread,
            'threadOld' => $statsOld->thread,
            'threadAnnouncement' => $stats->threadAnnouncement,
            'threadAnnouncementOld' => $statsOld->threadAnnouncement,
            'threadPoll' => $stats->threadPoll,
            'threadPollOld' => $statsOld->threadPoll,
            'threadSticky' => $stats->threadSticky,
            'threadStickyOld' => $statsOld->threadSticky,
            'threadDisabled' => $stats->threadDisabled,
            'threadDisabledOld' => $statsOld->threadDisabled,
            'threadClosed' => $stats->threadClosed,
            'threadClosedOld' => $statsOld->threadClosed,
            'threadClosed' => $stats->threadClosed,
            'threadClosedOld' => $statsOld->threadClosed,
            'threadDeleted' => $stats->threadDeleted,
            'threadDeletedOld' => $statsOld->threadDeleted,
            'threadView' => $stats->threadView,
            'threadViewOld' => $statsOld->threadView,
            'post' => $stats->post,
            'postOld' => $statsOld->post,
            'postDisabled' => $stats->postDisabled,
            'postDisabledOld' => $statsOld->postDisabled,
            'postClosed' => $stats->postClosed,
            'postClosedOld' => $statsOld->postClosed,
            'postDeleted' => $stats->postDeleted,
            'postDeletedOld' => $statsOld->postDeleted,
            'postEdit' => $stats->postEdit,
            'postEditOld' => $statsOld->postEdit,
        ];

        $placeholders['stats'] = $result;
        $placeholders['stats-lang'] = 'wcf.uzbot.wbb.stats';
        $placeholders['date-from'] = $statsOld->timeWbb;
        $placeholders['time-from'] = $statsOld->timeWbb;
        $placeholders['date-to'] = TIME_NOW;
        $placeholders['time-to'] = TIME_NOW;

        // Step through all bots and get updates
        foreach ($bots as $bot) {
            // update stats unless test mode
            if (!$bot->testMode) {
                $editor = new UzbotStatsEditor($stats);
                $editor->update([
                    'board' => $stats->board,
                    'boardClosed' => $stats->boardClosed,
                    'boardInvisible' => $stats->boardInvisible,
                    'boardCategory' => $stats->boardCategory,
                    'boardLink' => $stats->boardLink,
                    'thread' => $stats->thread,
                    'threadAnnouncement' => $stats->threadAnnouncement,
                    'threadPoll' => $stats->threadPoll,
                    'threadSticky' => $stats->threadSticky,
                    'threadDisabled' => $stats->threadDisabled,
                    'threadClosed' => $stats->threadClosed,
                    'threadDeleted' => $stats->threadDeleted,
                    'threadView' => $stats->threadView,
                    'post' => $stats->post,
                    'postDisabled' => $stats->postDisabled,
                    'postClosed' => $stats->postClosed,
                    'postDeleted' => $stats->postDeleted,
                    'postEdit' => $stats->postEdit,
                    'timeWbb' => TIME_NOW,
                ]);
            }

            // send to scheduler
            $notify = $bot->checkNotify(true, true);
            if ($notify === null) {
                continue;
            }

            $data = [
                'bot' => $bot,
                'placeholders' => $placeholders,
                'affectedUserIDs' => [],
                'countToUserID' => [],
            ];

            $job = new NotifyScheduleBackgroundJob($data);
            BackgroundQueueHandler::getInstance()->performJob($job);
        }
    }
}
