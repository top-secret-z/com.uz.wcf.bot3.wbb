<?php
namespace wbb\system\event\listener;
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
 * Listen to thread moderations for Bot
 * 
 * @author		2014-2022 Zaydowicz
 * @license		GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package		com.uz.wcf.bot3.wbb
 */
class UzbotThreadModerationListener implements IParameterizedEventListener {
	/**
	 * @inheritDoc
	 */
	public function execute($eventObj, $className, $eventName, array &$parameters) {
		// check module
		if (!MODULE_UZBOT) return;
		
		// only action create and user
		if ($eventObj->getActionName() != 'create') return;
		if (!WCF::getUser()->userID) return;
		
		// only thread modification
		$objectTypeID = ObjectTypeCache::getInstance()->getObjectTypeIDByName('com.woltlab.wcf.modifiableContent', 'com.woltlab.wbb.thread');
		$params = $eventObj->getParameters();
		if ($params['data']['objectTypeID'] != $objectTypeID) return;
		
		// only if bots
		$bots = UzbotValidBotCacheBuilder::getInstance()->getData(['typeDes' => 'wbb_threadModeration']);
		if (!count($bots)) return;
		
		// preset data
		$defaultLanguage = LanguageFactory::getInstance()->getLanguage(LanguageFactory::getInstance()->getDefaultLanguageID());
		$threadID = $params['data']['objectID'];
		$action = $params['data']['action'];
		$moderatorID = $params['data']['userID'];
		$additional = [];
		if (isset($params['data']['additionalData'])) $additional = unserialize($params['data']['additionalData']);
		
		// skip 'delete'
		$validActions = ['changeTopic', 'close', 'disable', 'done', 'enable', 'merge', 'move', 'open', 'restore', 'scrape', 'setAsAnnouncement', 'setLabel', 'sticky', 'trash', 'undone', 'unsetAsAnnouncement'];
		
		if (!in_array($action, $validActions)) return;
		
		// only moderation
		$thread = new Thread($threadID);
		if ($thread && $thread->userID == $moderatorID) return;
		
		// Step through bots
		foreach ($bots as $bot) {
			$affectedUserIDs = $countToUserID = $placeholders = [];
			$count = 0;
			
			// check action
			if (!stripos($bot->wbbThreadModerationData, $action)) continue;	// 0/false doesn't matter
			
			// check board
			if (!in_array($thread->boardID, unserialize($bot->uzbotBoardIDs))) continue;
			
			// check action
			$moderations = unserialize($bot->wbbThreadModerationData);
			$act = 0;
			foreach ($moderations as $key => $value) {
				if ($key == 'threadModerationAuthorOnly') continue;
				if (0 == strcasecmp($key, 'threadModeration'.$action) && $value == 1) {
					$act = 1;
					break;
				}
			}
			if (!$act) continue;
			
			// get potentially affected users
			if ($bot->changeAffected) {
				$affectedUserIDs[] = $moderatorID;
				$countToUserID[$moderatorID] = 1;
				$count = 1;
			}
			else {
				if ($moderations['threadModerationAuthorOnly']) {
					// leave if thread without author
					if (!$thread->userID) continue;
					
					$affectedUserIDs[] = $thread->userID;
					$countToUserID[$thread->userID] = 1;
					$count = 1;
				}
				else {
					// authors of posts are affected, only active posts
					$sql = "SELECT	userID
							FROM	wbb".WCF_N."_post
							WHERE 	threadID = ? AND userID > ? AND isDeleted = ? AND isDisabled = ?";
					$statement = WCF::getDB()->prepareStatement($sql);
					$statement->execute([$threadID, 0, 0, 0]);
					while ($row = $statement->fetchArray()) {
						$count ++;
						$affectedUserIDs[] = $row['userID'];
						if (isset($countToUserID[$row['userID']])) $countToUserID[$row['userID']] ++;
						else $countToUserID[$row['userID']] = 1;
					}
					// leave if no users
					if (!count($affectedUserIDs)) continue;
					
					$affectedUserIDs = array_unique($affectedUserIDs);
				}
			}
			
			// check users
			$conditions = $bot->getUserConditions();
			if (count($conditions)) {
				$userList = new UserList();
				$userList->getConditionBuilder()->add('user_table.userID IN (?)', [$affectedUserIDs]);
				foreach ($conditions as $condition) {
					$condition->getObjectType()->getProcessor()->addUserCondition($condition, $userList);
				}
				$userList->readObjects();
				if (!count($userList->getObjects())) continue;
			}
			
			// found users, board and a valid action, get data
			// log action
			if ($bot->enableLog) {
				if (!$bot->testMode) {
					UzbotLogEditor::create([
							'bot' => $bot,
							'count' => $count,
							'additionalData' => $defaultLanguage->getDynamicVariable('wcf.acp.uzbot.log.user.affected', [
									'total' => count($affectedUserIDs),
									'userIDs' => implode(', ', $affectedUserIDs)
							])
					]);
				}
				else {
					$result = $defaultLanguage->getDynamicVariable('wcf.acp.uzbot.log.test', [
							'objects' => $count,
							'users' => count($affectedUserIDs),
							'userIDs' => implode(', ', $affectedUserIDs)
					]);
					if (mb_strlen($result) > 64000) $result = mb_substr($result, 0, 64000) . ' ...';
					UzbotLogEditor::create([
							'bot' => $bot,
							'count' => $count,
							'testMode' => 1,
							'additionalData' => serialize(['', '', $result])
					]);
				}
			}
			
			// check for and prepare notification
			$notify = $bot->checkNotify(true, true);
			if ($notify === null) continue;
			
			$bot->threadID = $thread->threadID;
			$board = $thread->getBoard();
			$moderator = WCF::getUser();
			
			$placeholders['board-id'] = $thread->boardID;
			$placeholders['board-name'] = $thread->getBoard()->title;
			$placeholders['count'] = count($affectedUserIDs);
			
			$placeholders['moderation-action'] = 'wcf.uzbot.wbb.moderation.' . $action;
			$placeholders['moderation-reason'] = '';
			if (isset($additional['reason'])) $placeholders['moderation-reason'] = $additional['reason'];
			$placeholders['moderator-id'] = $moderator->userID;
			$placeholders['moderator-link'] = $moderator->getLink();
			$placeholders['moderator-link2'] = StringUtil::getAnchorTag($moderator->getLink(), $moderator->username);
			$placeholders['moderator-name'] = $moderator->username;
			
			$placeholders['thread-link'] = $thread->getLink();
			$placeholders['thread-subject'] = $thread->getTitle();
			$placeholders['thread-text'] = $thread->getFirstPost()->getMessage();
			$placeholders['translate'] = ['board-name', 'moderation-action'];
			
			// test mode
			$testUserIDs = $testToUserIDs = [];
			$testUserIDs[] = $affectedUserIDs[0];
			$testToUserIDs[$affectedUserIDs[0]] = $countToUserID[$affectedUserIDs[0]];
			
			// send to scheduler
			$data = [
					'bot' => $bot,
					'placeholders' => $placeholders,
					'affectedUserIDs' => !$bot->testMode ? $affectedUserIDs : $testUserIDs,
					'countToUserID' => !$bot->testMode ? $countToUserID : $testToUserIDs
			];
			
			$job = new NotifyScheduleBackgroundJob($data);
			BackgroundQueueHandler::getInstance()->performJob($job);
		}
	}
}
