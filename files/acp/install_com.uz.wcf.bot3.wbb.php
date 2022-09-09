<?php
use wcf\data\uzbot\top\UzbotTopAction;
use wcf\system\WCF;

/**
 * Initializes wbb top data for Bot 
 * 
 * @author		2014-2022 Zaydowicz
 * @license		GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package		com.uz.wcf.bot3.wbb
 */

// posts
$topPosterID = null;
$sql = "SELECT		userID, wbbPosts
		FROM		wcf".WCF_N."_user
		ORDER BY 	wbbPosts DESC";
$statement = WCF::getDB()->prepareStatement($sql, 1);
$statement->execute();
$row = $statement->fetchArray();
if (!empty($row)) $topPosterID = $row['userID'];

$action = new UzbotTopAction([1], 'update', [
		'data' => [
				'post' => $topPosterID
		]
]);
$action->executeAction();