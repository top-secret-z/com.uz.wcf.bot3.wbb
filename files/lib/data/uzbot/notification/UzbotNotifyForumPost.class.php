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
namespace wbb\data\uzbot\notification;

use wbb\data\post\PostAction;
use wbb\data\thread\Thread;
use wcf\data\uzbot\log\UzbotLogEditor;
use wcf\data\uzbot\Uzbot;
use wcf\data\uzbot\UzbotEditor;
use wcf\system\exception\SystemException;
use wcf\system\html\input\HtmlInputProcessor;
use wcf\system\language\LanguageFactory;
use wcf\system\WCF;
use wcf\util\MessageUtil;

/**
 * Creates posts for Bot
 */
class UzbotNotifyForumPost
{
    public function send(Uzbot $bot, $content, $subject, $teaser, $language, $receiver, $tags)
    {
        // prepare texts and data
        $defaultLanguage = LanguageFactory::getInstance()->getLanguage(LanguageFactory::getInstance()->getDefaultLanguageID());

        $content = MessageUtil::stripCrap($content);

        // ufn no subject
        //$subject = MessageUtil::stripCrap(StringUtil::stripHTML($subject));
        //if (mb_strlen($subject) > 255) $subject = mb_substr($subject, 0, 250) . '...';
        $subject = '';

        // set publication time
        $publicationTime = TIME_NOW;
        if (isset($bot->publicationTime) && $bot->publicationTime) {
            $publicationTime = $bot->publicationTime;
        }

        if (!$bot->testMode) {
            $htmlInputProcessor = new HtmlInputProcessor();
            $htmlInputProcessor->process($content, 'com.woltlab.wbb.post', 0);

            // get post data and create
            try {
                $wbbPostData = \unserialize($bot->wbbPostData);

                // preset thread, maybe 0 on automatic post in affected thread
                if (!$wbbPostData['postNotifyThreadID']) {
                    $wbbPostData['postNotifyThreadID'] = $bot->threadID;
                }

                $thread = new Thread($wbbPostData['postNotifyThreadID']);
                $board = $thread->getBoard();

                $htmlInputProcessor = new HtmlInputProcessor();
                $htmlInputProcessor->process($content, 'com.woltlab.wbb.post', 0);

                $postData = [
                    'threadID' => $thread->threadID,
                    'subject' => $subject,
                    'message' => $content,
                    'time' => $publicationTime,
                    'userID' => $bot->senderID,
                    'username' => $bot->sendername,
                    'enableTime' => 0,
                    'isClosed' => $wbbPostData['postNotifyIsClosed'],
                    'isDisabled' => $wbbPostData['postNotifyIsDisabled'],
                    'ipAddress' => '',
                    'isOfficial' => $bot->postIsOfficial,
                    'isUzbot' => 1,
                ];

                $postCreateParameters = [
                    'data' => $postData,
                    'thread' => $thread,
                    'board' => $board,
                    'isFirstPost' => false,
                    'attachmentHandler' => null,
                    'htmlInputProcessor' => $htmlInputProcessor,
                ];

                $postAction = new PostAction([], 'create', $postCreateParameters);
                $postAction->executeAction();
            } catch (SystemException $e) {
                // users may get lost; check sender again to abort
                if (!$bot->checkSender(true, true)) {
                    return false;
                }

                // thread must exist / disable
                if (!$thread->threadID) {
                    $editor = new UzbotEditor($bot);
                    $editor->update(['isDisabled' => 1]);
                    UzbotEditor::resetCache();

                    if ($bot->enableLog) {
                        UzbotLogEditor::create([
                            'bot' => $bot,
                            'status' => 2,
                            'additionalData' => 'wcf.acp.uzbot.notify.post.threadID.error.notValid',
                        ]);

                        UzbotLogEditor::create([
                            'bot' => $bot,
                            'status' => 2,
                            'additionalData' => 'wcf.acp.uzbot.error.disabled',
                        ]);
                    }

                    return false;
                }

                // report any other error und continue
                if ($bot->enableLog) {
                    $error = $defaultLanguage->get('wcf.acp.uzbot.log.notify.error') . ' ' . $e->getMessage();

                    UzbotLogEditor::create([
                        'bot' => $bot,
                        'status' => 1,
                        'count' => 1,
                        'additionalData' => $error,
                    ]);
                }
            }
        } else {
            $teaser = '';
            if (\mb_strlen($content) > 63500) {
                $content = \mb_substr($content, 0, 63500) . ' ...';
            }
            $result = \serialize([$subject, $teaser, $content]);

            UzbotLogEditor::create([
                'bot' => $bot,
                'count' => 1,
                'testMode' => 1,
                'additionalData' => $result,
            ]);
        }
    }
}
